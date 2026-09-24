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
 * OJO al leerlo: question_time mide EXPOSICIÓN, no dedicación. Con varias
 * preguntas a la vez en pantalla (un docente que arma un cuestionario con
 * cuatro por página) el segundo se le suma entero a cada una. Por eso el
 * evento lleva visible_count, la mayor cantidad de preguntas a la vista en
 * ese lapso: con él el análisis reparte o descuenta en vez de adivinar. La
 * dedicación real se estima cruzándolo con key_activity y clipboard de la
 * misma pregunta.
 *
 * El tiempo se acumula y NO se informa en cada vaciado: se informa cuando la
 * pregunta sale de la vista, cuando se cambia de página o se cierra (el
 * vaciado final), o, como mucho, cada minuto. Informarlo cada 5 segundos
 * llenaba la lista de filas casi iguales sin decir nada más: en un examen de
 * dos horas eran más de mil por pregunta, y ocultaban lo importante.
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
    /** Lo más que se guarda, sin informarlo, el tiempo de una pregunta que sigue a la vista. */
    var MAX_HOLD_MS = 60000;

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
        var hasMoved = false;
        var lastFlushAt = env.now();
        var leftAt = null;
        // La pausa más larga DENTRO de esta ventana, y el hueco desde el último
        // movimiento de una ventana anterior si es el primero de esta. Antes
        // idle_ms se medía contra el último movimiento aunque fuera de tramos
        // anteriores: salía "15 s sin mover" en una ventana de 5 s, y al revés,
        // 15 s casi quietos quedaban informados como "300 ms" (revisión del
        // 24/09/2026, punto 3.2).
        var longestGap = 0;
        var gapBefore = null;
        var idleReported = false;

        var onMove = env.safe(function() {
            var now = env.now();
            if (now - lastSampleAt < SAMPLE_MS) {
                return;
            }
            lastSampleAt = now;
            if (moves === 0 && hasMoved && lastMoveAt < lastFlushAt) {
                gapBefore = Math.max(0, Math.round(now - lastMoveAt));
            }
            longestGap = Math.max(longestGap, now - Math.max(lastMoveAt, lastFlushAt));
            lastMoveAt = now;
            hasMoved = true;
            idleReported = false;
            moves += 1;
        });

        var onLeave = env.safe(function() {
            leftAt = env.now();
            env.emit('mouse_leave', {});
        });
        // away_ms igual que en sig_focus: la duración viaja en el propio evento
        // de vuelta y sobrevive aunque falte el de salida. Sin salida previa
        // (la página se cargó con el cursor afuera) no hay cuánto informar.
        var onEnter = env.safe(function() {
            var data = {};
            if (leftAt !== null) {
                data.away_ms = Math.max(0, Math.round(env.now() - leftAt));
                leftAt = null;
            }
            env.emit('mouse_enter', data);
        });

        // Tiempo a la vista por pregunta, muestreado una vez por segundo. Cada
        // entrada guarda cuánto se acumuló sin informar (ms), desde cuándo
        // (since) y si la pregunta sigue a la vista (inView).
        var dwell = new Map();
        var lastTickAt = env.now();
        var tick = env.safe(function() {
            var now = env.now();
            // Si el reloj se corrigió hacia atrás la diferencia es negativa: no se descuenta tiempo.
            var elapsed = Math.max(0, Math.min(now - lastTickAt, MAX_TICK_MS));
            lastTickAt = now;
            if (doc.hidden) {
                return;
            }
            dwell.forEach(function(entry) {
                entry.inView = false;
            });
            var visible = [];
            Array.prototype.forEach.call(doc.querySelectorAll('.que'), function(question) {
                if (!isMostlyVisible(win, question)) {
                    return;
                }
                var context = QuestionContext.of(question);
                if (context.slot === undefined) {
                    return;
                }
                visible.push(context);
            });
            visible.forEach(function(context) {
                var entry = dwell.get(context.slot) ||
                    {context: context, ms: 0, since: now, inView: true, covisible: 0};
                entry.ms += elapsed;
                entry.inView = true;
                entry.covisible = Math.max(entry.covisible, visible.length);
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

            /**
             * @param {Object} [options]
             * @param {boolean} [options.final] la página se está cerrando o
             *        ocultando: lo que quedó acumulado se informa ya.
             */
            flush: function(options) {
                var final = !!(options && options.final);
                tick();

                var now = env.now();
                // Largo real de la ventana que resume el evento: el normal es de
                // 5 s, pero los vaciados por salir de la página o volver la
                // conexión la acortan.
                var windowMs = Math.max(0, Math.round(now - lastFlushAt));
                if (moves > 0) {
                    // La pausa final, desde el último movimiento hasta el cierre de la ventana.
                    var data = {
                        moves: moves,
                        idle_ms: Math.max(longestGap, now - lastMoveAt),
                        window_ms: windowMs
                    };
                    if (gapBefore !== null) {
                        data.gap_before_ms = gapBefore;
                    }
                    env.emit('mouse_activity', data);
                } else if (!idleReported && now - lastMoveAt >= IDLE_MS) {
                    env.emit('mouse_activity', {moves: 0, idle_ms: now - lastMoveAt, window_ms: windowMs});
                    idleReported = true;
                }
                moves = 0;
                longestGap = 0;
                gapBefore = null;
                lastFlushAt = now;

                dwell.forEach(function(entry, slot) {
                    var due = final || !entry.inView || now - entry.since >= MAX_HOLD_MS;
                    if (!due) {
                        return;
                    }
                    if (entry.ms >= MIN_DWELL_MS) {
                        env.emit('question_time', QuestionContext.withContext(
                            {ms: entry.ms, visible_count: entry.covisible}, entry.context));
                    }
                    if (final || !entry.inView) {
                        dwell.delete(slot);
                    } else {
                        entry.ms = 0;
                        entry.since = now;
                        entry.covisible = 0;
                    }
                });
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
