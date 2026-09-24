<?php
/**
 * Hoja de estilos que el navegador pide SOLO si el JavaScript está apagado.
 *
 * La página del examen la enlaza desde un bloque <noscript>: con JavaScript
 * encendido el navegador ni la pide. Cuando la pide, hace dos cosas:
 * - oculta el examen y muestra un cartel que pide encender JavaScript, y
 * - le avisa al servidor (capture_watch) que esa página se cargó sin captura, para
 *   que el docente vea la marca "Sin captura".
 *
 * No bloquea de verdad: ocultar con CSS se puede saltear editando la página, y no
 * hay forma de impedir del lado del navegador que alguien apague el JavaScript. Lo
 * que importa es que quede a la vista.
 *
 * @package   local_samce
 */

define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    if (isloggedin() && !isguestuser() && confirm_sesskey()) {
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        $attempt = $attemptid > 0 ? $DB->get_record('quiz_attempts', ['id' => $attemptid],
            'id, userid, state, preview') : false;

        if ($attempt && (int) $attempt->userid === (int) $USER->id && $attempt->state === 'inprogress' &&
                empty($attempt->preview) && get_config('local_samce', 'capture_enabled') &&
                !empty(get_config('local_samce', 'launchsecret'))) {
            \local_samce\capture_watch::report((int) $USER->id, (int) $attempt->id,
                \local_samce\capture_watch::STATE_JS_DISABLED);

            $text = addcslashes(get_string('nojsblocked', 'local_samce'), "\\\"\r\n");
            echo "#page, #page-wrapper, #page-header, #region-main-box, [role=\"main\"], .drawer, .navbar { display: none !important; }\n";
            echo "body::before { content: \"{$text}\"; display: block; padding: 48px 24px; text-align: center; " .
                "font: 600 20px/1.5 -apple-system, BlinkMacSystemFont, sans-serif; color: #7a1f1f; }\n";
        }
    }
} catch (\Throwable $e) {
    // Una hoja de estilos vacía: nunca puede romper nada.
}
