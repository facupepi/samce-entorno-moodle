<?php
/**
 * Callbacks de Moodle para local_samce.
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Agrega el link al panel de supervisión SAMCE en la navegación del curso,
 * visible únicamente para quien tenga local/samce:viewpanel en ese
 * contexto (docentes del curso). Moodle llama a esta función
 * automáticamente por convención de nombre — no requiere registro en
 * db/hooks.php.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_samce_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if (!has_capability('local/samce:viewpanel', $context)) {
        return;
    }

    $url = new moodle_url('/local/samce/launch.php', ['courseid' => $course->id]);

    $navigation->add(
        get_string('viewpanel', 'local_samce'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_samce_launch',
        new pix_icon('i/report', '')
    );
}

/**
 * Agrega el link al panel general SAMCE (todos los cursos donde el usuario
 * tiene local/samce:viewpanel) a la navegación global, para que el docente
 * pueda acceder sin depender de estar parado en un curso puntual. Moodle
 * llama a esta función automáticamente por convención de nombre, igual que
 * local_samce_extend_navigation_course().
 *
 * get_user_capability_course() resuelve de una sola consulta en qué cursos
 * tiene la capability, así que no hace falta guardar en ningún lado la
 * relación docente-curso: Moodle sigue siendo la única fuente de verdad.
 *
 * @param global_navigation $navigation
 */
function local_samce_extend_navigation(global_navigation $navigation) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $courses = get_user_capability_course('local/samce:viewpanel', $USER->id, true, 'shortname,fullname');
    if (empty($courses)) {
        return;
    }

    $url = new moodle_url('/local/samce/launch_global.php');

    $navigation->add(
        get_string('viewpanelgeneral', 'local_samce'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_samce_launch_global',
        new pix_icon('i/report', '')
    );
}
