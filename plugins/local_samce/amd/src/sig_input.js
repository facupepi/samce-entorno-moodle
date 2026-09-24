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
        // Cuándo fue la última entrada de texto de cada pregunta. Vive afuera de
        // la ventana a propósito: la ventana se descarta en cada vaciado, y con
        // ella se perdía el hueco entre la última tecla de una y la primera de
        // la siguiente, que es justo la pausa larga que interesa medir.
        var lastKeyAt = new Map();
        // Desde cuándo cuenta window_ms: el vaciado anterior, igual que en
        // sig_pointer. Antes acá era desde que arrancó la ventana de cada pregunta
        // y allá desde el vaciado, dos definiciones para el mismo nombre
        // (revisión del 24/09/2026, punto 3.3).
        var lastFlushAt = env.now();

        var windowFor = function(context) {
            var key = context.slot === undefined ? 'none' : String(context.slot);
            if (!windows.has(key)) {
                windows.set(key, {
                    key: key,
                    gapBefore: null,
                    maxPause: 0,
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
                if (acc.last === null && lastKeyAt.has(acc.key)) {
                    acc.gapBefore = Math.max(0, Math.round(at - lastKeyAt.get(acc.key)));
                }
                lastKeyAt.set(acc.key, at);
                if (acc.last !== null) {
                    var gap = at - acc.last;
                    if (gap >= PAUSE_MS) {
                        acc.pauses += 1;
                        acc.maxPause = Math.max(acc.maxPause, Math.round(gap));
                    } else if (gap >= 0 && acc.intervals.length < MAX_INTERVALS) {
                        acc.intervals.push(Math.round(gap));
                    }
                }
                acc.last = at;
            });

            /**
             * Cuántos caracteres hay seleccionados. En un input o textarea
             * window.getSelection() no devuelve la selección (Chrome, Edge y
             * Firefox), así que ahí se mide con selectionStart/selectionEnd.
             * Devuelve null si ninguna de las dos formas da un número: el
             * evento sale entonces sin `length` y el panel lo muestra sin
             * cantidad, en vez de afirmar que se copiaron 0 caracteres.
             */
            var selectedLength = function(target) {
                try {
                    if (target && typeof target.selectionStart === 'number' &&
                            typeof target.selectionEnd === 'number') {
                        return Math.max(0, target.selectionEnd - target.selectionStart);
                    }
                } catch (e) {
                    // Algunos tipos de input lanzan al leer la selección.
                }
                var view = (root.ownerDocument || root).defaultView;
                var selection = view && typeof view.getSelection === 'function' ? view.getSelection() : null;
                return selection ? String(selection).length : null;
            };

            var clipboard = function(action) {
                return env.safe(function(event) {
                    var data = {action: action};
                    if (action === 'paste') {
                        // Solo se mide; el texto pegado no se guarda ni se manda.
                        var clipboardData = event.clipboardData;
                        var pasted = clipboardData ? clipboardData.getData('text') : '';
                        if (typeof pasted === 'string' && pasted.length > 0) {
                            data.length = pasted.length;
                        } else if (clipboardData && clipboardData.files && clipboardData.files.length > 0) {
                            // Pegó un archivo o una imagen: no es texto y no tiene largo. Antes
                            // salía "0 caracteres" (revisión del 24/09/2026, punto 3.5).
                            data.non_text = true;
                        } else {
                            data.length = 0;
                        }
                    } else {
                        var length = selectedLength(event.target);
                        if (length !== null) {
                            data.length = length;
                        }
                    }
                    env.emit('clipboard', QuestionContext.withContext(data, QuestionContext.of(event.target, frame)));
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
                    var data = {
                        inserts: acc.inserts,
                        deletes: acc.deletes,
                        by_origin: acc.byOrigin,
                        pauses: acc.pauses,
                        // Largo real de la ventana: el normal es de 5 s, pero los
                        // vaciados por salir de la página o volver la conexión la acortan.
                        window_ms: Math.round(env.now() - lastFlushAt),
                        // La pausa más larga dentro de la ventana (pauses solo la cuenta).
                        max_pause_ms: acc.maxPause
                    };
                    // Sin intervalos que medir (una sola tecla, o todos los huecos fueron
                    // pausas de 2 s o más) no se manda la clave. Antes salía {0,0,0}: el
                    // alumno que piensa cada palabra quedaba igual que un pegado
                    // instantáneo, que es lo contrario (revisión del 24/09/2026, 1.5). Es el
                    // mismo criterio que el de `length` en copiar y cortar.
                    if (intervals.length) {
                        data.interval_ms = {min: intervals[0], median: median(intervals), max: intervals[intervals.length - 1]};
                    }
                    // Hueco desde la última entrada de la ventana anterior de esta
                    // misma pregunta; sin clave si es la primera vez que se teclea.
                    if (acc.gapBefore !== null) {
                        data.gap_before_ms = acc.gapBefore;
                    }
                    env.emit('key_activity', QuestionContext.withContext(data, acc.context));
                });
                windows.clear();
                lastFlushAt = env.now();
            },

            stop: function() {}
        };
    };

    return {start: start};
});
