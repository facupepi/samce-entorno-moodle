/**
 * Señales de foco y de visibilidad (RF07).
 *
 * Foco y visibilidad son dos cosas distintas y hacen falta las dos: si otra
 * aplicación tapa el navegador sin minimizarlo, la pestaña sigue "visible" y
 * solo cambia el foco; si el alumno cambia de pestaña o minimiza, cambian
 * las dos.
 *
 * Un detalle importante con los editores de texto: cuando el alumno hace clic
 * dentro del cuadro de un ensayo, el foco pasa al iframe del editor y la
 * ventana principal recibe un "blur" aunque el alumno no haya salido del
 * examen. Se distingue porque document.hasFocus() sigue siendo verdadero
 * mientras el foco está dentro de un iframe de la página. Sin este filtro
 * habría una falsa pérdida de foco cada vez que se hace clic para escribir.
 *
 * @module     local_samce/sig_focus
 * @copyright  SAMCE
 */
define('local_samce/sig_focus', [], function() {
    'use strict';

    /**
     * @param {Object} env entorno de la captura (win, doc, now, emit, safe).
     * @return {Object} {stop, flush}
     */
    var start = function(env) {
        var win = env.win;
        var doc = env.doc;
        var focusLostAt = null;
        var hiddenAt = null;

        var onBlur = env.safe(function() {
            // Se mira un instante después: recién ahí el foco ya terminó de
            // pasar al iframe, si es que pasó.
            win.setTimeout(env.safe(function() {
                if (doc.hasFocus() || focusLostAt !== null) {
                    return;
                }
                focusLostAt = env.now();
                env.emit('focus_lost', {});
            }), 0);
        });

        var onFocus = env.safe(function() {
            if (focusLostAt === null) {
                return;
            }
            var away = env.now() - focusLostAt;
            focusLostAt = null;
            env.emit('focus_gained', {away_ms: away});
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

        win.addEventListener('blur', onBlur);
        win.addEventListener('focus', onFocus);
        doc.addEventListener('visibilitychange', onVisibility);

        return {
            stop: function() {
                win.removeEventListener('blur', onBlur);
                win.removeEventListener('focus', onFocus);
                doc.removeEventListener('visibilitychange', onVisibility);
            },
            flush: function() {}
        };
    };

    return {start: start};
});
