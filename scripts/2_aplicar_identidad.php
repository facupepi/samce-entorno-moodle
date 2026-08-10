<?php
/**
 * Aplica al Moodle local la identidad visual del Campus Virtual de la FRSFCO.
 *
 * Reemplaza a los nueve scripts sueltos que se fueron escribiendo durante el
 * relevamiento: aquí está el estado final consolidado. Es idempotente, se puede
 * ejecutar las veces que haga falta.
 *
 * Requiere que las imágenes estén en /tmp/imagenes_cvg dentro del contenedor.
 * Uso:
 *   docker cp .\imagenes moodle-docker-webserver-1:/tmp/imagenes_cvg
 *   docker cp .\2_aplicar_identidad.php moodle-docker-webserver-1:/tmp/
 *   docker exec moodle-docker-webserver-1 php /tmp/2_aplicar_identidad.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->dirroot . '/course/lib.php');

$contexto = context_system::instance();
$fs = get_file_storage();
$origen = '/tmp/imagenes_cvg';

if (!is_dir($origen)) {
    fwrite(STDERR, "ERROR: no se encuentra $origen dentro del contenedor.\n"
        . "Copiá primero la carpeta de imágenes:\n"
        . "  docker cp .\\imagenes moodle-docker-webserver-1:/tmp/imagenes_cvg\n");
    exit(1);
}

// ---------------------------------------------------------------------------
echo "=== 1. IMÁGENES ===\n";
// componente, área, archivo de origen, nombre final, ajuste que lo referencia
$imagenes = [
    ['theme_adaptable', 'logo',                      'logo-utn-siglas.png.png', 'logo-utn-siglas.png.png', 'logo'],
    ['theme_adaptable', 'p1',                        'slide1_UTN-05.jpg',       'UTN-05.jpg',              'p1'],
    ['theme_adaptable', 'p2',                        'slide2_utn-fondo2.jpg',   'utn-fondo2.jpg',          'p2'],
    ['theme_adaptable', 'p3',                        'slide3_utn-fondo3.jpg',   'utn-fondo3.jpg',          'p3'],
    ['theme_adaptable', 'adaptablemarkettingimages', 'logo_blanco.png',         'Logo_Blanco.png',         null],
    ['theme_adaptable', 'adaptablemarkettingimages', 'ConsultasWhatsApp.png',   'ConsultasWhatsApp.png',   null],
    ['core_admin',      'logocompact',               'logocompacto.png',        'Logotipocompacto.png',    'logocompact'],
    ['core_admin',      'favicon',                   'favicon.ico',             'logo_favicon.ico',        'favicon'],
];

foreach ($imagenes as [$componente, $area, $archivoOrigen, $nombreFinal, $ajuste]) {
    $ruta = "$origen/$archivoOrigen";
    if (!file_exists($ruta)) {
        printf("  %-40s NO ENCONTRADO (%s)\n", "$componente/$area", $archivoOrigen);
        continue;
    }
    // Importante: borrar SOLO este archivo. Pasarle un filtro a delete_area_files()
    // vacía el área entera, porque su último parámetro es una ruta, no una condición.
    if ($previo = $fs->get_file($contexto->id, $componente, $area, 0, '/', $nombreFinal)) {
        $previo->delete();
    }
    $fs->create_file_from_pathname([
        'contextid' => $contexto->id, 'component' => $componente, 'filearea' => $area,
        'itemid' => 0, 'filepath' => '/', 'filename' => $nombreFinal,
    ], $ruta);
    if ($ajuste) {
        set_config($ajuste, '/' . $nombreFinal, $componente === 'core_admin' ? 'core_admin' : 'theme_adaptable');
    }
    printf("  %-40s %s\n", "$componente/$area", $nombreFinal);
}

// ---------------------------------------------------------------------------
echo "\n=== 2. AJUSTES DEL TEMA ===\n";
// Cada valor fue verificado contra la hoja de estilos del campus.
// El nombre del ajuste que genera cada regla se encuentra en
// theme/adaptable/scss/settings/*.scss, leyendo el [[setting:X]] correspondiente.
$ajustes = [
    // Encabezado: son dos filas con ajustes distintos.
    'headertoprowbkcolour'       => '#008cb2',   // franja superior (idioma y acceso)
    'headertoprowtextcolour'     => '#ffffff',
    'headermainrowbkcolour'      => '#F2F2F0',   // franja del logotipo, gris casi blanco
    'headermainrowtextcolour'    => '#ffffff',
    'menubordercolor'            => '#008cb2',   // borde inferior de la barra de navegación
    'sitetitle'                  => 'disabled',  // el campus no muestra el nombre junto al logotipo
    'logoalt'                    => 'UTN - Facultad Regional San Francisco',

    // Colores generales
    'blockbordercolor'           => '#008cb2',
    'blockheaderbackgroundcolor' => '#e0f1f5',
    'linkhover'                  => '#008cb2',
    'selectionbackground'        => '#008cb2',
    'buttonlogincolor'           => '#ef5350',   // botón de acceso, distinto del primario
    'buttonloginhovercolor'      => '#e53935',
    'buttonlogintextcolor'       => '#FFF',

    // Tipografías
    'fontname'                   => 'Open Sans',
    'fontheadername'             => 'Roboto',
    'fonttitlename'              => 'Roboto Condensed',
    'googlefonts'                => '1',

    // Carrusel de la portada
    'sliderenabled'              => '1',
    'slidercount'                => '3',
    'slidervisible'              => '3',

    // Pie de página
    'footerbkcolor'              => '#114C5E',
    'footertextcolor'            => '#ffffff',
    'footerlinkcolor'            => '#ffffff',
    'footerlayoutrow1'           => '9-0-0-0',   // una sola columna ancha
    'footer1header'              => '',
    'showfooterboxes'            => '1',
    'hidefootersocial'           => '1',

    // Regiones de la portada. Sin frontpageblocksenabled, el tema solo declara
    // la región lateral y cualquier bloque cae en el panel colapsable.
    'frontpageblocksenabled'     => '1',
    'blocklayoutlayoutrow1'      => '4-4-4-0',   // tres columnas: la del medio queda centrada
    'blocklayoutlayoutrow2'      => '12-0-0-0',  // una región de ancho completo
];
foreach ($ajustes as $clave => $valor) {
    set_config($clave, $valor, 'theme_adaptable');
}
printf("  %d ajustes aplicados\n", count($ajustes));

// Ajustes que no existen en esta versión del tema y quedaron de pruebas previas
foreach (['headerbkcolor', 'headerbkcolor2'] as $obsoleto) {
    unset_config($obsoleto, 'theme_adaptable');
}

// El tema dibuja un control de mostrar/ocultar en cada bloque que el campus no tiene.
$css = (string) get_config('theme_adaptable', 'customcss');
if (strpos($css, 'block-collapsible') === false) {
    $css = trim($css . "\n/* El campus no muestra el control de mostrar/ocultar en los bloques. */\n"
        . ".block-collapsible { display: none !important; }\n");
    set_config('customcss', $css, 'theme_adaptable');
    echo "  CSS personalizado: se oculta el control de mostrar/ocultar de los bloques\n";
}

