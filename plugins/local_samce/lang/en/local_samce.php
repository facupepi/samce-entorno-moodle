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

What is recorded: window focus and tab visibility changes, mouse and keyboard activity (never what you type), clipboard use (only the number of characters copied or pasted, never the text), window size and fullscreen changes, the time spent on each question, connection drops and recoveries, and technical details of your device: your browser family and major version, and whether the device is touch-capable. The content of your answers is never recorded, and no camera, microphone or other device is activated.

Who can access it: only the teacher in charge of this course, through a monitoring panel.

Legal basis: this data processing takes place under Argentine Law No. 25,326 on Personal Data Protection.

You need to accept this notice to start this exam.';
$string['consentaccept'] = 'I accept';
$string['consentdecline'] = 'I do not accept';
$string['indicatortext'] = 'This exam is being monitored';
$string['taskretryexamevent'] = 'Retry an exam event notice to SAMCE';
$string['taskretryexameventfailed'] = 'Could not retry the exam event notice to SAMCE';
$string['privacy:metadata:samce_backend'] = 'The SAMCE monitoring system (samce-backend), to which the plugin sends, server to server, the start and submission of each exam attempt and the technical signals of the student\'s interaction during the attempt.';
$string['privacy:metadata:samce_backend:attemptid'] = 'The exam attempt identifier.';
$string['privacy:metadata:samce_backend:userid'] = 'The student\'s Moodle identifier (stored encrypted).';
$string['privacy:metadata:samce_backend:fullname'] = 'The student\'s full name (stored encrypted).';
$string['privacy:metadata:samce_backend:courseid'] = 'The course identifier.';
$string['privacy:metadata:samce_backend:quizid'] = 'The quiz identifier and name, its time limit and its number of questions.';
$string['privacy:metadata:samce_backend:timestamps'] = 'The time the attempt started and was submitted or abandoned.';
$string['privacy:metadata:samce_backend:interaction'] = 'Technical signals of the interaction with the browser during the attempt: focus and visibility changes, typing cadence and mouse movement without content, number of characters copied or pasted (never the text), window size and fullscreen, time per question, connection state, browser family and major version, whether the device is touch-capable, and the acceptance of the monitoring notice.';
$string['restrictbrowser'] = 'Require Google Chrome on a computer';
$string['restrictbrowser_desc'] = 'When enabled, exams with event capture can only be taken with Google Chrome on a computer. With another browser, or from a phone or tablet, the student sees a notice and cannot take the exam. Note: this is decided from the User-Agent, which can be spoofed, and Brave and other Chromium-based browsers identify themselves as Chrome.';
$string['browserblockedtitle'] = 'Unsupported browser';
$string['browserblockedbody'] = 'This exam can only be taken with Google Chrome on a computer (not on a phone or tablet, and not with another browser).

Open this exam in Google Chrome from a computer to take it.';
$string['browserblockedback'] = 'Back';
