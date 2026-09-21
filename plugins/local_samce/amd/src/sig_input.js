/**
 * Señales de tecleo (RF08) y de portapapeles (RF10).
 *
 * PRIVACIDAD. Nada de lo que escribe el alumno sale de acá: no se guarda qué
 * tecla se apretó ni el texto que hay en el cuadro. Del tecleo se cuentan
 * inserciones y borrados, de dónde vino lo insertado (tipeo, pegado, arrastre,
 * autocompletado) y los tiempos entre una entrada y la siguiente. Del
 * portapapeles, la acción y la longitud del texto, nunca el texto. No se lee
 * nunca event.data ni el contenido del portapapeles más que para medirlo.
 *
 * Se mira el evento "input" y su inputType, no "keydown": no depende de qué
 * tecla fue, funciona igual con teclados virtuales y métodos de entrada, y
 * distingue el pegado del tipeo. El inputType también permite ver de dónde
 * vino un bloque de texto, que es lo que hace falta para detectar un pegado
 * sin guardar el texto.
 *
 * El tecleo se agrega por ventanas y por pregunta: cada vez que se vacía la
 * cola (cada pocos segundos) sale un solo evento por pregunta con lo
 * acumulado, en vez de uno por tecla.
 *
 * @module     local_samce/sig_input
 * @copyright  SAMCE
 */
define('local_samce/sig_input', ['local_samce/question_context'], function(QuestionContext) {
    'use strict';

    /** Una pausa entre entradas de texto, en milisegundos: por encima de esto no es cadencia, es una pausa. */
    var PAUSE_MS = 2000;
    /** Tope de intervalos que se guardan por ventana, para no crecer sin límite. */
    var MAX_INTERVALS = 500;

    var ORIGIN_BY_INPUT_TYPE = {
        insertText: 'typed',
        insertCompositionText: 'typed',
        insertLineBreak: 'typed',
        insertParagraph: 'typed',
        insertFromPaste: 'paste',
        insertFromPasteAsQuotation: 'paste',
        insertFromDrop: 'drop',
        insertReplacementText: 'replacement',
        insertFromYank: 'replacement'
    };

    var median = function(sorted) {
        var middle = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[middle] : Math.round((sorted[middle - 1] + sorted[middle]) / 2);
    };

    /**
     * @param {Object} env entorno de la captura (win, doc, now, emit, safe, onDocument).
     * @return {Object} {stop, flush}
     */
    var start = function(env) {
        var windows = new Map();

        var windowFor = function(context) {
            var key = context.slot === undefined ? 'none' : String(context.slot);
            if (!windows.has(key)) {
                windows.set(key, {
                    context: context,
                    inserts: 0,
                    deletes: 0,
                    byOrigin: {typed: 0, paste: 0, drop: 0, replacement: 0},
                    intervals: [],
                    pauses: 0,
                    last: null
                });
            }
            return windows.get(key);
        };

        var register = function(root, frame) {
            var onInput = env.safe(function(event) {
                var inputType = event.inputType;
                if (typeof inputType !== 'string' || inputType === '') {
                    // Casillas, botones de opción y listas también disparan "input", sin inputType.
                    return;
                }

                var isDelete = inputType.indexOf('delete') === 0;
                var origin = ORIGIN_BY_INPUT_TYPE[inputType];
                if (!isDelete && origin === undefined) {
                    // Formato, deshacer, rehacer…: no es texto que entra ni que sale.
                    return;
                }

                var acc = windowFor(QuestionContext.of(event.target, frame));
                if (isDelete) {
                    acc.deletes += 1;
                } else {
                    acc.inserts += 1;
                    acc.byOrigin[origin] += 1;
                }

                var at = env.now();
                if (acc.last !== null) {
                    var gap = at - acc.last;
                    if (gap >= PAUSE_MS) {
                        acc.pauses += 1;
                    } else if (gap >= 0 && acc.intervals.length < MAX_INTERVALS) {
                        acc.intervals.push(Math.round(gap));
                    }
                }
                acc.last = at;
            });

            var clipboard = function(action) {
                return env.safe(function(event) {
                    var length = 0;
                    if (action === 'paste') {
                        // Solo se mide; el texto pegado no se guarda ni se manda.
                        var pasted = event.clipboardData ? event.clipboardData.getData('text') : '';
                        length = typeof pasted === 'string' ? pasted.length : 0;
                    } else {
                        var view = (root.ownerDocument || root).defaultView;
                        var selection = view && typeof view.getSelection === 'function' ? view.getSelection() : null;
                        length = selection ? String(selection).length : 0;
                    }
                    env.emit('clipboard', QuestionContext.withContext({action: action, length: length},
                        QuestionContext.of(event.target, frame)));
                });
            };

            var onCopy = clipboard('copy');
            var onCut = clipboard('cut');
            var onPaste = clipboard('paste');

            root.addEventListener('input', onInput, true);
            root.addEventListener('copy', onCopy, true);
            root.addEventListener('cut', onCut, true);
            root.addEventListener('paste', onPaste, true);

            return function() {
                root.removeEventListener('input', onInput, true);
                root.removeEventListener('copy', onCopy, true);
                root.removeEventListener('cut', onCut, true);
                root.removeEventListener('paste', onPaste, true);
            };
        };

        return {
            register: register,

            /** Emite un evento por pregunta con lo acumulado desde la última vez. */
            flush: function() {
                windows.forEach(function(acc) {
                    var intervals = acc.intervals.slice().sort(function(a, b) {
                        return a - b;
                    });
                    env.emit('key_activity', QuestionContext.withContext({
                        inserts: acc.inserts,
                        deletes: acc.deletes,
                        by_origin: acc.byOrigin,
                        interval_ms: intervals.length ?
                            {min: intervals[0], median: median(intervals), max: intervals[intervals.length - 1]} :
                            {min: 0, median: 0, max: 0},
                        pauses: acc.pauses
                    }, acc.context));
                });
                windows.clear();
            },

            stop: function() {}
        };
    };

    return {start: start};
});
