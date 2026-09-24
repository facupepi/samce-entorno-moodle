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
$string['consentnoticetitle'] = 'Aviso de monitoreo durante el examen';
$string['consentnoticebody'] = 'Este examen utiliza SAMCE, un sistema que registra determinadas señales técnicas de tu interacción con el navegador mientras rendís, con el fin de asistir al proceso de evaluación académica.

Qué se registra: cambios de foco de ventana y de pestaña, actividad de mouse y teclado (sin el contenido de lo que escribís), uso del portapapeles (solo la cantidad de caracteres copiados o pegados, nunca el texto), cambios de tamaño de ventana y de pantalla completa, y el tiempo dedicado a cada pregunta. No se registra el contenido de tus respuestas, ni se activa la cámara, el micrófono, ni ningún otro dispositivo.

Quién accede: únicamente el/la docente responsable de este curso, a través de un panel de supervisión.

Marco legal: este tratamiento de datos se realiza en el marco de la Ley N.° 25.326 de Protección de los Datos Personales.

Para comenzar este examen es necesario aceptar este aviso.';
$string['consentaccept'] = 'Acepto';
$string['indicatortext'] = 'Este examen está siendo monitoreado';