// ---------------------------------------------------------------------------
echo "\n=== 3. IDENTIDAD Y CONFIGURACIÓN DEL SITIO ===\n";
$sitio = get_site();
$registro = new stdClass();
$registro->id = $sitio->id;
$registro->shortname = 'FR_SFco';   // hace que el título del navegador coincida con el campus
$registro->summary = 'El Campus Virtual de la UTN es un Sistema de Gestión de Contenido Educativo el cual '
    . 'integra sustantivamente las nuevas tecnologías a los procesos educativos aprovechando las '
    . 'potencialidades que estas ofrecen. Nuestro propósito es que los estudiantes puedan encontrar '
    . 'respuestas a las inquietudes y necesidades académicas extendiendo las fronteras de espacio y tiempo.';
$registro->summaryformat = FORMAT_HTML;
$DB->update_record('course', $registro);
echo "  Nombre corto y descripción del sitio actualizados\n";

// Tipografías: el campus las carga con una etiqueta en la cabecera, no por el tema.
$cabecera = (string) get_config('core', 'additionalhtmlhead');
if (strpos($cabecera, 'fonts.googleapis.com') === false) {
    set_config('additionalhtmlhead', trim($cabecera . "\n"
        . '<link rel="stylesheet" type="text/css" '
        . 'href="https://fonts.googleapis.com/css?family=Montserrat:300i,400,600|Roboto:400,700">'));
    echo "  Tipografías Montserrat y Roboto agregadas a la cabecera\n";
}

// El campus no ofrece acceso como invitado ni registro de cuentas nuevas.
set_config('guestloginbutton', 0);
set_config('autologinguests', 0);
$metodos = array_values(array_filter(explode(',', (string) get_config('core', 'auth')),
    function ($a) { return trim($a) !== '' && trim($a) !== 'guest'; }));
set_config('auth', implode(',', $metodos));
echo "  Acceso como invitado desactivado\n";

// La portada no lista los cursos: queda el acceso, la franja de consultas y los horarios.
set_config('frontpage', '');
set_config('frontpageloggedin', '');
echo "  Lista de cursos retirada de la portada\n";

// ---------------------------------------------------------------------------
echo "\n=== 4. PIE DE PÁGINA ===\n";
$logopie = $CFG->wwwroot . '/pluginfile.php/' . $contexto->id
    . '/theme_adaptable/adaptablemarkettingimages/0/Logo_Blanco.png';
