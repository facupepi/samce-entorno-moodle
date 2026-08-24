#!/bin/bash
# Entrypoint del contenedor de producción. El esquema de Moodle (idioma,
# tema, admin) solo se instala si la base está vacía. Complementos,
# identidad visual y curso de prueba son pasos idempotentes que corren en
# todos los arranques, así un arranque interrumpido a mitad de camino se
# termina de completar solo en el siguiente, en vez de quedar a medias.
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
    php /var/www/html/admin/tool/langimport/cli/install.php --lang=es || \
        echo "AVISO: no se pudo instalar el paquete de idioma es, sigue en inglés." >&2
    php /var/www/html/admin/cli/cfg.php --name=lang --set=es
    php /var/www/html/admin/cli/cfg.php --name=timezone --set=America/Argentina/Cordoba
    php /var/www/html/admin/cli/cfg.php --name=country --set=AR

    echo "=== Activando tema Adaptable ==="
    php /var/www/html/admin/cli/cfg.php --name=theme --set=adaptable

    echo "=== Instalación inicial del esquema completa ==="
else
    echo "Moodle ya estaba instalado — se omite la instalación inicial del esquema."
fi

# Estos tres pasos son idempotentes (2_aplicar_identidad.php lo es por
# diseño; samce_setup.php y samce_preguntas.php chequean si ya existe lo
# que van a crear) — corren en TODOS los arranques, no solo el primero.
# Así, si un arranque anterior llegó a instalar el esquema pero se cortó
# antes de esto (como pasó una vez acá), el siguiente arranque los
# completa en vez de quedar a mitad de camino para siempre.

echo "=== Registrando complementos (idempotente) ==="
php /var/www/html/admin/cli/upgrade.php --non-interactive || true

echo "=== Aplicando identidad visual del campus (idempotente) ==="
rm -rf /tmp/imagenes_cvg
cp -r /opt/samce/imagenes /tmp/imagenes_cvg
php /opt/samce/2_aplicar_identidad.php

echo "=== Cargando curso, usuarios y evaluación de prueba (idempotente) ==="
cp /opt/samce/samce_setup.php /var/www/html/samce_setup.php
cp /opt/samce/samce_preguntas.php /var/www/html/samce_preguntas.php
(cd /var/www/html && php samce_setup.php && php samce_preguntas.php)
rm -f /var/www/html/samce_setup.php /var/www/html/samce_preguntas.php

php /var/www/html/admin/cli/purge_caches.php || true

echo "=== Forzando mpm_prefork como único MPM (algo lo revierte entre build y runtime) ==="
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
ln -sf ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
ls -la /etc/apache2/mods-enabled/ | grep -i mpm

echo "=== Arrancando Apache ==="
exec apache2-foreground
