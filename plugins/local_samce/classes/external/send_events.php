<?php

namespace local_samce\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_samce\event_batch;
use local_samce\send_policy;
use local_samce\token_signer;

/**
 * Recibe de la página del examen un lote de eventos de interacción del alumno
 * y lo reenvía, firmado, a samce-backend (HU10).
 *
 * El navegador del alumno nunca habla con el backend: le entrega los eventos
 * a Moodle, y es esta función, ya con el usuario verificado por Moodle, la que
 * los firma y los reenvía. Se expone solo para AJAX de páginas de Moodle
 * (ajax => true en db/services.php), sin habilitar servicios web ni emitir
 * ningún token.
 *
 * Esta función no lanza errores hacia la página por fallas del monitoreo. Los
 * devuelve como un estado, para que ninguna falla de SAMCE interrumpa, demore
 * ni ensucie el examen: el alumno responde, navega y entrega igual.
 *
 * @package local_samce
 */
class send_events extends external_api {

    /** Segundos que se espera para conectar con el backend. */
    const CONNECT_TIMEOUT = 2;

    /** Segundos que se espera, en total, por la respuesta del backend. */
    const TOTAL_TIMEOUT = 3;

    /** Segundos, después de entregado un intento, en que todavía se aceptan
     * eventos: el vaciado final del buffer llega junto con la entrega. */
    const LATE_GRACE_SECONDS = 120;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Id del intento de examen'),
            'events' => new external_value(PARAM_RAW, 'Lote de eventos, en JSON'),
            // Identifica el contexto de captura del navegador (una carga de
            // página). Opcional: un JS viejo en caché no lo manda.
            'contextid' => new external_value(PARAM_ALPHANUMEXT, 'Id del contexto de captura', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * @param int $attemptid Intento al que pertenecen los eventos.
     * @param string $events Lista de eventos en JSON.
     * @param string $contextid Id del contexto de captura (puede venir vacío).
     * @return array Con un solo campo, status: 'ok' (guardado), 'disabled'
     *               (la captura está apagada o mal configurada: el cliente
     *               debe dejar de capturar), 'rejected' (el lote no se puede
     *               aceptar: el cliente lo descarta) o 'retry' (falló el
     *               envío: el cliente lo conserva y reintenta).
     */
    public static function execute(int $attemptid, string $events, string $contextid = ''): array {
        global $CFG, $DB, $USER;

        try {
            $params = self::validate_parameters(self::execute_parameters(), [
                'attemptid' => $attemptid,
                'events' => $events,
                'contextid' => $contextid,
            ]);

            if (!get_config('local_samce', 'capture_enabled')) {
                return self::result('disabled');
            }

            $secret = get_config('local_samce', 'launchsecret');
            $eventsurl = event_batch::events_url((string) get_config('local_samce', 'backendurl'));
            if (empty($secret) || $eventsurl === '') {
                debugging('local_samce: no se pudieron enviar los eventos de interacción ' .
                    '(falta configurar el secreto o la URL del backend)', DEBUG_NORMAL);
                return self::result('disabled');
            }

            // Solo el dueño del intento, y solo mientras el intento está en
            // curso (o recién entregado). Nunca la revisión de un intento
            // viejo ni el intento de otro alumno. La vista previa de un
            // docente tampoco: no tiene sesión en el backend.
            $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']],
                'id, quiz, userid, state, preview, timestart, timefinish');
            if (!$attempt || (int) $attempt->userid !== (int) $USER->id || !empty($attempt->preview)) {
                return self::result('rejected');
            }
            if (!self::attempt_accepts_events($attempt)) {
                return self::result('rejected');
            }

            $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, IGNORE_MISSING);
            if (!$cm) {
                return self::result('rejected');
            }
            $context = \context_module::instance($cm->id);
            self::validate_context($context);
            require_capability('mod/quiz:attempt', $context);

            // Sin la constancia del aviso no se acepta ningún evento. 'disabled'
            // hace que el navegador deje de capturar y vacíe la cola: si nunca se
            // vio el aviso, no hay monitoreo.
            if (!accept_notice::is_accepted((int) $attempt->id)) {
                return self::result('disabled');
            }

            $dropped = 0;
            $clean = event_batch::parse($params['events'], $dropped);
            if ($dropped > 0) {
                debugging('local_samce: ' . $dropped . ' evento(s) con data demasiado grande se descartaron del lote ' .
                    '(attemptid=' . (int) $params['attemptid'] . ')', DEBUG_NORMAL);
            }
            if ($clean === null) {
                // Hasta ahora este descarte era mudo (punto 20 de la revisión
                // externa del 23/09/2026): el lote entero se pierde, y del
                // lado del backend nunca llega a verse. El navegador del
                // alumno controla este contenido, así que solo se registra el
                // intento, no lo que trajo.
                debugging('local_samce: lote de eventos inválido, se descarta entero (attemptid=' .
                    (int) $params['attemptid'] . ')', DEBUG_NORMAL);
                return self::result('rejected');
            }

            // Tope por intento: el contenido lo arma el navegador y no se puede
            // verificar, pero se acota cuánto puede mandar una sola sesión.
            global $SESSION;
            if (!isset($SESSION->local_samce_quota) || !is_array($SESSION->local_samce_quota)) {
                $SESSION->local_samce_quota = [];
            }
            $quota = $SESSION->local_samce_quota[(int) $attempt->id] ?? [];
            $within = send_policy::within_quota($quota, count($clean), strlen($params['events']), time());
            $SESSION->local_samce_quota[(int) $attempt->id] = $quota;
            if (!$within) {
                debugging('local_samce: el intento superó el tope de eventos y se descarta el lote (attemptid=' .
                    (int) $attempt->id . ')', DEBUG_NORMAL);
                return self::result('rejected');
            }

            $now = time();
            $claims = [
                'event_type'        => 'interaction_events',
                'moodle_attempt_id' => (int) $attempt->id,
                'events'            => $clean,
                'iat'               => $now,
                'exp'               => $now + 60,
            ];
            // El mismo tope que aplica el backend (36 caracteres).
            $contextid = substr((string) $params['contextid'], 0, 36);
            if ($contextid !== '') {
                $claims['context_id'] = $contextid;
            }
            $token = token_signer::sign($claims, $secret);

            // Ya no hace falta la sesión de Moodle: liberarla antes de
            // esperar al backend evita que una demora de SAMCE trabe la
            // página siguiente del alumno, que necesita esa misma sesión.
            \core\session\manager::write_close();

            return self::result(self::forward($eventsurl, $token, (int) $attempt->id,
                time() - (int) $attempt->timestart));
        } catch (\Throwable $e) {
            debugging('local_samce: fallo al procesar los eventos de interacción: ' . $e->getMessage(), DEBUG_NORMAL);
            return self::result('rejected');
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'ok, disabled, rejected o retry'),
        ]);
    }

    /**
     * Un intento acepta eventos mientras está en curso, y unos minutos después
     * de entregado, para el último vaciado del buffer.
     */
    private static function attempt_accepts_events(\stdClass $attempt): bool {
        if ($attempt->state === 'inprogress') {
            return true;
        }

        return !empty($attempt->timefinish) && (time() - (int) $attempt->timefinish) <= self::LATE_GRACE_SECONDS;
    }

    /**
     * Manda el lote firmado al backend, con timeouts cortos, y traduce la
     * respuesta a un estado para el cliente.
     */
    private static function forward(string $eventsurl, string $token, int $attemptid, int $attemptage): string {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->post($eventsurl, json_encode(['token' => $token]), [
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT,
            'CURLOPT_TIMEOUT'        => self::TOTAL_TIMEOUT,
        ]);

        if ($curl->get_errno()) {
            return 'retry';
        }

        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        $status = send_policy::status_for_http_code($code, $attemptage);
        if ($status !== 'rejected') {
            return $status;
        }

        // Un rechazo definitivo del backend (firma, formato, un tipo de evento
        // que el plugin y el backend no comparten, un intento sin sesión) borra
        // el lote en el cliente. Hasta ahora no quedaba ni una línea del lado de
        // Moodle (punto 20 de la revisión externa del 23/09/2026).
        debugging('local_samce: el backend rechazó un lote de eventos (HTTP ' . $code .
            ', attemptid=' . $attemptid . '), se descarta', DEBUG_NORMAL);
        return 'rejected';
    }

    private static function result(string $status): array {
        return ['status' => $status];
    }
}
