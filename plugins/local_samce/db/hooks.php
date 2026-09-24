<?php
/**
 * Hooks de Moodle a los que se engancha local_samce.
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    // La puerta del servidor: frena, antes de armar la página, a quien quiere
    // comenzar o seguir un examen monitoreado sin haber visto el aviso o con un
    // navegador no admitido. Ver hook_callbacks::after_config.
    [
        'hook'     => \core\hook\after_config::class,
        'callback' => \local_samce\hook_callbacks::class . '::after_config',
    ],
    [
        'hook'     => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \local_samce\hook_callbacks::class . '::before_standard_head_html_generation',
    ],
];
