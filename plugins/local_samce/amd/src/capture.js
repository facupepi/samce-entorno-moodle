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
    'local_samce/indicator',
    'local_samce/sig_focus',
    'local_samce/sig_input',
    'local_samce/sig_pointer',
    'local_samce/sig_window'
], function(Queue, Transport, Frames, Indicator, SigFocus, SigInput, SigPointer, SigWindow) {
    'use strict';

    var DEFAULT_FLUSH_MS = 5000;
    var BATCH_SIZE = 100;
    /**
     * Tope de bytes del data de un lote. El mismo límite de 128 KiB lo aplican
     * el plugin (event_batch::MAX_BATCH_DATA_BYTES) y el backend
     * (maxBatchDataBytes); acá se corta más abajo, con margen, para que un lote
     * nunca se rechace por tamaño y se pierda entero.
     */
    var BATCH_MAX_BYTES = 96 * 1024;
    /** Ventana al azar antes del vaciado por reconexión, para que 30 alumnos no le peguen juntos al backend. */
    var ONLINE_JITTER_MS = 3000;
    /** Al cerrar la página el navegador limita el tamaño de lo que puede terminar de enviar. */
    var UNLOAD_BATCH_SIZE = 40;
    var BACKOFF_BASE_MS = 2000;
    var BACKOFF_MAX_MS = 30000;
    /** Tope de lotes seguidos en un mismo vaciado, para no monopolizar la página. */
    var MAX_BATCHES_PER_FLUSH = 5;
    /** Cuántos envíos fallidos seguidos hacen falta para dar por perdida la conexión. */
    var LOST_AFTER_FAILURES = 2;

    /** Un id corto al azar, con el alfabeto que acepta el backend. */
    var newContextId = function(random) {
        var alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var id = '';
        for (var i = 0; i < 12; i++) {
            id += alphabet.charAt(Math.floor(random() * alphabet.length));
        }
        return id;
    };

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
     * @param {boolean} [options.consentAcceptedAt] el alumno acaba de aceptar
     *        el aviso (HU11): emite el evento consent_accepted. false en las
     *        páginas siguientes del mismo intento, cuando local_samce/consent
     *        ya lo había registrado antes.
     * @param {string} [options.indicatorText] texto del indicador de HU12.
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

        var random = options.random || Math.random;
        // Identifica este contexto de captura (esta carga de página). Vive en una
        // variable del módulo y NO en sessionStorage, porque duplicar una pestaña
        // copia el almacenamiento. Dos pestañas del mismo intento, o el mismo
        // intento reabierto tras cerrar el navegador, tienen ids distintos: el
        // análisis puede separar los flujos en vez de leer el cambio entre
        // pestañas como "se fue del examen" (punto 10 de la revisión externa).
        var contextId = newContextId(random);

        var stopped = false;
        var sending = false;
        var failures = 0;
        var retryAt = 0;
        // Cambiar de pregunta en un cuestionario paginado dispara
        // visibilitychange a oculto y pagehide casi juntos, y los dos
        // vacían con keepalive (a propósito: no esperan a que termine
        // ningún otro envío). Sin esta bandera, cada cambio de pregunta
        // mandaba el mismo lote dos veces por la red — inofensivo desde el
        // fix de dropUpTo, pero tráfico de más en cada examen (punto 2 de
        // la revisión externa del 23/09/2026, la parte de tráfico que
        // quedó pendiente). Se resetea al volver a verse: un ocultamiento
        // futuro tiene que volver a vaciar.
        var keepaliveFlushed = false;

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
            // resize solo sale desde el listener: un alumno que nunca toca la
            // ventana no dejaba ni un ancho y alto en toda la sesión.
            env.emit('resize', {w: win.innerWidth, h: win.innerHeight});
            queue.markProfileSent();
        }

        // HU11 (RF05, criterio 3): la aceptación explícita se registra como un
        // evento más, con su marca temporal (occurred_at/received_at), sin
        // tabla ni endpoint nuevo. Solo llega acá cuando consent.js acaba de
        // recibir el click de "Acepto" — en las páginas siguientes del mismo
        // intento no se repite (ver local_samce/consent).
        if (options.consentAcceptedAt) {
            env.emit('consent_accepted', {});
        }

        // HU12 (RF06): indicador visible y persistente mientras la captura
        // esté realmente activa. Arranca junto con la captura (que, por HU11,
        // nunca arranca sin la aceptación) y se cae en stop().
        var indicator = Indicator.show(doc, options.indicatorText);

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
            indicator.remove();
            win.removeEventListener('pagehide', onPageHide);
            win.removeEventListener('online', onOnline);
            doc.removeEventListener('visibilitychange', onVisibility);
        };

        /**
         * Vacía lo pendiente hacia el servidor.
         *
         * @param {Object} [flushOptions]
         * @param {boolean} [flushOptions.force] ignora la espera de un reintento.
         * @param {boolean} [flushOptions.keepalive] la página se está cerrando u ocultando.
         * @param {boolean} [flushOptions.unloading] la página se está yendo de
         *        verdad (pagehide), a diferencia de solo ocultarse (puede volver).
         * @return {Promise}
         */
        var flush = function(flushOptions) {
            flushOptions = flushOptions || {};
            if (stopped) {
                return Promise.resolve();
            }

            // Con la página cerrándose u ocultándose, los detectores informan ya
            // lo que tienen acumulado (por ejemplo, el tiempo de cada pregunta).
            // Esto corre siempre, aunque el envío de más abajo se termine
            // frenando: lo que una señal encole acá queda guardado igual, y
            // sale en el próximo vaciado que sí llegue a mandar (el de la
            // página siguiente, por ejemplo).
            var sizeBeforeSignals = queue.size();
            signals.forEach(function(signal) {
                safe(signal.flush)({final: !!flushOptions.keepalive, unloading: !!flushOptions.unloading});
            });
            var signalsAdded = queue.size() > sizeBeforeSignals;

            // Lo descartado desde el último vaciado sale como un evento más, para
            // que un hueco en los datos no se lea como inactividad.
            var droppedNow = queue.takeDropped();
            if (droppedNow) {
                env.emit('events_dropped', droppedNow);
                signalsAdded = true;
            }

            // Cambiar de pregunta en un cuestionario paginado dispara
            // visibilitychange a oculto y pagehide casi juntos, y los dos
            // llegan hasta acá con keepalive. Sin este freno, cada cambio de
            // pregunta mandaba el mismo lote dos veces por la red —
            // inofensivo desde dropUpTo, pero tráfico de más en cada examen.
            //
            // Excepción: si la página se está yendo de verdad y las señales
            // acaban de sumar algo (el cierre del tramo de visibilidad, por
            // ejemplo), ese lote no lo mandó el vaciado anterior y en la
            // última navegación del intento no hay página siguiente que lo
            // vacíe. Ahí no se frena.
            var yaVacioPorEsteOcultamiento = flushOptions.keepalive && keepaliveFlushed &&
                !(flushOptions.unloading && signalsAdded);
            if (flushOptions.keepalive) {
                keepaliveFlushed = true;
            }

            // El vaciado final sale aunque haya un envío en curso: la página se
            // está yendo y lo que se acumuló desde entonces no tendría otra
            // oportunidad. Repetir el principio de la cola no hace daño, el
            // backend descarta lo que ya tiene (clave única por sesión y seq).
            if (yaVacioPorEsteOcultamiento || (sending && !flushOptions.keepalive) || queue.size() === 0) {
                return Promise.resolve();
            }
            if (!flushOptions.force && now() < retryAt) {
                return Promise.resolve();
            }

            sending = true;
            var batchesLeft = flushOptions.keepalive ? 1 : MAX_BATCHES_PER_FLUSH;

            var sendNext = function() {
                var batch = queue.peek(flushOptions.keepalive ? UNLOAD_BATCH_SIZE : BATCH_SIZE, BATCH_MAX_BYTES);
                // Cualquier falla del envío, incluso una que lance de forma síncrona, es un reintento.
                return Promise.resolve().then(function() {
                    return options.transport.send(attemptId, batch, {keepalive: !!flushOptions.keepalive, contextId: contextId});
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
                        // El navegador no siempre avisa que se cortó la conexión
                        // (no dispara "offline" si queda otra interfaz de red o si
                        // lo que se perdió es internet): se deduce de los envíos.
                        if (failures >= LOST_AFTER_FAILURES && !queue.isLost()) {
                            queue.setLost(true);
                            env.emit('connection', {online: false, source: 'send'});
                        }
                        // +-25 % al azar: sin él, todos los alumnos que fallaron a la
                        // vez reintentaban a la vez, siempre con el mismo intervalo.
                        var backoff = Math.min(BACKOFF_MAX_MS, BACKOFF_BASE_MS * Math.pow(2, failures - 1));
                        retryAt = now() + Math.round(backoff * (0.75 + random() * 0.5));
                        return;
                    }

                    // 'ok', o 'rejected' (que no mejora reintentando): el lote sale de la
                    // cola. Por seq y no por cantidad, para que confirmar sea idempotente
                    // aunque se superponga con otro vaciado (ver el comentario de dropUpTo).
                    if (batch.length > 0) {
                        queue.dropUpTo(batch[batch.length - 1].seq);
                        // Un lote rechazado se pierde: se anota. Salvo que fuera solo el
                        // aviso de descartes: si el backend lo rechazara, contarlo
                        // generaría otro aviso, y otro, sin fin.
                        if (status === 'rejected' && batch.some(function(e) {
                            return e.type !== 'events_dropped';
                        })) {
                            queue.noteDropped('rejected', batch.length);
                        }
                    }
                    if (queue.isLost()) {
                        queue.setLost(false);
                        env.emit('connection', {online: true, source: 'send'});
                    }
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
            flush({force: true, keepalive: true, unloading: true});
        });
        var onVisibility = safe(function() {
            if (doc.hidden) {
                flush({force: true, keepalive: true});
            } else {
                // Volvió a verse: un ocultamiento futuro tiene que volver a vaciar.
                keepaliveFlushed = false;
            }
        });
        var onOnline = safe(function() {
            // Es el único camino realmente sincronizado (force saltea retryAt): al
            // volver la red de todos a la vez, cada uno espera un rato distinto.
            win.setTimeout(safe(function() {
                flush({force: true});
            }), Math.floor(random() * ONLINE_JITTER_MS));
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
         * @param {boolean} [config.consentacceptedat] ver options.consentAcceptedAt de start().
         * @param {string} [config.indicatortext] ver options.indicatorText de start().
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
                    consentAcceptedAt: !!config.consentacceptedat,
                    indicatorText: config.indicatortext,
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