set_config('footer1content',
    '<p><img class="img-fluid" style="font-size:1rem;" src="' . $logopie
    . '" alt="UTN - Facultad Regional San Francisco" width="380"></p>'
    . '<h5><span style="color:#ffffff;">UTN | Universidad Tecnológica Nacional - Facultad Regional San Francisco </span></h5>'
    . '<p><span style="color:#ffffff;">Av. de la Universidad 501</span><br />'
    . '<span style="color:#ffffff;">(2400) San Francisco - Córdoba</span><br />'
    . '<span style="color:#ffffff;">Tel. (03564) 431019 / 435403</span><br /><br />'
    . '<span style="font-size:small; color:#ffffff;">Copyright © ' . date('Y')
    . ' Universidad Tecnológica Nacional</span></p>',
    'theme_adaptable');
echo "  Logotipo, nombre, dirección y teléfonos cargados\n";

// ---------------------------------------------------------------------------
echo "\n=== 5. BLOQUES DE LA PORTADA ===\n";
$imgwhatsapp = $CFG->wwwroot . '/pluginfile.php/' . $contexto->id
    . '/theme_adaptable/adaptablemarkettingimages/0/ConsultasWhatsApp.png';

$horarios = [
    ['Lic. en Administración Rural', 'https://sanfrancisco.utn.edu.ar/info/horarios-licenciatura-en-administracion-rural-7'],
    ['Tec. Universitaria en Programación', 'http://sanfrancisco.utn.edu.ar/info/horarios-tecnicatura-universitaria-en-programacion-8'],
    ['Ing. Química', 'https://sanfrancisco.utn.edu.ar/info/horarios-ingenieria-quimica-5'],
    ['Ing. en Sistemas de Informción', 'https://sanfrancisco.utn.edu.ar/info/horarios-ingenieria-en-sistemas-de-informacion-2'],
    ['Ing. Electromecánica', 'https://sanfrancisco.utn.edu.ar/info/horarios-ingenieria-electromecanica-4'],
    ['Ing. Electrónica', 'https://sanfrancisco.utn.edu.ar/info/horarios-ingenieria-electronica-3'],
    ['Ing. Industrial', 'https://sanfrancisco.utn.edu.ar/info/horarios-ingenieria-industrial-6'],
];
$listado = "<ul>\n";
foreach ($horarios as [$texto, $enlace]) {
    $listado .= '<li><a href="' . $enlace . '" target="_blank" rel="noopener">' . $texto . "</a></li>\n";
}
$listado .= '</ul>';

$deseados = [
    ['blockname' => 'login', 'region' => 'frnt-market-b', 'weight' => 0, 'title' => null, 'text' => null],
    ['blockname' => 'html', 'region' => 'frnt-market-d', 'weight' => 1, 'title' => '',
     'text' => '<p style="text-align:center;"><a title="https://wa.me/549680555" '
        . 'href="https://wa.me/+5493564680555" target="_blank" rel="noopener">'
        . '<img style="max-width:100%;height:auto;" class="img-fluid" role="presentation" src="'
        . $imgwhatsapp . '" alt="Consultas por WhatsApp" width="990" height="110"></a></p>'],
    ['blockname' => 'html', 'region' => 'frnt-market-d', 'weight' => 2, 'title' => 'HORARIOS 2°C 2026',
     'text' => $listado],
];

// Se parte de cero para que el resultado no dependa de ejecuciones anteriores.
$existentes = $DB->get_records_select('block_instances',
    "parentcontextid = ? AND pagetypepattern = 'site-index'", [$contexto->id]);
foreach ($existentes as $b) {
    blocks_delete_instance($b);
}

foreach ($deseados as $d) {
    $registro = new stdClass();
    $registro->blockname = $d['blockname'];
    $registro->parentcontextid = $contexto->id;
    $registro->showinsubcontexts = 0;
    $registro->requiredbytheme = 0;
    $registro->pagetypepattern = 'site-index';
    $registro->subpagepattern = null;
    $registro->defaultregion = $d['region'];
    $registro->defaultweight = $d['weight'];
    if ($d['blockname'] === 'html') {
        $cfg = new stdClass();
        $cfg->title = $d['title'];
        $cfg->text = $d['text'];
        $cfg->format = FORMAT_HTML;
        $cfg->classes = '';
        $registro->configdata = base64_encode(serialize($cfg));
    } else {
        $registro->configdata = '';
    }
    $registro->timecreated = time();
    $registro->timemodified = time();
    $id = $DB->insert_record('block_instances', $registro);
    context_block::instance($id);
    printf("  %-8s -> %-16s %s\n", $d['blockname'], $d['region'],
        $d['title'] ?: ($d['blockname'] === 'login' ? 'Entrar' : 'Consultas por WhatsApp'));
}

// ---------------------------------------------------------------------------
echo "\n=== 6. PURGA DE CACHÉS ===\n";
purge_all_caches();
echo "  Hecho. La primera carga tardará entre 20 y 30 segundos mientras se\n";
echo "  regenera la hoja de estilos del tema; después vuelve a la normalidad.\n";
