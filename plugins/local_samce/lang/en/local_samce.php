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
$string['launchsecret'] = 'Launch shared secret';
$string['launchsecret_desc'] = 'Shared secret used to sign the token sent to the SAMCE panel when a teacher launches it from a course. Must match MOODLE_LAUNCH_SECRET on samce-backend exactly.';
$string['panelurl'] = 'Teacher panel callback URL';
$string['panelurl_desc'] = 'Full URL of the SAMCE teacher dashboard\'s auth callback, where the signed launch token is sent as a query parameter.';
$string['missingsecret'] = 'The local_samce launch secret has not been configured yet (Site administration > Plugins > Local plugins > SAMCE).';
$string['missingpanelurl'] = 'The local_samce teacher panel URL has not been configured yet (Site administration > Plugins > Local plugins > SAMCE).';
$string['backendurl'] = 'Backend events URL';
$string['backendurl_desc'] = 'Full URL of the samce-backend endpoint that receives exam attempt events (POST /sessions/moodle-event). Used server-to-server, never seen by the student.';
