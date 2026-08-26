<?php
/**
 * Observers de eventos de Moodle para local_samce (HU02, SAMCE-8).
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_started',
        'callback'  => '\local_samce\observer::quiz_attempt_started',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\local_samce\observer::quiz_attempt_submitted',
    ],
];
