/**
 * Cola de eventos de la captura de SAMCE.
 *
 * Junta los eventos hasta que se envían, y sobrevive a la recarga de la
 * página: cada pregunta de un cuestionario paginado recarga la página
 * completa, así que lo que todavía no salió tiene que quedar guardado del
 * lado del navegador (sessionStorage) y seguir de largo en la página
 * siguiente. Un evento solo sale de la cola cuando el servidor confirmó que
 * lo recibió; si el envío falla, sigue ahí para el reintento.
 *
 * Cada evento lleva un número (seq) creciente y único. Sirve de clave de
 * idempotencia en el backend: si un lote llega dos veces, por un reintento o
 * porque el navegador se cerró antes de saber si el primer envío salió, los
 * eventos repetidos no se duplican. El número parte de la hora, no de cero,
 * para que dos pestañas del mismo intento, o un sessionStorage vaciado a
 * mitad del examen, no reutilicen números ya usados.
 *
 * @module     local_samce/queue
 * @copyright  SAMCE
 */
define('local_samce/queue', [], function() {
    'use strict';

    /** Tope de eventos pendientes: si el envío falla mucho tiempo, se
     * descartan los más viejos antes que crecer sin límite. */
    var DEFAULT_MAX_PENDING = 500;

    /** El evento tal como sale hacia el servidor: sin la marca interna de no descartable. */
    var plain = function(event) {
        return {seq: event.seq, t: event.t, type: event.type, data: event.data};
    };

    /** Bytes UTF-8 de un texto: lo que cuenta el servidor, no la cantidad de caracteres. */
    var utf8Length = function(text) {
        try {
            return unescape(encodeURIComponent(text)).length;
        } catch (e) {
            return text.length;
        }
    };

    /**
     * @param {Object} options
     * @param {Storage|null} options.storage sessionStorage, o null si no está disponible.
     * @param {string} options.key clave bajo la que se guarda el estado del intento.
     * @param {Function} [options.now] reloj en milisegundos (para pruebas).
     * @param {Function} [options.random] número al azar entre 0 y 1 (para pruebas).
     * @param {number} [options.maxPending]
     * @param {number} [options.saveDelayMs] espera antes de escribir en el almacenamiento
     *        (0 = en el acto). Con la cola llena, cada evento reescribía la cola entera
     *        (~72 KB); con una espera se juntan las escrituras. persist() fuerza la
     *        escritura y hay que llamarlo antes de que la página se vaya.
     * @param {Function} [options.setTimeout] para pruebas.
     * @return {Object}
     */
    var create = function(options) {
        var storage = options.storage || null;
        var key = options.key;
        var now = options.now || Date.now;
        var random = options.random || Math.random;
        var maxPending = options.maxPending || DEFAULT_MAX_PENDING;
        var saveDelayMs = options.saveDelayMs || 0;
        var schedule = options.setTimeout || (typeof setTimeout === 'function' ? setTimeout : null);
        var dirty = false;
        var saveScheduled = false;
        // Espera creciente del reintento, para que sobreviva a la recarga de la
        // página: en un examen paginado cada pregunta recarga, y con la espera
        // guardada en una variable del módulo nunca llegaba a crecer.
        var retry = {failures: 0, at: 0};

        var last = 0;
        var pending = [];
        var profileSent = false;
        var lost = false;
        // Cuántos eventos se descartaron sin llegar a salir, por causa. Sin esto
        // el hueco es indetectable en los datos (seq no es consecutivo) y se lee
        // como "el alumno no hizo nada" (punto 9 de la revisión externa del 23/09/2026).
        var dropped = {overflow: 0, rejected: 0};

        var write = function() {
            dirty = false;
            if (!storage) {
                return;
            }
            try {
                storage.setItem(key, JSON.stringify({
                    last: last, pending: pending, profileSent: profileSent, lost: lost, dropped: dropped, retry: retry
                }));
            } catch (e) {
                // Sin almacenamiento (modo privado, cuota llena) la cola sigue funcionando en memoria.
            }
        };

        var save = function() {
            if (!saveDelayMs || !schedule) {
                write();
                return;
            }
            dirty = true;
            if (!saveScheduled) {
                saveScheduled = true;
                schedule(function() {
                    saveScheduled = false;
                    if (dirty) {
                        write();
                    }
                }, saveDelayMs);
            }
        };

        var load = function() {
            if (!storage) {
                return;
            }
            try {
                var raw = storage.getItem(key);
                if (!raw) {
                    return;
                }
                var state = JSON.parse(raw);
                last = typeof state.last === 'number' ? state.last : 0;
                pending = Array.isArray(state.pending) ? state.pending : [];
                profileSent = state.profileSent === true;
                lost = state.lost === true;
                if (state.retry && typeof state.retry === 'object') {
                    retry = {
                        failures: Math.max(0, parseInt(state.retry.failures, 10) || 0),
                        at: Math.max(0, Number(state.retry.at) || 0)
                    };
                }
                if (state.dropped && typeof state.dropped === 'object') {
                    dropped = {
                        overflow: Math.max(0, parseInt(state.dropped.overflow, 10) || 0),
                        rejected: Math.max(0, parseInt(state.dropped.rejected, 10) || 0)
                    };
                }
            } catch (e) {
                last = 0;
                pending = [];
                profileSent = false;
                lost = false;
                dropped = {overflow: 0, rejected: 0};
                retry = {failures: 0, at: 0};
            }
        };

        var nextSeq = function() {
            var candidate = Math.floor(now()) * 1000 + Math.floor(random() * 1000);
            last = Math.max(last + 1, candidate);
            return last;
        };

        load();

        return {
            /**
             * Agrega un evento al final de la cola.
             *
             * @param {string} type
             * @param {Object} [data]
             * @param {Object} [pushOptions]
             * @param {boolean} [pushOptions.keep] el evento no se descarta al desbordarse la
             *        cola mientras haya otros que sí. Es para los que pasan una sola vez por
             *        intento y no se pueden volver a generar: el perfil del navegador, el
             *        tamaño inicial de la ventana y la constancia del aviso. Cuando la cola
             *        se llena se recorta por lo más viejo, que justamente es eso.
             */
            push: function(type, data, pushOptions) {
                var event = {seq: nextSeq(), t: Math.floor(now()), type: type, data: data || {}};
                if (pushOptions && pushOptions.keep) {
                    event.k = 1;
                }
                pending.push(event);
                while (pending.length > maxPending) {
                    var index = 0;
                    for (var i = 0; i < pending.length; i++) {
                        if (!pending[i].k) {
                            index = i;
                            break;
                        }
                    }
                    pending.splice(index, 1);
                    dropped.overflow += 1;
                }
                save();
            },

            /**
             * Devuelve, sin sacarlos, los primeros eventos pendientes.
             *
             * @param {number} max cantidad máxima de eventos.
             * @param {number} [maxBytes] tope de bytes del data del lote. Se corta
             *        el lote antes de pasarse, para que el plugin y el backend nunca
             *        rechacen uno por tamaño; el primer evento entra siempre, para
             *        que uno grande no trabe la cola.
             * @return {Object[]}
             */
            peek: function(max, maxBytes) {
                if (!maxBytes) {
                    return pending.slice(0, max).map(plain);
                }
                var batch = [];
                var bytes = 0;
                for (var i = 0; i < pending.length && batch.length < max; i++) {
                    var size = utf8Length(JSON.stringify(pending[i].data || {}));
                    if (batch.length > 0 && bytes + size > maxBytes) {
                        break;
                    }
                    bytes += size;
                    batch.push(plain(pending[i]));
                }
                return batch;
            },

            /**
             * Anota eventos descartados sin haber salido.
             *
             * @param {string} cause 'overflow' o 'rejected'.
             * @param {number} count
             */
            noteDropped: function(cause, count) {
                if ((cause === 'overflow' || cause === 'rejected') && count > 0) {
                    dropped[cause] += count;
                    save();
                }
            },

            /**
             * Devuelve lo descartado desde la última vez ({count, overflow,
             * rejected}, solo con las causas que hubo) y lo pone en cero, o
             * null si no se descartó nada.
             *
             * @return {Object|null}
             */
            takeDropped: function() {
                var total = dropped.overflow + dropped.rejected;
                if (total === 0) {
                    return null;
                }
                var result = {count: total};
                ['overflow', 'rejected'].forEach(function(cause) {
                    if (dropped[cause] > 0) {
                        result[cause] = dropped[cause];
                    }
                });
                dropped = {overflow: 0, rejected: 0};
                save();
                return result;
            },

            /**
             * Saca los eventos ya confirmados: los que tengan seq <= upToSeq,
             * sin importar cuántos sean ni si siguen estando en el frente.
             *
             * Identificar por seq y no por cantidad es lo que hace esto
             * idempotente. Dos vaciados pueden superponerse de verdad —
             * pagehide y visibilitychange casi juntos en una sola navegación
             * de página disparan cada uno su propio peek/send/drop sobre la
             * misma cola (capture.js deja pasar el de keepalive incluso con
             * uno ya en curso, a propósito, para no perder lo que se
             * acumuló) — y un drop por cantidad, ciego a qué haya en el
             * frente en ese instante, puede terminar sacando eventos que la
             * otra confirmación nunca llegó a mandar. Lo mismo pasa si la
             * cola se desborda (ver el recorte de arriba) entre el peek y
             * este llamado: el frente ya no es el mismo. Buscar por seq no
             * depende de ninguna de las dos cosas.
             *
             * @param {number} upToSeq
             */
            dropUpTo: function(upToSeq) {
                var i = 0;
                while (i < pending.length && pending[i].seq <= upToSeq) {
                    i++;
                }
                pending.splice(0, i);
                save();
            },

            size: function() {
                return pending.length;
            },

            clear: function() {
                pending = [];
                save();
            },

            /** Escribe ya lo pendiente de escribir (ver saveDelayMs). */
            persist: function() {
                if (dirty) {
                    write();
                }
            },

            /** La espera creciente del reintento: {failures, at}. */
            getRetry: function() {
                return {failures: retry.failures, at: retry.at};
            },

            setRetry: function(failures, at) {
                retry = {failures: failures, at: at};
                save();
            },

            profileSent: function() {
                return profileSent;
            },

            markProfileSent: function() {
                profileSent = true;
                save();
            },

            /** Si ya se informó que los envíos vienen fallando (y todavía no que volvieron). */
            isLost: function() {
                return lost;
            },

            setLost: function(value) {
                lost = value === true;
                save();
            }
        };
    };

    return {create: create};
});
