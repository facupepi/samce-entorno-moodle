<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Firma y manda un aviso de examen a samce-backend, con los timeouts y el
 * chequeo del código HTTP ya resueltos.
 *
 * Separado de observer.php para que classes/task/retry_exam_event.php pueda
 * reintentar exactamente el mismo envío sin duplicar la lógica. Firma un
 * token nuevo cada vez (con iat/exp propios) en vez de reutilizar uno viejo:
 * el token vence a los EVENT_TOKEN_TTL_SECONDS de firmado, y una tarea en
 * segundo plano de Moodle puede tardar minutos en correr.
 *
 * @package local_samce
 */
class backend_notifier {

    /** Vigencia del token: viaja en un POST inmediato (o en el reintento de
     * una tarea en segundo plano), así que se mantiene corta a propósito. */
    const EVENT_TOKEN_TTL_SECONDS = 60;

    /** Segundos que se espera para conectar con el backend. */
    const REQUEST_CONNECT_TIMEOUT_SECONDS = 2;

    /** Segundos que se espera, en total, por la respuesta del backend. */
    const REQUEST_TIMEOUT_SECONDS = 3;

    /**
     * Firma y manda un aviso de examen. No lanza ninguna excepción: informa
     * el resultado en el valor de vuelta, y deja un debugging() con el
     * motivo cuando falla — nada de esto puede demorar ni ensuciar la
     * página del examen.
     *
     * @param array $claims Claims del aviso, sin iat ni exp (se agregan acá,
     *                        con la hora de este intento puntual).
     * @param string $secret Secreto compartido con MOODLE_LAUNCH_SECRET.
     * @param string $backendurl URL de samce-backend que recibe el aviso.
     * @return bool true si el backend lo aceptó (sin error de red y con un
     *              código HTTP menor a 400).
     */
    public static function send_exam_event(array $claims, string $secret, string $backendurl): bool {
        global $CFG;

        $now = time();
        $claims['iat'] = $now;
        $claims['exp'] = $now + self::EVENT_TOKEN_TTL_SECONDS;
        $token = token_signer::sign($claims, $secret);

        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        // Con timeouts propios: sin ellos el POST espera hasta 30 segundos
        // para conectar y no tiene límite total de respuesta, y como el
        // aviso de inicio sale mientras el alumno abre el examen, un
        // backend que acepta la conexión y no contesta lo dejaría
        // esperando. Ninguna falla del monitoreo puede demorar el examen.
        $curl->post($backendurl, json_encode(['token' => $token]), [
            'CURLOPT_CONNECTTIMEOUT' => self::REQUEST_CONNECT_TIMEOUT_SECONDS,
            'CURLOPT_TIMEOUT'        => self::REQUEST_TIMEOUT_SECONDS,
        ]);

        // get_errno() solo ve fallas de red (no conectó, timeout): un 404 o
        // un 500 del backend curl lo cuenta como éxito.
        $httpcode = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $httpcode >= 400) {
            $eventtype = $claims['event_type'] ?? '?';
            debugging('local_samce: fallo al notificar el evento "' . $eventtype . '" al backend' .
                ($httpcode ? " (HTTP {$httpcode})" : '') . ': ' . $curl->error, DEBUG_NORMAL);
            return false;
        }
        return true;
    }
}
