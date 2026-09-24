<?php

namespace local_samce\task;

use local_samce\event_batch;
use local_samce\token_signer;

defined('MOODLE_INTERNAL') || die();

/**
 * Manda al backend un aviso capture_status: una página del examen se entregó y la
 * captura no estuvo (JavaScript apagado, o no arrancó). Lo genera el servidor de
 * Moodle y no el navegador del alumno, que es lo único que puede afirmarlo sin
 * depender de que el JavaScript corra. El backend lo guarda y el panel le muestra
 * al docente la marca "Sin captura".
 *
 * Si falla (el backend no contesta, o el intento todavía no tiene sesión porque
 * el aviso de inicio se reintenta) lanza una excepción: Moodle reprograma sola la
 * tarea con demora creciente, igual que retry_exam_event.
 *
 * @package local_samce
 */
class send_capture_status extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('tasksendcapturestatus', 'local_samce');
    }

    public function execute() {
        $data = (array) $this->get_custom_data();
        $attemptid = (int) ($data['attemptid'] ?? 0);
        $state = (string) ($data['state'] ?? '');
        if ($attemptid <= 0 || !in_array($state, ['js_disabled', 'no_start'], true)) {
            return;
        }

        $secret = get_config('local_samce', 'launchsecret');
        $eventsurl = event_batch::events_url((string) get_config('local_samce', 'backendurl'));
        if (empty($secret) || $eventsurl === '') {
            return;
        }

        $ms = (int) round(microtime(true) * 1000);
        $now = time();
        $token = token_signer::sign([
            'event_type'        => 'interaction_events',
            'moodle_attempt_id' => $attemptid,
            'events'            => [[
                // seq y t los arma el servidor de Moodle; el seq parte de la hora igual que el del navegador.
                'seq'  => $ms * 1000 + random_int(0, 999),
                't'    => $ms,
                'type' => 'capture_status',
                'data' => (object) ['state' => $state],
            ]],
            'iat'               => $now,
            'exp'               => $now + 60,
        ], $secret);

        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->post($eventsurl, json_encode(['token' => $token]), [
            'CURLOPT_CONNECTTIMEOUT_MS' => 2000,
            'CURLOPT_TIMEOUT_MS'        => 5000,
        ]);

        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $code === 0 || $code === 404 || $code === 429 || $code >= 500) {
            throw new \moodle_exception('tasksendcapturestatusfailed', 'local_samce');
        }
        if ($code !== 200) {
            debugging('local_samce: el backend rechazó el aviso de captura (HTTP ' . $code . ', attemptid=' .
                $attemptid . ')', DEBUG_NORMAL);
        }
    }
}
