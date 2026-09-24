/**
 * Envío de los eventos al servidor de Moodle.
 *
 * El navegador del alumno no habla con SAMCE: le entrega el lote a Moodle, a
 * la función externa local_samce_send_events, y es el complemento quien lo
 * firma y lo reenvía. Se llama con fetch y no con core/ajax porque core/ajax
 * no tiene tiempo de espera por defecto y su manejo de errores puede mostrar
 * diálogos, y ninguna falla del monitoreo puede demorar el examen ni
 * ensuciarlo con mensajes ajenos al cuestionario.
 *
 * Nunca lanza ni rechaza: todo resultado, incluidas las fallas, sale como un
 * estado ('ok', 'disabled', 'rejected' o 'retry').
 *
 * @module     local_samce/transport
 * @copyright  SAMCE
 */
define('local_samce/transport', [], function() {
    'use strict';

    var METHOD = 'local_samce_send_events';
    var ACCEPT_METHOD = 'local_samce_accept_notice';
    var DEFAULT_TIMEOUT_MS = 10000;
    var STATUSES = ['ok', 'disabled', 'rejected', 'retry'];

    /**
     * @param {Object} options
     * @param {string} options.wwwroot raíz del sitio de Moodle (M.cfg.wwwroot).
     * @param {string} options.sesskey clave de sesión de Moodle (M.cfg.sesskey).
     * @param {Function} [options.fetch] fetch (para pruebas).
     * @param {number} [options.timeoutMs]
     * @return {Object}
     */
    var create = function(options) {
        var fetchFn = options.fetch || (typeof fetch === 'function' ? function(url, request) {
            return fetch(url, request);
        } : null);
        var timeoutMs = options.timeoutMs || DEFAULT_TIMEOUT_MS;
        var serviceUrl = function(method) {
            return options.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(options.sesskey) +
                '&info=' + method;
        };
        var url = serviceUrl(METHOD);

        /** Traduce la respuesta de Moodle a un estado. Nunca lanza. */
        var statusOf = function(response) {
            if (!response.ok) {
                return Promise.resolve('retry');
            }
            return response.json().then(function(body) {
                var first = Array.isArray(body) ? body[0] : null;
                if (first && first.error === false && first.data && STATUSES.indexOf(first.data.status) !== -1) {
                    return first.data.status;
                }
                // Moodle respondió con un error (sesión vencida, mantenimiento…):
                // no es algo que el alumno tenga que ver, se reintenta más tarde.
                return 'retry';
            });
        };

        return {
            /**
             * Registra, del lado del servidor, que el alumno vio el aviso de
             * monitoreo de este intento. Sin esa constancia Moodle no acepta
             * ningún evento del intento (local_samce_send_events devuelve
             * 'disabled').
             *
             * @param {number} attemptId
             * @return {Promise<string>} 'ok', 'disabled', 'rejected' o 'retry'.
             */
            accept: function(attemptId) {
                if (!fetchFn) {
                    return Promise.resolve('retry');
                }
                try {
                    return fetchFn(serviceUrl(ACCEPT_METHOD), {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        credentials: 'same-origin',
                        body: JSON.stringify([{index: 0, methodname: ACCEPT_METHOD, args: {attemptid: attemptId}}])
                    }).then(statusOf).catch(function() {
                        return 'retry';
                    });
                } catch (e) {
                    return Promise.resolve('retry');
                }
            },

            /**
             * @param {number} attemptId
             * @param {Object[]} events
             * @param {Object} [sendOptions]
             * @param {boolean} [sendOptions.keepalive] pide al navegador terminar el envío
             *        aunque la página se cierre.
             * @param {string} [sendOptions.contextId] identifica este contexto de
             *        captura (una carga de página), para separar pestañas del mismo
             *        intento.
             * @return {Promise<string>} 'ok', 'disabled', 'rejected' o 'retry'.
             */
            send: function(attemptId, events, sendOptions) {
                if (!fetchFn) {
                    return Promise.resolve('retry');
                }

                var controller = typeof AbortController === 'function' ? new AbortController() : null;
                var timer = setTimeout(function() {
                    if (controller) {
                        controller.abort();
                    }
                }, timeoutMs);

                var args = {attemptid: attemptId, events: JSON.stringify(events)};
                if (sendOptions && sendOptions.contextId) {
                    args.contextid = sendOptions.contextId;
                }

                var request = {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    credentials: 'same-origin',
                    body: JSON.stringify([{
                        index: 0,
                        methodname: METHOD,
                        args: args
                    }])
                };
                if (controller) {
                    request.signal = controller.signal;
                }
                if (sendOptions && sendOptions.keepalive) {
                    request.keepalive = true;
                }

                var done = function(status) {
                    clearTimeout(timer);
                    return status;
                };

                try {
                    return fetchFn(url, request).then(statusOf                    ).then(done, function() {
                        return done('retry');
                    });
                } catch (e) {
                    return Promise.resolve(done('retry'));
                }
            }
        };
    };

    return {create: create};
});
