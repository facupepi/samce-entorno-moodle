/**
 * Indicador visible y persistente de que el monitoreo está activo (HU12, RF06).
 *
 * Arranca junto con la captura real (que, por HU11, nunca arranca sin la
 * aceptación del alumno) y se cae cuando la captura para. Nada de esto puede
 * romper el examen: cualquier falla al crearlo o sacarlo se ignora.
 *
 * @module     local_samce/indicator
 * @copyright  SAMCE
 */
define('local_samce/indicator', [], function() {
    'use strict';

    var BAR_ID = 'local-samce-monitoring-indicator';

    // Alto de la barra. Se reserva el mismo espacio al pie del body para que
    // no tape el final de la página (el botón de siguiente o de terminar).
    var BAR_HEIGHT_PX = 28;

    /**
     * @param {Document} doc
     * @param {string} [text]
     * @return {Object} {remove}
     */
    var show = function(doc, text) {
        try {
            if (!text || doc.getElementById(BAR_ID)) {
                return {remove: function() { /* nada que mostrar o ya existe */ }};
            }

            var bar = doc.createElement('div');
            bar.id = BAR_ID;
            bar.setAttribute('role', 'status');
            bar.textContent = text;
            // Estilo en línea a propósito: nada de hoja de estilos aparte que
            // pueda no cargar o quedar pisada por el tema de Moodle. Colores
            // fuera de la paleta del examen (RF06: distinguirse visualmente).
            // Va al pie y no arriba: arriba tapaba la barra de navegación del
            // tema, que además es position:fixed y no se corre con padding.
            bar.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:2147483647;' +
                'background:#7a1f1f;color:#fff;text-align:center;box-sizing:border-box;' +
                'height:' + BAR_HEIGHT_PX + 'px;' +
                'font:600 13px/' + BAR_HEIGHT_PX + 'px -apple-system,BlinkMacSystemFont,sans-serif;' +
                'padding:0 8px;box-shadow:0 -1px 3px rgba(0,0,0,.35);pointer-events:none;';
            var previousPadding = doc.body.style.paddingBottom;
            var currentPadding = parseFloat(doc.defaultView && doc.defaultView.getComputedStyle ?
                doc.defaultView.getComputedStyle(doc.body).paddingBottom : '') || 0;
            doc.body.style.paddingBottom = (currentPadding + BAR_HEIGHT_PX) + 'px';
            doc.body.appendChild(bar);

            return {
                remove: function() {
                    try {
                        if (bar.parentNode) {
                            bar.parentNode.removeChild(bar);
                            doc.body.style.paddingBottom = previousPadding;
                        }
                    } catch (e) {
                        // Nada: si ya no está, no hay nada que sacar.
                    }
                }
            };
        } catch (e) {
            return {remove: function() { /* nada que sacar */ }};
        }
    };

    return {show: show};
});
