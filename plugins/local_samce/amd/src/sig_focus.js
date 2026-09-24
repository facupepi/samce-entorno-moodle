/**
 * Señales de foco y de visibilidad (RF07).
 *
 * Foco y visibilidad son dos cosas distintas y hacen falta las dos: si otra
 * aplicación tapa el navegador sin minimizarlo, la pestaña sigue "visible" y
 * solo cambia el foco; si el alumno cambia de pestaña o minimiza, cambian
 * las dos.
 *
 * EL FOCO SE LEE DE document.hasFocus(), NO DE LOS EVENTOS. Los eventos
 * "blur" y "focus" no alcanzan, por dos motivos que se comprobaron sobre Moodle
 * real:
 *
 *  - Cuando el alumno hace clic dentro del cuadro de un ensayo, el foco pasa al
 *    iframe del editor (TinyMCE) y la ventana principal recibe un "blur" aunque
 *    no haya salido del examen. document.hasFocus() sigue siendo verdadero
 *    mientras el foco está dentro de un iframe de la página, así que se
 *    distingue.
 *  - Al revés: si el foco ESTÁ dentro del iframe y el alumno cambia de
 *    aplicación, el "blur" le llega a la ventana del iframe y no a la
 *    principal. Escuchar solo la ventana principal perdía justo el caso más
 *    importante: el alumno escribiendo su respuesta y pasando a otra ventana.
 *    Por eso los eventos se escuchan también dentro de los iframes de los
 *    editores, y además se mira document.hasFocus() una vez por segundo, por
 *    si algún evento no llega.
 *
 * El estado (con foco o sin foco) es lo que decide cuándo se informa, así que
 * varios eventos seguidos son un solo cambio, y un evento sin cambio real no
 * informa nada.
 *
 * @module     local_samce/sig_focus
 * @copyright  SAMCE
 */
define('local_samce/sig_focus', [], function() {
    'use strict';

    /** Cada cuánto se mira el foco, por si algún evento no llegó. */
    var POLL_MS = 1000;

    /**
     * @param {Object} env entorno de la captura (win, doc, now, emit, safe).
     * @return {Object} {register, stop, flush}
     */
    var start = function(env) {
        var win = env.win;
        var doc = env.doc;
        var focused = doc.hasFocus();
        var focusLostAt = null;
        var hiddenAt = null;

        // Compara el foco real con el que se tenía anotado y informa el cambio.
        // Si la página arrancó sin foco, no inventa una pérdida: el primer
        // cambio que informa es cuando lo tiene.
        var check = env.safe(function() {
            var hasFocus = doc.hasFocus();
            if (hasFocus === focused) {
                return;
            }
            focused = hasFocus;
            if (!hasFocus) {
                focusLostAt = env.now();
                env.emit('focus_lost', {});
            } else if (focusLostAt !== null) {
                var away = env.now() - focusLostAt;
                focusLostAt = null;
                env.emit('focus_gained', {away_ms: away});
            }
        });

        // Se mira un instante después del evento: recién ahí el foco ya
        // terminó de pasar, si es que pasó a un iframe.
        var soon = env.safe(function() {
            win.setTimeout(check, 0);
        });

        var onVisibility = env.safe(function() {
            if (doc.hidden) {
                if (hiddenAt === null) {
                    hiddenAt = env.now();
                    env.emit('visibility_hidden', {});
                }
            } else if (hiddenAt !== null) {
                var away = env.now() - hiddenAt;
                hiddenAt = null;
                env.emit('visibility_visible', {away_ms: away});
            }
        });

        win.addEventListener('blur', soon);
        win.addEventListener('focus', soon);
        doc.addEventListener('visibilitychange', onVisibility);
        var interval = win.setInterval(check, POLL_MS);

        /**
         * Cierra el tramo oculto que quedó abierto cuando la página se está
         * yendo de verdad (pagehide), no cuando solo se oculta (cambio de
         * pestaña, minimizar): eso puede volver, esto no. Sin esto, cada
         * pregunta nueva de un cuestionario paginado deja un visibility_hidden
         * sin su visibility_visible, indistinguible de ocultar la pestaña de
         * verdad (detectado rindiendo un examen real, no leyendo el código).
         *
         * @param {Object} [flushOptions]
         * @param {boolean} [flushOptions.unloading] la página se está yendo.
         */
        var flush = env.safe(function(flushOptions) {
            if (!flushOptions || !flushOptions.unloading || hiddenAt === null) {
                return;
            }
            var away = env.now() - hiddenAt;
            hiddenAt = null;
            env.emit('visibility_visible', {away_ms: away, reason: 'unload'});
        });

        return {
            // También dentro de los editores de texto: ahí llega el "blur" del
            // cambio de aplicación cuando el foco está en el editor.
            register: function(root, frame) {
                if (!frame) {
                    return undefined;
                }
                var view = (root.ownerDocument || root).defaultView;
                if (!view) {
                    return undefined;
                }
                view.addEventListener('blur', soon);
                view.addEventListener('focus', soon);
                return function() {
                    view.removeEventListener('blur', soon);
                    view.removeEventListener('focus', soon);
                };
            },

            stop: function() {
                win.clearInterval(interval);
                win.removeEventListener('blur', soon);
                win.removeEventListener('focus', soon);
                doc.removeEventListener('visibilitychange', onVisibility);
            },

            flush: flush
        };
    };

    return {start: start};
});
