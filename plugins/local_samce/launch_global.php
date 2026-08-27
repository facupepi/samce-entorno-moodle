<?php
/**
 * Punto de lanzamiento del panel docente SAMCE general: junta todos los
 * cursos donde el usuario tiene local/samce:viewpanel y arma un único token
 * con esa lista, para que el panel muestre un resumen de todos sus cursos
 * en vez de uno solo.
 *
 * A diferencia de launch.php, no depende de estar parado en un curso
 * puntual — por eso se engancha a la navegación global
 * (local_samce_extend_navigation() en lib.php) y no a la navegación de
 * curso. require_login() acá solo exige una sesión válida de Moodle, la
 * lista de cursos autorizados sale de get_user_capability_course(), que
 * consulta a Moodle en el momento sin persistir nada de esa relación en
 * ningún lado.
 *
 * @package local_samce
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/samce/classes/token_signer.php');

use local_samce\token_signer;

// Mismo TTL y mismo razonamiento que launch.php: el token viaja en la URL
// de un redirect, así que se mantiene deliberadamente corto.
const LAUNCH_TOKEN_TTL_SECONDS = 60;

require_login();

$secret = get_config('local_samce', 'launchsecret');
if (empty($secret)) {
    throw new moodle_exception('missingsecret', 'local_samce');
}

$panelurl = get_config('local_samce', 'panelurl');
if (empty($panelurl)) {
    throw new moodle_exception('missingpanelurl', 'local_samce');
}

$courses = get_user_capability_course('local/samce:viewpanel', $USER->id, true, 'shortname,fullname');
if (empty($courses)) {
    throw new moodle_exception('nocoursesavailable', 'local_samce');
}

$courseclaims = [];
foreach ($courses as $course) {
    $courseclaims[] = [
        'id'   => (int) $course->id,
        'name' => format_string($course->fullname),
    ];
}

$now = time();
$claims = [
    'moodle_user_id' => (int) $USER->id,
    'username'       => $USER->username,
    'display_name'   => fullname($USER),
    'role'           => 'docente',
    'courses'        => $courseclaims,
    'iat'            => $now,
    'exp'            => $now + LAUNCH_TOKEN_TTL_SECONDS,
];

$token = token_signer::sign($claims, $secret);

redirect(new moodle_url($panelurl, ['token' => $token]));
