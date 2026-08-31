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

    /** Vigencia del token de evento: viaja en un POST inmediato, no en una
     * URL, pero se mantiene corta por el mismo criterio que el lanzamiento
     * del docente. */
    const EVENT_TOKEN_TTL_SECONDS = 60;

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
        global $CFG;

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

        $now = time();
        $claims = [
            'event_type'        => $eventtype,
            'moodle_attempt_id' => (int) $event->objectid,
            'moodle_user_id'    => (int) $event->relateduserid,
            'student_name'      => $studentname,
            'course_id'         => (int) $event->courseid,
            'quiz_id'           => $cm ? (int) $cm->instance : 0,
            'quiz_name'         => $cm ? format_string($cm->name) : '',
            'iat'               => $now,
            'exp'               => $now + self::EVENT_TOKEN_TTL_SECONDS,
        ];

        $token = token_signer::sign($claims, $secret);

        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->post($backendurl, json_encode(['token' => $token]));

        if ($curl->get_errno()) {
            debugging('local_samce: fallo al notificar el evento "' . $eventtype . '" al backend: ' .
                $curl->error, DEBUG_NORMAL);
        }
    }
}
