<?php
/**
 * English strings for local_samce.
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SAMCE';
$string['samce:viewpanel'] = 'View the SAMCE monitoring panel and launch it for a course';
$string['viewpanel'] = 'SAMCE monitoring panel';
$string['viewpanelgeneral'] = 'SAMCE panel (all my courses)';
$string['launchsecret'] = 'Launch shared secret';
$string['launchsecret_desc'] = 'Shared secret used to sign the token sent to the SAMCE panel when a teacher launches it from a course. Must match MOODLE_LAUNCH_SECRET on samce-backend exactly.';
$string['panelurl'] = 'Teacher panel callback URL';
$string['panelurl_desc'] = 'Full URL of the SAMCE teacher dashboard\'s auth callback, where the signed launch token is sent as a query parameter.';
$string['missingsecret'] = 'The local_samce launch secret has not been configured yet (Site administration > Plugins > Local plugins > SAMCE).';
$string['missingpanelurl'] = 'The local_samce teacher panel URL has not been configured yet (Site administration > Plugins > Local plugins > SAMCE).';
$string['nocoursesavailable'] = 'There is no course where you have a teaching role with access to the SAMCE panel.';
$string['backendurl'] = 'Backend events URL';
$string['backendurl_desc'] = 'Full URL of the samce-backend endpoint that receives exam attempt events (POST /sessions/moodle-event). Used server-to-server, never seen by the student. The URL of the interaction events endpoint (POST /sessions/moodle-events) is derived from this one, so it must end in /sessions/moodle-event.';
$string['captureenabled'] = 'Capture student interaction events';
$string['captureenabled_desc'] = 'When enabled, technical signals of the student\'s interaction are recorded during an exam attempt (focus changes, typing cadence without content, mouse movement, clipboard, fullscreen and window size) and sent to SAMCE. When disabled, no event is recorded or sent.';
$string['consentnoticetitle'] = 'Monitoring notice for this exam';
$string['consentnoticebody'] = 'This exam uses SAMCE, a system that records certain technical signals of your interaction with the browser while you take it, to assist the academic evaluation process.

What is recorded: window focus and tab visibility changes, mouse and keyboard activity (never what you type), clipboard use (only the number of characters copied or pasted, never the text), window size and fullscreen changes, and the time spent on each question. The content of your answers is never recorded, and no camera, microphone or other device is activated.

Who can access it: only the teacher in charge of this course, through a monitoring panel.

Legal basis: this data processing takes place under Argentine Law No. 25,326 on Personal Data Protection.

You need to accept this notice to start this exam.';
$string['consentaccept'] = 'I accept';
$string['indicatortext'] = 'This exam is being monitored';
$string['taskretryexamevent'] = 'Retry an exam event notice to SAMCE';
$string['taskretryexameventfailed'] = 'Could not retry the exam event notice to SAMCE';
