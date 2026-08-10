# Instala en el Moodle local los complementos de terceros que tiene el Campus Virtual.
# Es idempotente: si un complemento ya está, lo saltea.
# Uso:  .\1_instalar_plugins.ps1

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

$raizMoodle = "C:\dev\samce\moodle"
$contenedor = "moodle-docker-webserver-1"
$tmp = "C:\dev\samce\_descargas_plugins"

if (-not (Test-Path "$raizMoodle\version.php")) {
    Write-Host "ERROR: no se encuentra el arbol de Moodle en $raizMoodle" -ForegroundColor Red
    Write-Host "Ajusta la variable raizMoodle al inicio de este script."
    exit 1
}

# Copia de seguridad de la base antes de tocar el esquema
$backup = "C:\dev\samce\backup_pre_plugins.sql"
if (-not (Test-Path $backup)) {
    Write-Host "Creando copia de seguridad de la base de datos..." -ForegroundColor Cyan
    docker exec moodle-docker-db-1 sh -c "mysqldump -umoodle -pm@0dl3ing moodle 2>/dev/null" > $backup
    "  Guardada en $backup ({0:N1} MB)" -f ((Get-Item $backup).Length / 1MB)
}

New-Item -ItemType Directory -Force -Path $tmp | Out-Null

# Complementos verificados como compatibles con Moodle 4.5
$plugins = @(
    @{ nombre='mod_attendance'; ruta='mod\attendance';
       url='https://github.com/danmarsden/moodle-mod_attendance/archive/refs/heads/MOODLE_405_STABLE.zip' }
    @{ nombre='mod_customcert'; ruta='mod\customcert';
       url='https://github.com/mdjnelson/moodle-mod_customcert/archive/refs/heads/MOODLE_404_STABLE.zip' }
    @{ nombre='mod_hsuforum'; ruta='mod\hsuforum';
       url='https://github.com/open-lms-open-source/moodle-mod_hsuforum/archive/refs/heads/MOODLE_405_STABLE.zip' }
    @{ nombre='mod_hotpot'; ruta='mod\hotpot';
       url='https://github.com/gbateson/moodle-mod_hotpot/archive/refs/heads/master.zip' }
    @{ nombre='mod_zoom'; ruta='mod\zoom';
       url='https://github.com/ncstate-delta/moodle-mod_zoom/archive/refs/tags/v5.5.0.zip' }
    @{ nombre='filter_wiris'; ruta='filter\wiris';
       url='https://github.com/wiris/moodle-filter_wiris/archive/refs/heads/master.zip' }
    @{ nombre='atto_wiris'; ruta='lib\editor\atto\plugins\wiris';
       url='https://github.com/wiris/moodle-atto_wiris/archive/refs/tags/v8.9.0.zip' }
)

Write-Host "`n=== Descarga e instalacion ===" -ForegroundColor Cyan
$instalados = 0
foreach ($p in $plugins) {
    $destino = Join-Path $raizMoodle $p.ruta

    if (Test-Path (Join-Path $destino 'version.php')) {
        "  {0,-16} ya estaba instalado" -f $p.nombre
        continue
    }

    $zip = Join-Path $tmp "$($p.nombre).zip"
    try {
        Invoke-WebRequest -Uri $p.url -OutFile $zip -TimeoutSec 300 -UseBasicParsing
    } catch {
        "  {0,-16} FALLO LA DESCARGA: {1}" -f $p.nombre, $_.Exception.Message
        continue
    }

    $extraido = Join-Path $tmp $p.nombre
    if (Test-Path $extraido) { Remove-Item -Recurse -Force $extraido }
    Expand-Archive -Path $zip -DestinationPath $extraido -Force

    # El zip de GitHub trae una carpeta con nombre largo: hay que renombrarla
    $raiz = Get-ChildItem $extraido -Directory | Select-Object -First 1
    if (-not $raiz -or -not (Test-Path (Join-Path $raiz.FullName 'version.php'))) {
        "  {0,-16} el paquete no contiene version.php" -f $p.nombre
        continue
    }

    $padre = Split-Path $destino -Parent
    if (-not (Test-Path $padre)) { New-Item -ItemType Directory -Force -Path $padre | Out-Null }
    Move-Item -Path $raiz.FullName -Destination $destino

    $contenido = Get-Content (Join-Path $destino 'version.php') -Raw
    $comp = if ($contenido -match "component\s*=\s*'([^']+)'") { $Matches[1] } else { '?' }
    $ver = if ($contenido -match "plugin->version\s*=\s*(\d+)") { $Matches[1] } else { '?' }

    if ($comp -ne $p.nombre) {
        "  {0,-16} ATENCION: el paquete declara '{1}'" -f $p.nombre, $comp
    } else {
        "  {0,-16} instalado, version {1}" -f $p.nombre, $ver
        $instalados++
    }
}

Remove-Item -Recurse -Force $tmp -ErrorAction SilentlyContinue

if ($instalados -gt 0) {
    Write-Host "`n=== Registrando los complementos en Moodle ===" -ForegroundColor Cyan
    docker exec -w /var/www/html $contenedor php admin/cli/upgrade.php --non-interactive
    docker exec -w /var/www/html $contenedor php admin/cli/purge_caches.php
}

Write-Host "`n=== Comprobacion ===" -ForegroundColor Cyan
docker exec $contenedor php -r @'
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$esperados = ['mod_attendance','mod_customcert','mod_hsuforum','mod_hotpot','mod_zoom','filter_wiris','atto_wiris'];
$faltan = [];
foreach ($esperados as $c) {
    $v = get_config($c, 'version');
    printf("  %-16s %s\n", $c, $v ? "instalado ($v)" : "FALTA");
    if (!$v) { $faltan[] = $c; }
}
echo $faltan ? "\n  Faltan: " . implode(', ', $faltan) . "\n" : "\n  Los 7 complementos estan instalados.\n";
'@
