<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Callbacks de los hooks de Moodle que usa local_samce (ver db/hooks.php).
 *
 * @package local_samce
 */
class hook_callbacks {

    /** Cada cuántos milisegundos el navegador entrega al servidor lo juntado. */
    const FLUSH_INTERVAL_MS = 5000;

    /**
     * Carga el módulo de captura únicamente en la página de un intento de
     * examen propio y en curso.
     *
     * En cualquier otra página no hace nada, para que la captura no salga
     * nunca de ahí: ni de las demás páginas del campus, ni de la revisión de
     * un intento ya entregado, ni del intento de otro alumno, ni de la vista
     * previa de un docente. La función externa vuelve a comprobarlo del lado
     * del servidor, así que esto es la primera barrera y no la única.
     *
     * Nada de lo que pase acá puede romper la página del examen: cualquier
     * excepción se ignora y el alumno rinde como si el complemento no
     * estuviera.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $DB, $PAGE, $USER;

        try {
            if ($PAGE->pagetype !== 'mod-quiz-attempt') {
                return;
            }
            if (!isloggedin() || isguestuser() || !get_config('local_samce', 'capture_enabled')) {
                return;
            }

            $attemptid = optional_param('attempt', 0, PARAM_INT);
            if ($attemptid <= 0) {
                return;
            }

            $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id, userid, state, preview');
            if (!$attempt || (int) $attempt->userid !== (int) $USER->id ||
                    $attempt->state !== 'inprogress' || !empty($attempt->preview)) {
                return;
            }

            // Se carga consent, no capture directo: HU11 (RF05) exige que la
            // captura no arranque sin la aceptación explícita del alumno. Es
            // consent.js el que decide, ya en el navegador, si hace falta
            // mostrar el aviso o si alcanza con arrancar la captura.
            $PAGE->requires->js_call_amd('local_samce/consent', 'init', [[
                'attemptid'     => (int) $attempt->id,
                'flushms'       => self::FLUSH_INTERVAL_MS,
                'noticetitle'   => get_string('consentnoticetitle', 'local_samce'),
                'noticebody'    => get_string('consentnoticebody', 'local_samce'),
                'noticeaccept'  => get_string('consentaccept', 'local_samce'),
                'indicatortext' => get_string('indicatortext', 'local_samce'),
            ]]);
        } catch (\Throwable $e) {
            debugging('local_samce: no se pudo cargar la captura de eventos: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
}
