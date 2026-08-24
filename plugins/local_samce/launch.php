<?php
/**
 * Punto de lanzamiento del panel docente SAMCE desde dentro de un curso de
 * Moodle. El docente llega acá desde el link que agrega
 * local_samce_extend_navigation_course(); nunca se accede escribiendo la
 * URL a mano sin estar ya en una sesión válida de Moodle.
 *
 * require_login() se encarga de mandar a login si hace falta, y
 * require_capability() confirma el rol docente en este curso puntual antes
 * de emitir nada.
 *
 * @package local_samce
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/samce/classes/token_signer.php');

use local_samce\token_signer;

// Vigencia del token de lanzamiento: viaja en la URL del redirect, así que
// se mantiene deliberadamente corta. La sesión de verdad para el panel la
// emite el backend después de validar este token (ver
// samce-backend/src/api/core/auth/session_token.go).
const LAUNCH_TOKEN_TTL_SECONDS = 60;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/samce:viewpanel', $context);

$secret = get_config('local_samce', 'launchsecret');
if (empty($secret)) {
    throw new moodle_exception('missingsecret', 'local_samce');
}

$panelurl = get_config('local_samce', 'panelurl');
if (empty($panelurl)) {
    throw new moodle_exception('missingpanelurl', 'local_samce');
}

$now = time();
$claims = [
    'moodle_user_id' => (int) $USER->id,
    'username'       => $USER->username,
    'course_id'      => (int) $course->id,
    'role'           => 'docente',
    'iat'            => $now,
    'exp'            => $now + LAUNCH_TOKEN_TTL_SECONDS,
];

$token = token_signer::sign($claims, $secret);

redirect(new moodle_url($panelurl, ['token' => $token]));
