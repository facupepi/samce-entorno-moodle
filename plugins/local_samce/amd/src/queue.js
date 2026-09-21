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

    /**
     * @param {Object} options
     * @param {Storage|null} options.storage sessionStorage, o null si no está disponible.
     * @param {string} options.key clave bajo la que se guarda el estado del intento.
     * @param {Function} [options.now] reloj en milisegundos (para pruebas).
     * @param {Function} [options.random] número al azar entre 0 y 1 (para pruebas).
     * @param {number} [options.maxPending]
     * @return {Object}
     */
    var create = function(options) {
        var storage = options.storage || null;
        var key = options.key;
        var now = options.now || Date.now;
        var random = options.random || Math.random;
        var maxPending = options.maxPending || DEFAULT_MAX_PENDING;

        var last = 0;
        var pending = [];
        var profileSent = false;
        var lost = false;

        var save = function() {
            if (!storage) {
                return;
            }
            try {
                storage.setItem(key, JSON.stringify({last: last, pending: pending, profileSent: profileSent, lost: lost}));
            } catch (e) {
                // Sin almacenamiento (modo privado, cuota llena) la cola sigue funcionando en memoria.
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
            } catch (e) {
                last = 0;
                pending = [];
                profileSent = false;
                lost = false;
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
             */
            push: function(type, data) {
                pending.push({seq: nextSeq(), t: Math.floor(now()), type: type, data: data || {}});
                if (pending.length > maxPending) {
                    pending.splice(0, pending.length - maxPending);
                }
                save();
            },

            /**
             * Devuelve, sin sacarlos, los primeros eventos pendientes.
             *
             * @param {number} max
             * @return {Object[]}
             */
            peek: function(max) {
                return pending.slice(0, max);
            },

            /**
             * Saca los primeros eventos, una vez que el servidor los recibió
             * (o los rechazó para siempre).
             *
             * @param {number} count
             */
            drop: function(count) {
                pending.splice(0, count);
                save();
            },

            size: function() {
                return pending.length;
            },

            clear: function() {
                pending = [];
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
