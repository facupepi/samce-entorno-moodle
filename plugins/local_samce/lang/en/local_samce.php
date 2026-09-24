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
$string['consentnoticebody'] = 'This exam is taken with SAMCE, a monitoring system that records technical signals of your interaction with the browser while you take it, to assist the academic evaluation process. This notice is informative and given in advance, and reading it is a condition to take this exam: the institution carries out this processing in the exercise of its evaluative function, under Argentine Law No. 25,326 on Personal Data Protection.

What is recorded: your identification on the campus (identifier and full name), the course, the exam, and the time the attempt started and was submitted. During the exam: window focus and tab visibility changes, mouse and keyboard activity (never what you type), clipboard use (only the number of characters copied or pasted, never the text), window size and fullscreen changes, the time spent on each question, connection drops and recoveries, and technical details of your device: your browser family and major version, whether the device is touch-capable, and whether it uses a fine pointer (mouse) or touch. The content of your answers is never recorded, and no camera, microphone or other device is activated.

Where the data goes: it leaves Moodle for SAMCE, an external service hosted on cloud servers, and is stored with your identity encrypted.

Who can access it: only the teacher in charge of this course, through a monitoring panel.

Data controller: {$a->controller}

How long it is kept: {$a->retention}

Your rights: you can exercise your rights of access, rectification and deletion (articles 14 to 16 of Law No. 25,326) by contacting the controller above. The Agency for Access to Public Information is the oversight body of that law.

You need to have read this notice to start this exam.';
$string['consentaccept'] = 'I have read the notice and continue';
$string['consentdecline'] = 'Back';
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
$string['restrictbrowser_desc'] = 'When enabled, monitored exams can only be taken with Google Chrome on a computer. With another browser, or from a phone or tablet, the student receives a notice and cannot start the exam. Note: the restriction does not tell Google Chrome apart from other browsers built on the same engine (for example, Brave), which will also be admitted, and it is not an infallible control.';
$string['browserblockedtitle'] = 'Unsupported browser';
$string['browserblockedbody'] = 'This exam can only be taken with Google Chrome on a computer (not on a phone or tablet, and not with another browser).

Open this exam in Google Chrome from a computer to take it.';
$string['browserblockedback'] = 'Back';
$string['controllercontact'] = 'Data controller (for the notice)';
$string['controllercontact_desc'] = 'Who is the controller of the SAMCE database and how to contact them (institution name and an email or link). It is shown to the student in the notice before the exam, along with how to exercise the rights of access, rectification and deletion (Law 25,326, arts. 6 and 14 to 16). If left empty, the notice says it has not been defined yet.';
$string['retentionnotice'] = 'Retention period (for the notice)';
$string['retentionnotice_desc'] = 'How long the data is kept, worded for the student (for example: "90 days after the exam is finished"). Shown in the notice before the exam. This setting only informs: the backend performs the actual deletion (RETENTION_DAYS variable).';
$string['controllerdefault'] = 'the educational institution; contact details have not been defined yet: ask your teacher';
$string['retentiondefault'] = 'the period has not been defined yet; until then the data is kept without automatic deletion';
$string['privacy:preference:notice'] = 'The time you saw the monitoring notice of an exam attempt.';
