<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Escucha los eventos de intento de examen de Moodle y avisa a
 * samce-backend para que abra o cierre la sesión de monitoreo
 * correspondiente (HU02, SAMCE-8). Corre enteramente del lado del
 * servidor, sin ningún agente en el navegador del alumno (eso es HU10,
 * Sprint 2, todavía sin empezar) — el alumno rinde exactamente igual que
 * hoy, sin ninguna diferencia visible.
 *
 * @package local_samce
 */
class observer {

    /**
     * El alumno arrancó un intento de examen.
     *
     * @param \mod_quiz\event\attempt_started $event
     */
    public static function quiz_attempt_started(\mod_quiz\event\attempt_started $event): void {
        self::notify_backend('attempt_started', $event);
    }

    /**
     * El alumno entregó el examen.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::notify_backend('attempt_submitted', $event);
    }

    /**
     * Arma y firma el payload del evento, y lo manda a samce-backend.
     *
     * El id de examen que persiste el backend es moodle_quiz_id (mdl_quiz.id),
     * no el course module id (cmid) — son dos identificadores distintos en
     * Moodle. El evento attempt_started NO trae other.quizid poblado para
     * intentos reales (solo para vistas previas del docente, verificado
     * contra el código fuente real de mod_quiz/locallib.php), así que no se
     * puede usar ese campo. La forma confiable para los dos eventos es
     * `contextinstanceid` (el cmid, siempre presente) → course_modules →
     * `instance`, que es exactamente el moodle_quiz_id. La misma consulta
     * trae también el nombre del examen.
     */
    private static function notify_backend(string $eventtype, \core\event\base $event): void {
        $secret = get_config('local_samce', 'launchsecret');
        $backendurl = get_config('local_samce', 'backendurl');
        if (empty($secret) || empty($backendurl)) {
            debugging('local_samce: no se pudo notificar el evento de examen al backend ' .
                '(falta configurar el secreto o la URL del backend)', DEBUG_NORMAL);
            return;
        }

        $cm = get_coursemodule_from_id('quiz', (int) $event->contextinstanceid, 0, false, IGNORE_MISSING);

        // El nombre del alumno nunca viajaba (detectado por Facu en
        // revisión): el backend solo recibía moodle_user_id, y el panel no
        // tenía ningún dato legible para mostrar. Se agrega acá porque acá
        // es donde Moodle todavía tiene al alumno identificado de forma
        // confiable ($event->relateduserid) — el backend lo cifra igual que
        // el id antes de persistirlo (ver moodle_event.go).
        $student = \core_user::get_user((int) $event->relateduserid);
        $studentname = $student ? fullname($student) : '';

        // Sin iat ni exp acá: backend_notifier::send_exam_event() los pone,
        // con la hora del intento que en definitiva se mande (el de ahora, o
        // el de la tarea de reintento si hace falta).
        $claims = [
            // Distingue este aviso de un token de lanzamiento del docente
            // (launch.php/launch_global.php), firmado con el mismo secreto:
            // sin esto, este aviso también pasaba la verificación del panel.
            'token_type'        => 'exam_event',
            'event_type'        => $eventtype,
            'moodle_attempt_id' => (int) $event->objectid,
            'moodle_user_id'    => (int) $event->relateduserid,
            'student_name'      => $studentname,
            'course_id'         => (int) $event->courseid,
            'quiz_id'           => $cm ? (int) $cm->instance : 0,
            'quiz_name'         => $cm ? format_string($cm->name) : '',
        ];

        if (backend_notifier::send_exam_event($claims, $secret, $backendurl)) {
            return;
        }

        // El envío inmediato falló (backend_notifier ya dejó el debugging()
        // con el motivo). Sin la sesión que abre este aviso, todos los
        // lotes de eventos de este alumno se van a rechazar con 404 después
        // (punto 1 de la revisión externa del 23/09/2026), así que se
        // reintenta más tarde con una tarea en segundo plano, en vez de
        // darlo por perdido acá. Se encolan los claims, no el token: el
        // token ya firmado vence a los 60 segundos y la tarea puede tardar
        // minutos en correr.
        $task = new \local_samce\task\retry_exam_event();
        $task->set_custom_data($claims);
        \core\task\manager::queue_adhoc_task($task);
    }
}
