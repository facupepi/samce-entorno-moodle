<?php

namespace local_samce\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_samce\event_batch;

/**
 * Deja constancia, del lado del servidor, de que el alumno vio el aviso de
 * monitoreo de un intento.
 *
 * Antes la aceptación vivía solo en el navegador (localStorage) y el único
 * registro era el evento consent_accepted, que viaja en la misma cola que todo
 * lo demás, con hora del cliente, y se pierde si la cola se desborda. Y
 * local_samce_send_events no comprobaba nada: sin haber aceptado, un POST
 * directo cargaba eventos igual. Ahora la constancia es una preferencia del
 * usuario con la hora (local_samce_notice_<intento>), y send_events.php la
 * exige antes de aceptar un lote: la constancia y la puerta son la misma cosa.
 *
 * @package local_samce
 */
class accept_notice extends external_api {

    /** Prefijo de la preferencia de usuario; el resto es el id del intento. */
    const PREFERENCE_PREFIX = 'local_samce_notice_';

    /**
     * Prefijo de la autorización de comienzo: se guarda al tocar "Leí el aviso y
     * continúo" en la página del cuestionario, cuando todavía no existe el
     * intento. La puerta del servidor (hook_callbacks::after_config) la exige
     * para crear un intento nuevo.
     */
    const START_PREFERENCE_PREFIX = 'local_samce_start_';

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Id del intento de examen', VALUE_DEFAULT, 0),
            'cmid' => new external_value(PARAM_INT, 'Id del módulo del cuestionario, antes de que exista el intento',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * @param int $attemptid Intento cuyo aviso se vio (0 si se manda cmid).
     * @param int $cmid Cuestionario cuyo aviso se vio, antes de comenzar el intento (0 si se manda attemptid).
     * @return array status: 'ok', 'disabled' (la captura está apagada o mal
     *               configurada) o 'rejected' (no es un intento propio en curso).
     */
    public static function execute(int $attemptid = 0, int $cmid = 0): array {
        global $DB, $USER;

        try {
            $params = self::validate_parameters(self::execute_parameters(),
                ['attemptid' => $attemptid, 'cmid' => $cmid]);

            if (!get_config('local_samce', 'capture_enabled')) {
                return ['status' => 'disabled'];
            }
            $secret = get_config('local_samce', 'launchsecret');
            if (empty($secret) || event_batch::events_url((string) get_config('local_samce', 'backendurl')) === '') {
                return ['status' => 'disabled'];
            }

            if ($params['cmid'] > 0) {
                // Antes de comenzar: todavía no hay intento. Se guarda la hora, que la
                // puerta del servidor exige (y vence) para crear el intento.
                $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, IGNORE_MISSING);
                if (!$cm) {
                    return ['status' => 'rejected'];
                }
                $context = \context_module::instance($cm->id);
                self::validate_context($context);
                require_capability('mod/quiz:attempt', $context);
                set_user_preference(self::START_PREFERENCE_PREFIX . (int) $cm->id, time());
                return ['status' => 'ok'];
            }

            $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']],
                'id, quiz, userid, state, preview');
            if (!$attempt || (int) $attempt->userid !== (int) $USER->id ||
                    $attempt->state !== 'inprogress' || !empty($attempt->preview)) {
                return ['status' => 'rejected'];
            }

            $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, IGNORE_MISSING);
            if (!$cm) {
                return ['status' => 'rejected'];
            }
            $context = \context_module::instance($cm->id);
            self::validate_context($context);
            require_capability('mod/quiz:attempt', $context);

            // Cada vez que la captura arranca en una página del intento, se anota: es lo que
            // permite saber que la página no corrió sin captura (capture_watch).
            set_user_preference(\local_samce\capture_watch::SEEN_PREFIX . (int) $attempt->id, time());

            $name = self::PREFERENCE_PREFIX . (int) $attempt->id;
            // La primera hora es la que vale: un reenvío no la pisa.
            if (get_user_preferences($name, null) === null) {
                set_user_preference($name, time());
            }
            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            debugging('local_samce: no se pudo registrar el aviso del intento: ' . $e->getMessage(), DEBUG_NORMAL);
            return ['status' => 'rejected'];
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'ok, disabled o rejected'),
        ]);
    }

    /**
     * Si el alumno actual tiene registrado el aviso de un intento.
     *
     * @param int $attemptid
     * @return bool
     */
    public static function is_accepted(int $attemptid): bool {
        return get_user_preferences(self::PREFERENCE_PREFIX . $attemptid, null) !== null;
    }
}
