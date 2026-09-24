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
$string['consentnoticebody'] = 'Este examen se rinde con SAMCE, un sistema de monitoreo que registra señales técnicas de tu interacción con el navegador mientras rendís, para asistir al proceso de evaluación académica. Este aviso es previo e informativo y es condición para rendir este examen: la institución realiza este tratamiento en ejercicio de su función evaluativa, conforme a la Ley N.° 25.326 de Protección de los Datos Personales.

Qué se registra: tu identificación en el campus (identificador y nombre y apellido), el curso, el examen y la hora de inicio y de entrega del intento. Durante el examen: cambios de foco de ventana y de pestaña, actividad de mouse y teclado (sin el contenido de lo que escribís), uso del portapapeles (solo la cantidad de caracteres copiados o pegados, nunca el texto), cambios de tamaño de ventana y de pantalla completa, el tiempo dedicado a cada pregunta, los cortes y las recuperaciones de la conexión, y datos técnicos de tu equipo: la familia y la versión mayor del navegador, si el dispositivo es táctil y si usa un puntero fino (mouse) o táctil. No se registra el contenido de tus respuestas, ni se activa la cámara, el micrófono, ni ningún otro dispositivo.

Adónde van los datos: salen de Moodle hacia SAMCE, un servicio externo alojado en servidores en la nube, y se guardan con tu identidad cifrada.

Quién accede: únicamente el/la docente responsable de este curso, a través de un panel de supervisión.

Responsable de la base de datos: {$a->controller}

Cuánto tiempo se conservan: {$a->retention}

Tus derechos: podés ejercer los derechos de acceso, rectificación y supresión (artículos 14 a 16 de la Ley N.° 25.326) dirigiéndote al responsable indicado arriba. La Agencia de Acceso a la Información Pública es el órgano de control de esa ley.

Para comenzar este examen es necesario haber leído este aviso.';
$string['consentaccept'] = 'Leí el aviso y continúo';
$string['consentdecline'] = 'Volver';
$string['indicatortext'] = 'Este examen está siendo monitoreado';
$string['taskretryexamevent'] = 'Reintentar un aviso de examen a SAMCE';
$string['taskretryexameventfailed'] = 'No se pudo reintentar el aviso de examen a SAMCE';
$string['privacy:metadata:samce_backend'] = 'El sistema de monitoreo SAMCE (samce-backend), al que el complemento envía, servidor a servidor, el inicio y la entrega de cada intento de examen y las señales técnicas de interacción del alumno durante el intento.';
$string['privacy:metadata:samce_backend:attemptid'] = 'El identificador del intento de examen.';
$string['privacy:metadata:samce_backend:userid'] = 'El identificador del alumno en Moodle (se guarda cifrado).';
$string['privacy:metadata:samce_backend:fullname'] = 'El nombre completo del alumno (se guarda cifrado).';
$string['privacy:metadata:samce_backend:courseid'] = 'El identificador del curso.';
$string['privacy:metadata:samce_backend:quizid'] = 'El identificador y el nombre del cuestionario, su límite de tiempo y su cantidad de preguntas.';
$string['privacy:metadata:samce_backend:timestamps'] = 'La hora de inicio y de entrega o abandono del intento.';
$string['privacy:metadata:samce_backend:interaction'] = 'Señales técnicas de la interacción con el navegador durante el intento: cambios de foco y de visibilidad, cadencia de tecleo y movimiento del mouse sin el contenido, cantidad de caracteres copiados o pegados (nunca el texto), tamaño de ventana y pantalla completa, tiempo por pregunta, estado de la conexión, familia y versión mayor del navegador, si el dispositivo es táctil, y la aceptación del aviso de monitoreo.';
$string['restrictbrowser'] = 'Exigir Google Chrome en una computadora';
$string['restrictbrowser_desc'] = 'Si está activada, los exámenes con monitoreo solo pueden rendirse con Google Chrome desde una computadora. Con otro navegador, o desde un teléfono o una tablet, el alumno recibe un aviso y no puede comenzar el examen. Nota: la restricción no distingue a Google Chrome de otros navegadores basados en el mismo motor (por ejemplo, Brave), que también serán admitidos, y no constituye un control infalible.';
$string['browserblockedtitle'] = 'Navegador no admitido';
$string['browserblockedbody'] = 'Este examen se rinde únicamente con Google Chrome, en una computadora (no en un teléfono ni en una tablet, y no con otro navegador).

Abrí este examen en Google Chrome desde una computadora para poder rendirlo.';
$string['browserblockedback'] = 'Volver';
$string['controllercontact'] = 'Responsable de la base de datos (para el aviso)';
$string['controllercontact_desc'] = 'Quién es el responsable de la base de datos de SAMCE y cómo contactarlo (nombre de la institución y un correo o un enlace). Se le muestra al alumno en el aviso previo al examen, junto con cómo ejercer sus derechos de acceso, rectificación y supresión (Ley 25.326, arts. 6 y 14 a 16). Si queda vacío, el aviso dice que todavía no está definido.';
$string['retentionnotice'] = 'Plazo de conservación de los datos (en días)';
$string['retentionnotice_desc'] = 'Cantidad de días que se conservan los datos, contados desde la finalización del examen (por ejemplo: 90). Se le informa al alumno en el aviso previo al examen. Este ajuste solo informa: para que los datos se borren efectivamente, el mismo plazo tiene que estar configurado en el servidor de SAMCE.';
$string['controllerdefault'] = 'la institución educativa; todavía no se definieron los datos de contacto: consultalos con tu docente';
$string['retentiondefault'] = 'el plazo todavía no está definido; hasta entonces los datos se conservan sin supresión automática';
$string['privacy:preference:notice'] = 'La hora en que viste el aviso de monitoreo de un intento de examen.';
$string['gatenoticerequired'] = 'Antes de rendir este examen tenés que leer el aviso de monitoreo. Tocá «Comenzar intento» para verlo y continuar.';
$string['gatebrowserrequired'] = 'Este examen se rinde únicamente con Google Chrome desde una computadora. Abrilo con Google Chrome desde una computadora para poder rendirlo.';
$string['consentaccepterror'] = 'No se pudo registrar la lectura del aviso. Revisá tu conexión y volvé a intentarlo.';
$string['privacy:preference:start'] = 'La hora en que aceptaste el aviso de monitoreo para comenzar un intento de examen.';
$string['retentiondays'] = '{$a} días desde la finalización del examen';
$string['retentionday'] = '1 día desde la finalización del examen';
$string['nojsblocked'] = 'Este examen requiere JavaScript habilitado. Activalo en tu navegador y recargá la página.';
$string['tasksendcapturestatus'] = 'Avisar a SAMCE que una página del examen se cargó sin captura';
$string['tasksendcapturestatusfailed'] = 'No se pudo avisar a SAMCE que una página del examen se cargó sin captura';
$string['privacy:preference:page'] = 'La hora en que se entregó la última página de un intento de examen (para saber si la captura arrancó).';
$string['privacy:preference:seen'] = 'La hora en que arrancó la captura de eventos en la última página de un intento de examen.';
$string['privacy:preference:alert'] = 'La hora en que se avisó a SAMCE que una página de un intento de examen se cargó sin captura.';
