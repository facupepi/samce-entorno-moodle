# Levanta el entorno Moodle desde cero: Docker, Moodle 4.5, tema Adaptable y
# contenido de prueba. Después de esto se corren los pasos 1, 2 y 3.
#
# ATENCIÓN: este script no está probado de punta a punta, a diferencia de los
# otros tres. Reproduce los pasos con los que se armó el entorno original, pero
# la instalación de Moodle depende de la red y del estado de los repositorios.
# Conviene ejecutarlo por tramos y verificar cada uno, en lugar de a ciegas.
#
# Uso:  .\0_instalar_entorno.ps1

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

# --- Parámetros -------------------------------------------------------------
$base        = "C:\dev\samce"          # fuera de OneDrive, a propósito
$ramaMoodle  = "MOODLE_405_STABLE"
$ramaTema    = "MOODLE_405"            # ojo: no existe MOODLE_405_STABLE en Adaptable
$puerto      = "8000"
$phpVersion  = "8.2"
$claveAdmin  = "Samce.2026"

Write-Host "=== 0. Comprobaciones previas ===" -ForegroundColor Cyan
foreach ($cmd in @('docker', 'git')) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
        Write-Host "  FALTA: $cmd. Instalalo antes de seguir." -ForegroundColor Red
        exit 1
    }
    "  $cmd disponible"
}
docker info 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Docker no está corriendo. Abrí Docker Desktop y esperá a que arranque." -ForegroundColor Red
    exit 1
}
"  Docker en ejecución"

Write-Host "`n=== 1. Descargar Moodle y moodle-docker ===" -ForegroundColor Cyan
New-Item -ItemType Directory -Force -Path $base | Out-Null

if (-not (Test-Path "$base\moodle-docker\.git")) {
    git clone https://github.com/moodlehq/moodle-docker.git "$base\moodle-docker"
    "  moodle-docker descargado"
} else { "  moodle-docker ya estaba" }

if (-not (Test-Path "$base\moodle\version.php")) {
    Write-Host "  Descargando Moodle (son ~29.000 archivos, puede tardar)..." -ForegroundColor Yellow
    git clone --branch $ramaMoodle --depth 1 https://github.com/moodle/moodle.git "$base\moodle"
    "  Moodle descargado"
} else { "  Moodle ya estaba" }

Write-Host "`n=== 2. Variables de entorno ===" -ForegroundColor Cyan
# Solo hacen falta para docker compose, no para arrancar los contenedores después.
$env:MOODLE_DOCKER_WWWROOT     = "$base\moodle"
$env:MOODLE_DOCKER_DB          = "mysql"
$env:MOODLE_DOCKER_PHP_VERSION = $phpVersion
$env:MOODLE_DOCKER_WEB_PORT    = $puerto
"  definidas para esta sesión"

Write-Host "`n=== 3. Levantar los contenedores ===" -ForegroundColor Cyan
Copy-Item "$base\moodle-docker\config.docker-template.php" "$base\moodle\config.php" -Force
Push-Location "$base\moodle-docker"
docker compose up -d
Pop-Location
"  esperando a la base de datos..."
Start-Sleep -Seconds 25

Write-Host "`n=== 4. Instalar Moodle ===" -ForegroundColor Cyan
docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/install_database.php `
    --agree-license `
    --fullname="CAMPUS VIRTUAL - FAC. REGIONAL SAN FRANCISCO" `
    --shortname="FR_SFco" `
    --adminpass="$claveAdmin" `
    --adminemail="admin@example.com"

Write-Host "`n=== 5. Idioma español y zona horaria ===" -ForegroundColor Cyan
docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/install_language_pack.php --lang=es
docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/cfg.php --name=lang --set=es
docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/cfg.php --name=timezone --set=America/Argentina/Cordoba
docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/cfg.php --name=country --set=AR
"  idioma y zona horaria configurados"

Write-Host "`n=== 6. Tema Adaptable ===" -ForegroundColor Cyan
if (-not (Test-Path "$base\moodle\theme\adaptable\version.php")) {
    git clone --branch $ramaTema --depth 1 https://github.com/gjbarnard/moodle-theme_adaptable.git "$base\moodle\theme\adaptable"
    Remove-Item -Recurse -Force "$base\moodle\theme\adaptable\.git" -ErrorAction SilentlyContinue
    docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/upgrade.php --non-interactive
}
docker exec -w /var/www/html moodle-docker-webserver-1 php admin/cli/cfg.php --name=theme --set=adaptable
"  tema Adaptable instalado y activado"

Write-Host "`n=== 7. Corregir el rendimiento ===" -ForegroundColor Cyan
# El config.php de moodle-docker activa el modo desarrollador, que sobre el disco
# montado desde Windows deja el sitio en ~15 segundos por página.
$config = "$base\moodle\config.php"
$texto = Get-Content $config -Raw
$texto = $texto -replace '\$CFG->debug\s*=\s*\(E_ALL\)[^;]*;', '$CFG->debug = 0;'
$texto = $texto -replace '\$CFG->debugdisplay\s*=\s*1;', '$CFG->debugdisplay = 0;'
$texto = $texto -replace '\$CFG->debugstringids\s*=\s*1;', '$CFG->debugstringids = 0;'
$texto = $texto -replace '\$CFG->perfdebug\s*=\s*15;', '$CFG->perfdebug = 0;'
$texto = $texto -replace '\$CFG->debugpageinfo\s*=\s*1;', '$CFG->debugpageinfo = 0;'
Set-Content $config -Value $texto -Encoding UTF8 -NoNewline
"  modo de depuración desactivado en config.php"

# Ajuste del acelerador de código PHP (vive dentro del contenedor: se pierde si se recrea)
docker cp "$PSScriptRoot\opcache-samce.ini" moodle-docker-webserver-1:/usr/local/etc/php/conf.d/zz-samce.ini
docker restart moodle-docker-webserver-1 | Out-Null
"  ajuste de OPcache copiado"
Start-Sleep -Seconds 15

Write-Host "`n=== 8. Contenido de prueba ===" -ForegroundColor Cyan
docker cp "$PSScriptRoot\samce_setup.php" moodle-docker-webserver-1:/var/www/html/samce_setup.php
docker cp "$PSScriptRoot\samce_preguntas.php" moodle-docker-webserver-1:/var/www/html/samce_preguntas.php
docker exec -w /var/www/html moodle-docker-webserver-1 php samce_setup.php
docker exec -w /var/www/html moodle-docker-webserver-1 php samce_preguntas.php
docker exec moodle-docker-webserver-1 rm -f /var/www/html/samce_setup.php /var/www/html/samce_preguntas.php
"  curso, usuarios y evaluación creados"

Write-Host "`n=== 9. Comprobación ===" -ForegroundColor Cyan
$codigo = curl.exe -s -o NUL -w "%{http_code}" -m 120 "http://localhost:$puerto/login/index.php"
if ($codigo -eq "200") {
    Write-Host "  El sitio responde en http://localhost:$puerto" -ForegroundColor Green
    Write-Host "  Usuario admin, contraseña $claveAdmin"
    Write-Host "`n  Siguiente: .\1_instalar_plugins.ps1" -ForegroundColor Cyan
} else {
    Write-Host "  El sitio devolvió $codigo. Revisá: docker logs moodle-docker-webserver-1" -ForegroundColor Red
}
