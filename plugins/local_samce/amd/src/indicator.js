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
            bar.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:2147483647;' +
                'background:#7a1f1f;color:#fff;text-align:center;' +
                'font:600 13px/1.8 -apple-system,BlinkMacSystemFont,sans-serif;' +
                'padding:2px 8px;box-shadow:0 1px 3px rgba(0,0,0,.35);pointer-events:none;';
            doc.body.appendChild(bar);

            return {
                remove: function() {
                    try {
                        if (bar.parentNode) {
                            bar.parentNode.removeChild(bar);
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
