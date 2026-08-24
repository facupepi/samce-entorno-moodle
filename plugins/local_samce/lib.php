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
