<?php
/**
 * Cadenas en español de local_samce.
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SAMCE';
$string['samce:viewpanel'] = 'Ver el panel de supervisión SAMCE y lanzarlo para un curso';
$string['viewpanel'] = 'Panel de supervisión SAMCE';
$string['launchsecret'] = 'Secreto compartido de lanzamiento';
$string['launchsecret_desc'] = 'Secreto usado para firmar el token que se envía al panel SAMCE cuando un docente lo lanza desde un curso. Tiene que coincidir exactamente con MOODLE_LAUNCH_SECRET en samce-backend.';
$string['panelurl'] = 'URL de callback del panel docente';
$string['panelurl_desc'] = 'URL completa del callback de autenticación del panel docente SAMCE, adonde se envía el token de lanzamiento firmado como parámetro.';
$string['missingsecret'] = 'Todavía no se configuró el secreto de lanzamiento de local_samce (Administración del sitio > Plugins > Plugins locales > SAMCE).';
$string['missingpanelurl'] = 'Todavía no se configuró la URL del panel docente en local_samce (Administración del sitio > Plugins > Plugins locales > SAMCE).';
