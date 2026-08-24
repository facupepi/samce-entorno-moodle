# Imagen de producción de Moodle para el entorno SAMCE (Railway).
#
# No reemplaza al flujo local (moodle-docker + scripts/0_instalar_entorno.ps1):
# ese sigue intacto para desarrollo. Esta imagen arma desde cero, en el build,
# lo mismo que los scripts 0 y 1 arman en un Moodle local: núcleo 4.5, tema
# Adaptable y los 7 complementos de terceros, con las mismas fuentes.
#
# La identidad visual (2_aplicar_identidad.php) y el contenido de prueba
# (samce_setup.php / samce_preguntas.php) se aplican en runtime, la primera
# vez que arranca el contenedor contra una base vacía — ver deploy/entrypoint.sh.

FROM php:8.2-apache

ARG MOODLE_BRANCH=MOODLE_405_STABLE
ARG ADAPTABLE_BRANCH=MOODLE_405

# --- Dependencias del sistema y extensiones de PHP que pide Moodle 4.5 -----
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl default-mysql-client \
        libzip-dev libicu-dev libxml2-dev libpng-dev libxslt1-dev \
        libfreetype6-dev libjpeg62-turbo-dev libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli gd intl xml zip curl mbstring soap opcache xsl \
    && a2enmod rewrite \
    && (a2dismod mpm_event mpm_worker 2>/dev/null || true) \
    && a2enmod mpm_prefork \
    && rm -rf /var/lib/apt/lists/*

# --- Núcleo de Moodle (misma rama que usa el entorno local) ----------------
RUN git clone --branch "${MOODLE_BRANCH}" --depth 1 \
        https://github.com/moodle/moodle.git /var/www/html \
    && rm -rf /var/www/html/.git

# --- Tema Adaptable ----------------------------------------------------------
RUN git clone --branch "${ADAPTABLE_BRANCH}" --depth 1 \
        https://github.com/gjbarnard/moodle-theme_adaptable.git /var/www/html/theme/adaptable \
    && rm -rf /var/www/html/theme/adaptable/.git

# --- Los 7 complementos de terceros (mismas URLs que scripts/1_instalar_plugins.ps1) ---
COPY deploy/instalar_plugins.sh /tmp/instalar_plugins.sh
RUN chmod +x /tmp/instalar_plugins.sh && /tmp/instalar_plugins.sh && rm /tmp/instalar_plugins.sh

# --- Ajustes de PHP: rendimiento (mismo archivo que usa el entorno local) y
# los mínimos que pide el chequeo de entorno de Moodle en producción -------
COPY scripts/opcache-samce.ini /usr/local/etc/php/conf.d/zz-samce.ini
COPY deploy/php-samce.ini /usr/local/etc/php/conf.d/zz-samce-prod.ini

# --- Config.php de producción, imágenes de identidad y scripts de setup ----
COPY deploy/config.php /var/www/html/config.php
COPY imagenes /opt/samce/imagenes
COPY scripts/2_aplicar_identidad.php /opt/samce/2_aplicar_identidad.php
COPY scripts/samce_setup.php /opt/samce/samce_setup.php
COPY scripts/samce_preguntas.php /opt/samce/samce_preguntas.php
COPY deploy/entrypoint.sh /opt/samce/entrypoint.sh
RUN chmod +x /opt/samce/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
ENTRYPOINT ["/opt/samce/entrypoint.sh"]
