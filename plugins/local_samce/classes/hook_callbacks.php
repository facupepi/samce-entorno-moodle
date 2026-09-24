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

            // Con JavaScript apagado el navegador pide nojs.php: oculta el examen y avisa al
            // servidor que esta página se cargó sin captura (ver capture_watch).
            $hook->add_html('<noscript><link rel="stylesheet" href="' . (new \moodle_url('/local/samce/nojs.php', [
                'attempt' => (int) $attempt->id, 'sesskey' => sesskey(),
            ]))->out(true) . '"></noscript>');

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
            // Solo si el servidor ya tiene la constancia de ese intento: si no, retomarlo
            // exige aceptar el aviso de nuevo (la puerta del servidor lo frena).
            'unfinishedattemptid' => ($unfinished && get_user_preferences(
                \local_samce\external\accept_notice::PREFERENCE_PREFIX . $unfinished, null) !== null)
                ? (int) $unfinished : 0,
            'noticetitle'         => get_string('consentnoticetitle', 'local_samce'),
            'noticebody'          => self::notice_body(),
            'noticeaccept'        => get_string('consentaccept', 'local_samce'),
            'noticedecline'       => get_string('consentdecline', 'local_samce'),
            'accepterror'         => get_string('consentaccepterror', 'local_samce'),
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
        // El ajuste es la cantidad de días (por ejemplo, 90): el texto para el alumno lo
        // arma el plugin. Un valor que no es un número se muestra tal cual.
        $retention = trim((string) get_config('local_samce', 'retentionnotice'));
        if ($retention !== '' && ctype_digit($retention) && (int) $retention > 0) {
            $days = (int) $retention;
            $a->retention = get_string($days === 1 ? 'retentionday' : 'retentiondays', 'local_samce', $days);
        } else if ($retention !== '') {
            $a->retention = $retention;
        } else {
            $a->retention = get_string('retentiondefault', 'local_samce');
        }

        return get_string('consentnoticebody', 'local_samce', $a);
    }

    /**
     * La puerta del servidor. Corre en cada pedido, apenas Moodle terminó de
     * armar la configuración y ya con el usuario identificado, y frena a un
     * alumno que quiere comenzar o seguir un examen monitoreado si:
     * - no consta en el servidor que vio el aviso de monitoreo, o
     * - su navegador no es Chrome de escritorio (con el ajuste encendido).
     *
     * Es lo que impide saltear el aviso: el cartel de JavaScript se puede borrar
     * con las herramientas del navegador o desactivando JavaScript, pero el
     * servidor no crea el intento ni entrega las preguntas sin la constancia, y
     * esa constancia solo se genera pasando por el aviso.
     *
     * Barato para todo lo que no es un examen: lo primero que mira es el script.
     * Si algo falla del lado del plugin la puerta deja pasar y lo registra: un
     * error nuestro no puede dejar a todos los alumnos sin rendir. Se apaga
     * desactivando capture_enabled.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $DB, $USER;

        $redirect = null;
        try {
            if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
                return;
            }
            $kind = gate_policy::kind_of_script((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            if ($kind === null || !isloggedin() || isguestuser()) {
                return;
            }
            if (!get_config('local_samce', 'capture_enabled')) {
                return;
            }
            $secret = get_config('local_samce', 'launchsecret');
            if (empty($secret) || event_batch::events_url((string) get_config('local_samce', 'backendurl')) === '') {
                return;
            }

            // Qué examen (y, si corresponde, qué intento).
            $attempt = null;
            if ($kind === 'start') {
                $cmid = optional_param('cmid', 0, PARAM_INT);
                $cm = $cmid > 0 ? get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING) : false;
            } else {
                $attemptid = optional_param('attempt', 0, PARAM_INT);
                $attempt = $attemptid > 0 ? $DB->get_record('quiz_attempts', ['id' => $attemptid],
                    'id, quiz, userid, state, preview, timestart') : false;
                if (!$attempt || (int) $attempt->userid !== (int) $USER->id ||
                        $attempt->state !== 'inprogress' || !empty($attempt->preview)) {
                    return;
                }
                $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, IGNORE_MISSING);
            }
            if (!$cm) {
                return;
            }

            // Solo a quien rinde: docentes y administradores (vista previa) no.
            $context = \context_module::instance($cm->id);
            if (!has_capability('mod/quiz:attempt', $context) || has_capability('mod/quiz:preview', $context)) {
                return;
            }

            $restrict = get_config('local_samce', 'restrict_browser');
            $restrict = ($restrict === false || (string) $restrict !== '0');
            $browserok = browser_check::is_supported((string) \core_useragent::get_user_agent_string());

            $prefix = \local_samce\external\accept_notice::PREFERENCE_PREFIX;
            $startprefix = \local_samce\external\accept_notice::START_PREFERENCE_PREFIX;
            $startauth = get_user_preferences($startprefix . $cm->id, null);
            $startauth = $startauth === null ? null : (int) $startauth;

            $attemptnoticed = false;
            $unfinishednoticed = false;
            if ($attempt) {
                $attemptnoticed = get_user_preferences($prefix . $attempt->id, null) !== null;
            } else {
                $unfinished = $DB->get_field_select('quiz_attempts', 'id',
                    'quiz = :quiz AND userid = :userid AND state = :state AND preview = 0',
                    ['quiz' => $cm->instance, 'userid' => $USER->id, 'state' => 'inprogress']);
                $unfinishednoticed = $unfinished && get_user_preferences($prefix . $unfinished, null) !== null;
            }

            $verdict = gate_policy::verdict($kind, $restrict, $browserok, $attemptnoticed, $startauth,
                (bool) $unfinishednoticed, time());

            if ($verdict === gate_policy::BIND || $verdict === gate_policy::ALLOW) {
                if ($verdict === gate_policy::BIND) {
                    set_user_preference($prefix . $attempt->id, time());
                }
                // Cada entrega de una página del intento: si la anterior corrió sin que la
                // captura arrancara, se avisa; y se anota esta.
                if ($attempt) {
                    capture_watch::check_previous_page((int) $USER->id, (int) $attempt->id);
                    capture_watch::mark_delivered((int) $USER->id, (int) $attempt->id);
                }
            } else if ($verdict === gate_policy::NOTICE) {
                $redirect = [$cm->id, get_string('gatenoticerequired', 'local_samce')];
            } else if ($verdict === gate_policy::BROWSER) {
                $redirect = [$cm->id, get_string('gatebrowserrequired', 'local_samce')];
            }
        } catch (\Throwable $e) {
            debugging('local_samce: la puerta del servidor falló y deja pasar: ' . $e->getMessage(), DEBUG_NORMAL);
            return;
        }

        if ($redirect !== null) {
            redirect(new \moodle_url('/mod/quiz/view.php', ['id' => $redirect[0]]), $redirect[1], null,
                \core\output\notification::NOTIFY_ERROR);
        }
    }
}
