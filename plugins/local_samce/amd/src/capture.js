/**
 * Captura de eventos de interacción del alumno durante un intento de examen
 * (HU10, TT_S2).
 *
 * El complemento carga este módulo únicamente en la página de un intento
 * propio y en curso (ver classes/hook_callbacks.php). Junta las señales, las
 * guarda en una cola que sobrevive a la recarga de la página, y cada pocos
 * segundos las entrega a Moodle, que las firma y las reenvía a SAMCE. El
 * navegador nunca habla con SAMCE.
 *
 * REGLA DE ORO: nada de acá puede afectar el examen. Cada detector corre
 * envuelto en un try/catch, el envío tiene tiempo límite y no muestra
 * mensajes, y si la captura está apagada o mal configurada del lado del
 * servidor, se calla del todo. Con el monitoreo caído, sin respuesta o mal
 * configurado, el alumno responde, navega y entrega igual.
 *
 * @module     local_samce/capture
 * @copyright  SAMCE
 */
define('local_samce/capture', [
    'local_samce/queue',
    'local_samce/transport',
    'local_samce/frames',
    'local_samce/sig_focus',
    'local_samce/sig_input',
    'local_samce/sig_pointer',
    'local_samce/sig_window'
], function(Queue, Transport, Frames, SigFocus, SigInput, SigPointer, SigWindow) {
    'use strict';

    var DEFAULT_FLUSH_MS = 5000;
    var BATCH_SIZE = 100;
    /** Al cerrar la página el navegador limita el tamaño de lo que puede terminar de enviar. */
    var UNLOAD_BATCH_SIZE = 40;
    var BACKOFF_BASE_MS = 2000;
    var BACKOFF_MAX_MS = 30000;
    /** Tope de lotes seguidos en un mismo vaciado, para no monopolizar la página. */
    var MAX_BATCHES_PER_FLUSH = 5;

    /**
     * Arranca la captura. Recibe todo lo que toca del navegador, para poder
     * probarla sin Moodle.
     *
     * @param {Object} options
     * @param {Window} options.win
     * @param {Document} options.doc
     * @param {Object} options.transport
     * @param {Storage|null} options.storage
     * @param {number} options.attemptId
     * @param {number} [options.flushMs]
     * @param {Function} [options.now]
     * @return {Object} {stop, flush, queue}
     */
    var start = function(options) {
        var win = options.win;
        var doc = options.doc;
        var now = options.now || Date.now;
        var attemptId = options.attemptId;

        var queue = Queue.create({
            storage: options.storage,
            key: 'local_samce:capture:' + attemptId,
            now: now
        });

        var stopped = false;
        var sending = false;
        var failures = 0;
        var retryAt = 0;

        // Ningún error de un detector puede llegar a la página.
        var safe = function(fn) {
            return function() {
                try {
                    return fn.apply(this, arguments);
                } catch (e) {
                    return undefined;
                }
            };
        };

        var env = {
            win: win,
            doc: doc,
            now: now,
            safe: safe,
            emit: function(type, data) {
                if (!stopped) {
                    queue.push(type, data);
                }
            }
        };

        var signals = [
            SigFocus.start(env),
            SigInput.start(env),
            SigPointer.start(env),
            SigWindow.start(env)
        ];

        // Los detectores de texto también van dentro de los iframes de los editores.
        var frames = Frames.watch(win, doc, function(root, frame) {
            var cleanups = [];
            signals.forEach(function(signal) {
                if (typeof signal.register === 'function') {
                    cleanups.push(signal.register(root, frame));
                }
            });
            return function() {
                cleanups.forEach(function(cleanup) {
                    if (typeof cleanup === 'function') {
                        cleanup();
                    }
                });
            };
        });

        if (!queue.profileSent()) {
            env.emit('client_profile', SigWindow.profileOf(win));
            queue.markProfileSent();
        }

        var stop = function() {
            if (stopped) {
                return;
            }
            stopped = true;
            win.clearInterval(interval);
            signals.forEach(function(signal) {
                signal.stop();
            });
            frames.stop();
            win.removeEventListener('pagehide', onPageHide);
            win.removeEventListener('online', onOnline);
            doc.removeEventListener('visibilitychange', onVisibility);
        };

        /**
         * Vacía lo pendiente hacia el servidor.
         *
         * @param {Object} [flushOptions]
         * @param {boolean} [flushOptions.force] ignora la espera de un reintento.
         * @param {boolean} [flushOptions.keepalive] la página se está cerrando.
         * @return {Promise}
         */
        var flush = function(flushOptions) {
            flushOptions = flushOptions || {};
            if (stopped) {
                return Promise.resolve();
            }

            signals.forEach(function(signal) {
                safe(signal.flush)();
            });

            if (sending || queue.size() === 0) {
                return Promise.resolve();
            }
            if (!flushOptions.force && now() < retryAt) {
                return Promise.resolve();
            }

            sending = true;
            var batchesLeft = flushOptions.keepalive ? 1 : MAX_BATCHES_PER_FLUSH;

            var sendNext = function() {
                var batch = queue.peek(flushOptions.keepalive ? UNLOAD_BATCH_SIZE : BATCH_SIZE);
                // Cualquier falla del envío, incluso una que lance de forma síncrona, es un reintento.
                return Promise.resolve().then(function() {
                    return options.transport.send(attemptId, batch, {keepalive: !!flushOptions.keepalive});
                }).catch(function() {
                    return 'retry';
                }).then(function(status) {
                    if (status === 'disabled') {
                        // La captura está apagada o mal configurada: se deja de capturar.
                        queue.clear();
                        stop();
                        return;
                    }
                    if (status === 'retry') {
                        failures += 1;
                        retryAt = now() + Math.min(BACKOFF_MAX_MS, BACKOFF_BASE_MS * Math.pow(2, failures - 1));
                        return;
                    }

                    // 'ok', o 'rejected' (que no mejora reintentando): el lote sale de la cola.
                    queue.drop(batch.length);
                    failures = 0;
                    retryAt = 0;
                    batchesLeft -= 1;
                    if (batchesLeft > 0 && queue.size() > 0) {
                        return sendNext();
                    }
                });
            };

            return sendNext().then(function() {
                sending = false;
            }, function() {
                sending = false;
            });
        };

        var interval = win.setInterval(safe(function() {
            flush();
        }), options.flushMs || DEFAULT_FLUSH_MS);

        var onPageHide = safe(function() {
            flush({force: true, keepalive: true});
        });
        var onVisibility = safe(function() {
            if (doc.hidden) {
                flush({force: true, keepalive: true});
            }
        });
        var onOnline = safe(function() {
            flush({force: true});
        });

        win.addEventListener('pagehide', onPageHide);
        win.addEventListener('online', onOnline);
        doc.addEventListener('visibilitychange', onVisibility);

        // Lo que quedó pendiente de la página anterior sale enseguida.
        flush({force: true});

        return {stop: stop, flush: flush, queue: queue};
    };

    return {
        /**
         * Punto de entrada desde Moodle (js_call_amd).
         *
         * @param {Object} config
         * @param {number} config.attemptid
         * @param {number} [config.flushms]
         * @return {Object|null} la captura en marcha, o null si no arrancó.
         */
        init: function(config) {
            try {
                var moodleConfig = window.M && window.M.cfg;
                if (!config || !config.attemptid || !moodleConfig) {
                    return null;
                }

                var storage = null;
                try {
                    storage = window.sessionStorage;
                } catch (e) {
                    storage = null;
                }

                return start({
                    win: window,
                    doc: document,
                    storage: storage,
                    attemptId: config.attemptid,
                    flushMs: config.flushms,
                    transport: Transport.create({wwwroot: moodleConfig.wwwroot, sesskey: moodleConfig.sesskey})
                });
            } catch (e) {
                // Si algo falla al arrancar, el alumno rinde como si el complemento no estuviera.
                return null;
            }
        },

        start: start
    };
});
