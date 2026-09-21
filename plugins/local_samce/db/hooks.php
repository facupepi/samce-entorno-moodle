<?php
/**
 * Hooks de Moodle a los que se engancha local_samce.
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \local_samce\hook_callbacks::class . '::before_standard_head_html_generation',
    ],
];
