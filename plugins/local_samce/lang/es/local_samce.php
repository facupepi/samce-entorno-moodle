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
$string['viewpanelgeneral'] = 'Panel SAMCE (todos mis cursos)';
$string['launchsecret'] = 'Secreto compartido de lanzamiento';
$string['launchsecret_desc'] = 'Secreto usado para firmar el token que se envía al panel SAMCE cuando un docente lo lanza desde un curso. Tiene que coincidir exactamente con MOODLE_LAUNCH_SECRET en samce-backend.';
$string['panelurl'] = 'URL de callback del panel docente';
$string['panelurl_desc'] = 'URL completa del callback de autenticación del panel docente SAMCE, adonde se envía el token de lanzamiento firmado como parámetro.';
$string['missingsecret'] = 'Todavía no se configuró el secreto de lanzamiento de local_samce (Administración del sitio > Plugins > Plugins locales > SAMCE).';
$string['missingpanelurl'] = 'Todavía no se configuró la URL del panel docente en local_samce (Administración del sitio > Plugins > Plugins locales > SAMCE).';
$string['nocoursesavailable'] = 'No hay ningún curso donde tengas el rol de docente con acceso al panel SAMCE.';
$string['backendurl'] = 'URL de eventos del backend';
$string['backendurl_desc'] = 'URL completa del endpoint de samce-backend que recibe los eventos de intento de examen (POST /sessions/moodle-event). Se usa servidor a servidor, el alumno nunca la ve. La URL del endpoint de eventos de interacción (POST /sessions/moodle-events) se deriva de esta, así que tiene que terminar en /sessions/moodle-event.';
$string['captureenabled'] = 'Capturar los eventos de interacción del alumno';
$string['captureenabled_desc'] = 'Si está activada, durante un intento de examen se registran señales técnicas de la interacción del alumno (cambios de foco, cadencia de tecleo sin contenido, movimiento del mouse, portapapeles, pantalla completa y tamaño de ventana) y se envían a SAMCE. Si está desactivada, no se registra ni se envía ningún evento.';
