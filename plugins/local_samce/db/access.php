<?php
/**
 * Capacidades de local_samce.
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Controla quién ve el link al panel de supervisión SAMCE dentro del
    // curso y puede efectivamente lanzar una sesión hacia el panel. Se
    // otorga por defecto a los arquetipos docentes; el link no aparece para
    // nadie más y launch.php la vuelve a chequear del lado del servidor.
    'local/samce:viewpanel' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
