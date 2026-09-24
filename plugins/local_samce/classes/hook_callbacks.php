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
     * Páginas del intento en las que corre el agente. La de resumen
     * (summary.php) es la última que ve el alumno antes de entregar y el
     * intento sigue en curso ahí, así que HU12 pide el indicador también.
     * La revisión (mod-quiz-review) queda afuera: comparte el parámetro
     * `attempt` pero es de un intento ya entregado. Se filtra por pagetype,
     * nunca por URL ni por la presencia del parámetro.
     */
    const MONITORED_PAGETYPES = ['mod-quiz-attempt', 'mod-quiz-summary'];

    /**
     * Carga el módulo de captura únicamente en las páginas de un intento de
     * examen propio y en curso (ver MONITORED_PAGETYPES).
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
            // La página del cuestionario (donde está "Comenzar intento") no
            // captura nada: solo pide el consentimiento antes de que Moodle cree
            // el intento.
            if ($PAGE->pagetype === 'mod-quiz-view') {
                self::load_start_gate();
                return;
            }

            if (!in_array($PAGE->pagetype, self::MONITORED_PAGETYPES, true)) {
                return;
            }
            if (!isloggedin() || isguestuser() || !get_config('local_samce', 'capture_enabled')) {
                return;
            }

            // Misma condición que send_events.php: sin secreto o sin URL del
            // backend no va a haber monitoreo, así que tampoco se le muestra
            // al alumno un aviso de algo que no existe.
            $secret = get_config('local_samce', 'launchsecret');
            $eventsurl = event_batch::events_url((string) get_config('local_samce', 'backendurl'));
            if (empty($secret) || $eventsurl === '') {
                debugging('local_samce: captura habilitada pero sin secreto o sin URL del backend', DEBUG_NORMAL);
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

            // Los exámenes monitoreados se rinden en Chrome, en una computadora.
            // Con otro navegador, o un teléfono o tablet, no se carga ni el aviso
            // de consentimiento ni la captura: se tapa el examen con un aviso
            // que solo deja volver. Sin el ajuste guardado (una instalación
            // que nunca lo tocó) rige como encendido.
            $restrict = get_config('local_samce', 'restrict_browser');
            if (($restrict === false || (string) $restrict !== '0') &&
                    !browser_check::is_supported((string) \core_useragent::get_user_agent_string())) {
                $PAGE->requires->js_call_amd('local_samce/blocked', 'init', [[
                    'title'   => get_string('browserblockedtitle', 'local_samce'),
                    'body'    => get_string('browserblockedbody', 'local_samce'),
                    'back'    => get_string('browserblockedback', 'local_samce'),
                    'backurl' => $PAGE->cm
                        ? (new \moodle_url('/mod/quiz/view.php', ['id' => $PAGE->cm->id]))->out(false)
                        : '',
                ]]);
                return;
            }

            // Se carga consent, no capture directo: HU11 (RF05) exige que la
            // captura no arranque sin la aceptación explícita del alumno. Es
            // consent.js el que decide, ya en el navegador, si hace falta
            // mostrar el aviso o si alcanza con arrancar la captura.
            $PAGE->requires->js_call_amd('local_samce/consent', 'init', [[
                'attemptid'     => (int) $attempt->id,
                'cmid'          => $PAGE->cm ? (int) $PAGE->cm->id : 0,
                'flushms'       => self::FLUSH_INTERVAL_MS,
                'noticetitle'   => get_string('consentnoticetitle', 'local_samce'),
                'noticebody'    => self::notice_body(),
                'noticeaccept'  => get_string('consentaccept', 'local_samce'),
                'noticedecline' => get_string('consentdecline', 'local_samce'),
                // Adonde vuelve el alumno si no acepta: la página del cuestionario.
                'declineurl'    => $PAGE->cm
                    ? (new \moodle_url('/mod/quiz/view.php', ['id' => $PAGE->cm->id]))->out(false)
                    : '',
                'indicatortext' => get_string('indicatortext', 'local_samce'),
            ]]);
        } catch (\Throwable $e) {
            debugging('local_samce: no se pudo cargar la captura de eventos: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
    /**
     * En la página del cuestionario: si el alumno va a poder rendir con
     * monitoreo, pide el consentimiento antes de que Moodle cree el intento
     * (ver local_samce/consent_gate); si su navegador no es admitido, le avisa
     * ahí mismo en vez de dejarlo empezar.
     *
     * Solo para quien puede rendir y no es docente: un usuario con el permiso de
     * vista previa (docentes, administradores) no ve ningún aviso.
     */
    private static function load_start_gate(): void {
        global $DB, $PAGE, $USER;

        if (!isloggedin() || isguestuser() || !get_config('local_samce', 'capture_enabled') || !$PAGE->cm) {
            return;
        }
        $secret = get_config('local_samce', 'launchsecret');
        $eventsurl = event_batch::events_url((string) get_config('local_samce', 'backendurl'));
        if (empty($secret) || $eventsurl === '') {
            return;
        }
        $context = $PAGE->context;
        if (!has_capability('mod/quiz:attempt', $context) || has_capability('mod/quiz:preview', $context)) {
            return;
        }

        $restrict = get_config('local_samce', 'restrict_browser');
        if (($restrict === false || (string) $restrict !== '0') &&
                !browser_check::is_supported((string) \core_useragent::get_user_agent_string())) {
            $PAGE->requires->js_call_amd('local_samce/blocked', 'init', [[
                'title'   => get_string('browserblockedtitle', 'local_samce'),
                'body'    => get_string('browserblockedbody', 'local_samce'),
                'back'    => get_string('browserblockedback', 'local_samce'),
                'backurl' => (new \moodle_url('/course/view.php', ['id' => $PAGE->course->id]))->out(false),
            ]]);
            return;
        }

        // El intento en curso del alumno, si tiene uno: retomarlo no vuelve a
        // pedir el consentimiento en el navegador donde ya aceptó.
        $unfinished = $DB->get_field_select('quiz_attempts', 'id',
            'quiz = :quiz AND userid = :userid AND state = :state AND preview = 0',
            ['quiz' => $PAGE->cm->instance, 'userid' => $USER->id, 'state' => 'inprogress']);

        $PAGE->requires->js_call_amd('local_samce/consent_gate', 'init', [[
            'cmid'                => (int) $PAGE->cm->id,
            'unfinishedattemptid' => $unfinished ? (int) $unfinished : 0,
            'noticetitle'         => get_string('consentnoticetitle', 'local_samce'),
            'noticebody'          => self::notice_body(),
            'noticeaccept'        => get_string('consentaccept', 'local_samce'),
            'noticedecline'       => get_string('consentdecline', 'local_samce'),
        ]]);
    }

    /**
     * El texto del aviso, con el responsable de la base y el plazo de
     * conservación que el administrador configuró (o, si no configuró nada, un
     * texto que dice honestamente que todavía no están definidos).
     */
    private static function notice_body(): string {
        $a = new \stdClass();
        $controller = trim((string) get_config('local_samce', 'controllercontact'));
        $a->controller = $controller !== '' ? $controller : get_string('controllerdefault', 'local_samce');
        $retention = trim((string) get_config('local_samce', 'retentionnotice'));
        $a->retention = $retention !== '' ? $retention : get_string('retentiondefault', 'local_samce');

        return get_string('consentnoticebody', 'local_samce', $a);
    }
}
