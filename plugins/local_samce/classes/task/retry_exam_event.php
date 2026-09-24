<?php

namespace local_samce\task;

use local_samce\backend_notifier;

defined('MOODLE_INTERNAL') || die();

/**
 * Reintenta, más tarde, un aviso de examen que no pudo notificarse a
 * samce-backend en el momento (punto 1 de la revisión externa del
 * 23/09/2026).
 *
 * Sin esto, un aviso de inicio perdido (un corte de red de medio segundo
 * alcanza) hace que la sesión de ese alumno nunca se cree: todo lo que
 * capture después se rechaza con 404, en silencio, hasta vaciar la cola sin
 * dejar ningún rastro. observer.php encola esta tarea con los mismos datos
 * del aviso (sin el token ya firmado, que vence a los 60 segundos: para
 * cuando corre el cron ya estaría vencido) cada vez que el envío inmediato
 * falla.
 *
 * No arma un backoff propio: si execute() lanza una excepción, Moodle
 * reprograma sola la tarea con demora creciente (\core\task\manager), que es
 * exactamente lo que hace falta acá. Del lado del backend no hace falta
 * ningún cambio: abrir o cerrar una sesión ya es idempotente sobre
 * moodle_attempt_id (ver exam_session.go), así que un reintento que en
 * realidad ya no hacía falta no duplica nada.
 *
 * @package local_samce
 */
class retry_exam_event extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('taskretryexamevent', 'local_samce');
    }

    public function execute() {
        $claims = (array) $this->get_custom_data();

        $secret = get_config('local_samce', 'launchsecret');
        $backendurl = get_config('local_samce', 'backendurl');
        if (empty($secret) || empty($backendurl)) {
            // Sigue sin configurarse: reintentar a ciegas no sirve de nada,
            // y observer.php ya lo dejó anotado con debugging() la primera
            // vez. No se lanza excepción, para no reprogramar para siempre
            // algo que solo un cambio de configuración puede arreglar.
            return;
        }

        if (!backend_notifier::send_exam_event($claims, $secret, $backendurl)) {
            throw new \moodle_exception('taskretryexameventfailed', 'local_samce');
        }
    }
}
