/**
 * Señales del puntero (RF09): movimiento del mouse, salida y entrada del
 * cursor en la ventana, y tiempo que cada pregunta estuvo a la vista.
 *
 * El movimiento del mouse no se registra punto a punto (saturaría el envío y
 * degradaría el examen, riesgo R7): se muestrea a lo sumo cada 250 ms y se
 * agrega por ventana, con la cantidad de movimientos y la pausa más larga.
 * Si el mouse queda quieto un rato largo se avisa una sola vez.
 *
 * "El cursor sale de la ventana" indica que el puntero se fue a otro monitor
 * o a otra aplicación, y complementa la pérdida de foco.
 *
 * El "tiempo de respuesta" por pregunta se mide como el tiempo en que la
 * pregunta estuvo visible en pantalla (al menos la mitad de su alto) con la
 * pestaña a la vista. Sirve igual con una pregunta por página que con varias
 * en la misma, donde el momento en que carga la página no dice nada.
 *
 * @module     local_samce/sig_pointer
 * @copyright  SAMCE
 */
define('local_samce/sig_pointer', ['local_samce/question_context'], function(QuestionContext) {
    'use strict';

    var SAMPLE_MS = 250;
    var IDLE_MS = 15000;
    var TICK_MS = 1000;
    /** Tiempo mínimo, por pregunta y por ventana, para que valga la pena informarlo. */
    var MIN_DWELL_MS = 500;
    /** Tope de un solo intervalo: si la máquina se durmió, no se cuenta todo lo que pasó dormida. */
    var MAX_TICK_MS = 2000;

    var isMostlyVisible = function(win, element) {
        var rect = element.getBoundingClientRect();
        var height = rect.height || 0;
        if (height <= 0) {
            return false;
        }
        var visible = Math.min(rect.bottom, win.innerHeight) - Math.max(rect.top, 0);
        return visible >= Math.min(height, win.innerHeight) * 0.5;
    };

    /**
     * @param {Object} env entorno de la captura (win, doc, now, emit, safe).
     * @return {Object} {register, flush, stop}
     */
    var start = function(env) {
        var win = env.win;
        var doc = env.doc;

        var moves = 0;
        var lastSampleAt = 0;
        var lastMoveAt = env.now();
        var longestGap = 0;
        var idleReported = false;

        var onMove = env.safe(function() {
            var now = env.now();
            if (now - lastSampleAt < SAMPLE_MS) {
                return;
            }
            lastSampleAt = now;
            longestGap = Math.max(longestGap, now - lastMoveAt);
            lastMoveAt = now;
            idleReported = false;
            moves += 1;
        });

        var onLeave = env.safe(function() {
            env.emit('mouse_leave', {});
        });
        var onEnter = env.safe(function() {
            env.emit('mouse_enter', {});
        });

        // Tiempo a la vista por pregunta, muestreado una vez por segundo.
        var dwell = new Map();
        var lastTickAt = env.now();
        var tick = env.safe(function() {
            var now = env.now();
            var elapsed = Math.min(now - lastTickAt, MAX_TICK_MS);
            lastTickAt = now;
            if (doc.hidden) {
                return;
            }
            Array.prototype.forEach.call(doc.querySelectorAll('.que'), function(question) {
                if (!isMostlyVisible(win, question)) {
                    return;
                }
                var context = QuestionContext.of(question);
                if (context.slot === undefined) {
                    return;
                }
                var entry = dwell.get(context.slot) || {context: context, ms: 0};
                entry.ms += elapsed;
                dwell.set(context.slot, entry);
            });
        });
        var interval = win.setInterval(tick, TICK_MS);

        doc.documentElement.addEventListener('mouseleave', onLeave);
        doc.documentElement.addEventListener('mouseenter', onEnter);

        return {
            // El movimiento se mira también dentro de los editores de texto.
            register: function(root) {
                root.addEventListener('mousemove', onMove, true);
                return function() {
                    root.removeEventListener('mousemove', onMove, true);
                };
            },

            flush: function() {
                tick();

                var now = env.now();
                if (moves > 0) {
                    env.emit('mouse_activity', {moves: moves, idle_ms: longestGap});
                    moves = 0;
                    longestGap = 0;
                } else if (!idleReported && now - lastMoveAt >= IDLE_MS) {
                    env.emit('mouse_activity', {moves: 0, idle_ms: now - lastMoveAt});
                    idleReported = true;
                }

                dwell.forEach(function(entry) {
                    if (entry.ms >= MIN_DWELL_MS) {
                        env.emit('question_time', QuestionContext.withContext({ms: entry.ms}, entry.context));
                    }
                });
                dwell.clear();
            },

            stop: function() {
                win.clearInterval(interval);
                doc.documentElement.removeEventListener('mouseleave', onLeave);
                doc.documentElement.removeEventListener('mouseenter', onEnter);
            }
        };
    };

    return {start: start};
});
