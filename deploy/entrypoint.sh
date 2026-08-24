#!/bin/bash
# Entrypoint del contenedor de producción. Es idempotente: si la base ya
# tiene el esquema de Moodle instalado, no vuelve a instalar — solo purga
# cachés y arranca Apache. La instalación completa (esquema, idioma, tema,
# identidad visual, curso de prueba) corre una única vez, la primera vez
# que el contenedor arranca contra una base vacía.
set -euo pipefail

MYSQLHOST="${MYSQLHOST:?falta la variable MYSQLHOST}"
MYSQLPORT="${MYSQLPORT:-3306}"
MYSQLUSER="${MYSQLUSER:?falta la variable MYSQLUSER}"
MYSQLPASSWORD="${MYSQLPASSWORD:?falta la variable MYSQLPASSWORD}"
MYSQLDATABASE="${MYSQLDATABASE:?falta la variable MYSQLDATABASE}"
DATAROOT="${MOODLE_DATAROOT:-/var/moodledata}"

echo "=== SAMCE Moodle — arranque del contenedor ==="

mkdir -p "$DATAROOT"
chown -R www-data:www-data "$DATAROOT"

echo "Esperando a MySQL en ${MYSQLHOST}:${MYSQLPORT} (usuario ${MYSQLUSER})..."
ULTIMO_ERROR=""
for i in $(seq 1 30); do
    if ULTIMO_ERROR="$(mysqladmin ping --skip-ssl -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" 2>&1)"; then
        echo "  MySQL disponible."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "ERROR: MySQL no respondió después de 30 intentos. Último error de mysqladmin:" >&2
        echo "$ULTIMO_ERROR" >&2
        exit 1
    fi
    sleep 2
done

YA_INSTALADO="$(mysql --skip-ssl -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" "$MYSQLDATABASE" \
    -N -e "SHOW TABLES LIKE 'mdl_config'" 2>/dev/null || true)"

if [ -z "$YA_INSTALADO" ]; then
    echo "=== Base vacía: instalando Moodle por primera vez ==="
    php /var/www/html/admin/cli/install_database.php \
        --agree-license \
        --fullname="CAMPUS VIRTUAL - FAC. REGIONAL SAN FRANCISCO" \
        --shortname="FR_SFco" \
        --adminpass="${MOODLE_ADMIN_PASS:-Samce.2026}" \
        --adminemail="${MOODLE_ADMIN_EMAIL:-admin@example.com}"

    echo "=== Idioma español y zona horaria ==="
    php /var/www/html/admin/cli/install_language_pack.php --lang=es
    php /var/www/html/admin/cli/cfg.php --name=lang --set=es
    php /var/www/html/admin/cli/cfg.php --name=timezone --set=America/Argentina/Cordoba
    php /var/www/html/admin/cli/cfg.php --name=country --set=AR

    echo "=== Activando tema Adaptable ==="
    php /var/www/html/admin/cli/cfg.php --name=theme --set=adaptable

    echo "=== Registrando los 7 complementos (ya están en la imagen) ==="
    php /var/www/html/admin/cli/upgrade.php --non-interactive

    echo "=== Aplicando identidad visual del campus ==="
    rm -rf /tmp/imagenes_cvg
    cp -r /opt/samce/imagenes /tmp/imagenes_cvg
    php /opt/samce/2_aplicar_identidad.php

    echo "=== Cargando curso, usuarios y evaluación de prueba ==="
    cp /opt/samce/samce_setup.php /var/www/html/samce_setup.php
    cp /opt/samce/samce_preguntas.php /var/www/html/samce_preguntas.php
    (cd /var/www/html && php samce_setup.php && php samce_preguntas.php)
    rm -f /var/www/html/samce_setup.php /var/www/html/samce_preguntas.php

    echo "=== Instalación inicial completa ==="
else
    echo "Moodle ya estaba instalado — se omite la instalación inicial."
    # Por si el redeploy trajo una versión de algún plugin nueva.
    php /var/www/html/admin/cli/upgrade.php --non-interactive || true
fi

php /var/www/html/admin/cli/purge_caches.php || true

echo "=== Arrancando Apache ==="
exec apache2-foreground
