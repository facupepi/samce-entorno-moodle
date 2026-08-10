<?php
/**
 * Comprueba que la réplica quedó completa. No modifica nada.
 * Uso:
 *   docker cp .\3_verificar.php moodle-docker-webserver-1:/tmp/
 *   docker exec moodle-docker-webserver-1 php /tmp/3_verificar.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/adminlib.php');

$contexto = context_system::instance();
$fs = get_file_storage();
$fallos = [];

function comprobar(string $etiqueta, $esperado, $obtenido, array &$fallos): void {
    $ok = (string) $esperado === (string) $obtenido;
    printf("  %-34s %-26s %s\n", $etiqueta, (string) $obtenido, $ok ? 'ok' : "<-- se esperaba $esperado");
    if (!$ok) {
        $fallos[] = $etiqueta;
    }
}

echo "=== 1. COMPLEMENTOS DE TERCEROS ===\n";
foreach (['mod_attendance', 'mod_customcert', 'mod_hsuforum', 'mod_hotpot',
          'mod_zoom', 'filter_wiris', 'atto_wiris', 'theme_adaptable'] as $c) {
    $v = get_config($c, 'version');
    printf("  %-18s %s\n", $c, $v ? "instalado ($v)" : 'FALTA');
    if (!$v) {
        $fallos[] = $c;
    }
}

echo "\n=== 2. AJUSTES DEL TEMA ===\n";
$esperados = [
    'headertoprowbkcolour'       => '#008cb2',
    'headermainrowbkcolour'      => '#F2F2F0',
    'menubordercolor'            => '#008cb2',
    'blockbordercolor'           => '#008cb2',
    'linkhover'                  => '#008cb2',
    'buttonlogincolor'           => '#ef5350',
    'footerbkcolor'              => '#114C5E',
    'blockheaderbackgroundcolor' => '#e0f1f5',
    'fontname'                   => 'Open Sans',
    'fontheadername'             => 'Roboto',
    'fonttitlename'              => 'Roboto Condensed',
    'sitetitle'                  => 'disabled',
    'sliderenabled'              => '1',
    'frontpageblocksenabled'     => '1',
    'blocklayoutlayoutrow1'      => '4-4-4-0',
    'blocklayoutlayoutrow2'      => '12-0-0-0',
    'footerlayoutrow1'           => '9-0-0-0',
];
foreach ($esperados as $clave => $valor) {
    comprobar($clave, $valor, get_config('theme_adaptable', $clave), $fallos);
}

echo "\n=== 3. CONFIGURACIÓN DEL SITIO ===\n";
comprobar('guestloginbutton', '0', get_config('core', 'guestloginbutton'), $fallos);
comprobar('frontpage (lista de cursos)', '', get_config('core', 'frontpage'), $fallos);
comprobar('shortname del sitio', 'FR_SFco', get_site()->shortname, $fallos);
comprobar('debug (0 = rápido)', '0', $CFG->debug, $fallos);

echo "\n=== 4. IMÁGENES (deben existir y pesar más de 0) ===\n";
$archivos = [
    ['theme_adaptable', 'logo', 'logo-utn-siglas.png.png'],
    ['theme_adaptable', 'p1', 'UTN-05.jpg'],
    ['theme_adaptable', 'p2', 'utn-fondo2.jpg'],
    ['theme_adaptable', 'p3', 'utn-fondo3.jpg'],
    ['theme_adaptable', 'adaptablemarkettingimages', 'Logo_Blanco.png'],
    ['theme_adaptable', 'adaptablemarkettingimages', 'ConsultasWhatsApp.png'],
    ['core_admin', 'logocompact', 'Logotipocompacto.png'],
    ['core_admin', 'favicon', 'logo_favicon.ico'],
];
foreach ($archivos as [$componente, $area, $nombre]) {
    $f = $fs->get_file($contexto->id, $componente, $area, 0, '/', $nombre);
    printf("  %-46s %s\n", "$area/$nombre",
        $f ? number_format($f->get_filesize()) . ' bytes' : 'FALTA');
    if (!$f) {
        $fallos[] = "$area/$nombre";
    }
}

echo "\n=== 5. BLOQUES DE LA PORTADA ===\n";
$bloques = $DB->get_records_select('block_instances',
    "parentcontextid = ? AND pagetypepattern = 'site-index'", [$contexto->id],
    'defaultregion, defaultweight');
$vistos = [];
foreach ($bloques as $b) {
    $cfg = @unserialize(base64_decode($b->configdata));
    $titulo = ($b->blockname === 'login') ? 'Entrar' : ($cfg->title ?? '');
    if ($titulo === '' && !empty($cfg->text) && stripos($cfg->text, 'wa.me') !== false) {
        $titulo = 'Consultas por WhatsApp';
    }
    printf("  %-24s %-16s peso %s\n", $titulo, $b->defaultregion, $b->defaultweight);
    $vistos[] = $titulo;
}
foreach (['Entrar', 'Consultas por WhatsApp', 'HORARIOS 2°C 2026'] as $necesario) {
    if (!in_array($necesario, $vistos, true)) {
        printf("  FALTA el bloque: %s\n", $necesario);
        $fallos[] = "bloque $necesario";
    }
}

echo "\n=== 6. ESTADO DE LOS COMPLEMENTOS ===\n";
$pm = core_plugin_manager::instance();
$conProblema = 0;
foreach ($pm->get_plugins() as $tipo => $plugins) {
    foreach ($plugins as $nombre => $info) {
        if ($info->get_status() !== core_plugin_manager::PLUGIN_STATUS_UPTODATE) {
            printf("  %s_%s no está al día\n", $tipo, $nombre);
            $conProblema++;
        }
    }
}
echo $conProblema ? "  $conProblema con problemas\n" : "  Todos al día.\n";

echo "\n" . str_repeat('=', 60) . "\n";
if ($fallos) {
    printf("RESULTADO: %d comprobaciones fallaron:\n", count($fallos));
    foreach ($fallos as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "RESULTADO: la réplica está completa y correcta.\n";
