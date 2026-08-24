<?php
/**
 * Instala el paquete de idioma español.
 *
 * Moodle 4.5 no trae ningún script CLI para esto (se verificó contra el
 * repo real: ni admin/cli/install_language_pack.php ni
 * admin/tool/langimport/cli/* existen). Usa la misma clase interna que
 * usa la pantalla de administración web (Site Administration > Language
 * > Language packs), tool_langimport\controller.
 *
 * Ejecutar: php instalar_idioma.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('Instalando paquete de idioma español');

$controller = new \tool_langimport\controller();
$controller->install_languagepacks(['es']);

foreach ($controller->info as $mensaje) {
    cli_writeln('  ' . $mensaje);
}
foreach ($controller->errors as $mensaje) {
    cli_writeln('  ERROR: ' . $mensaje);
}

if (!empty($controller->errors)) {
    exit(1);
}
