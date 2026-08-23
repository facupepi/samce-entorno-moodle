#!/bin/bash
# Descarga e instala los 7 complementos de terceros dentro de la imagen, en
# build time. Mismas fuentes exactas que scripts/1_instalar_plugins.ps1 (el
# que se usa para el entorno local) — si ese script cambia de versión de un
# plugin, hay que reflejarlo acá también a mano.
set -euo pipefail

instalar() {
    local url="$1" destino="$2"
    local tmp
    tmp="$(mktemp -d)"
    curl -fsSL "$url" -o "$tmp/plugin.zip"
    unzip -q "$tmp/plugin.zip" -d "$tmp/extraido"
    local raiz
    raiz="$(find "$tmp/extraido" -mindepth 1 -maxdepth 1 -type d | head -n1)"
    if [ -z "$raiz" ] || [ ! -f "$raiz/version.php" ]; then
        echo "ERROR: $url no contiene version.php" >&2
        exit 1
    fi
    mkdir -p "$(dirname "$destino")"
    mv "$raiz" "$destino"
    rm -rf "$tmp"
    echo "  instalado: $destino"
}

instalar "https://github.com/danmarsden/moodle-mod_attendance/archive/refs/heads/MOODLE_405_STABLE.zip" \
    /var/www/html/mod/attendance

instalar "https://github.com/mdjnelson/moodle-mod_customcert/archive/refs/heads/MOODLE_404_STABLE.zip" \
    /var/www/html/mod/customcert

instalar "https://github.com/open-lms-open-source/moodle-mod_hsuforum/archive/refs/heads/MOODLE_405_STABLE.zip" \
    /var/www/html/mod/hsuforum

instalar "https://github.com/gbateson/moodle-mod_hotpot/archive/refs/heads/master.zip" \
    /var/www/html/mod/hotpot

instalar "https://github.com/ncstate-delta/moodle-mod_zoom/archive/refs/tags/v5.5.0.zip" \
    /var/www/html/mod/zoom

instalar "https://github.com/wiris/moodle-filter_wiris/archive/refs/heads/master.zip" \
    /var/www/html/filter/wiris

instalar "https://github.com/wiris/moodle-atto_wiris/archive/refs/tags/v8.9.0.zip" \
    /var/www/html/lib/editor/atto/plugins/wiris

echo "Los 7 complementos quedaron instalados en la imagen."
