/**
 * Aviso de privacidad y aceptación explícita del monitoreo (HU11, RF05).
 *
 * Se carga en vez de local_samce/capture directamente (ver
 * classes/hook_callbacks.php) cuando capture_enabled está prendido. Tapa el
 * examen con un aviso bloqueante hasta que el alumno hace click en "Acepto":
 * recién ahí arranca local_samce/capture, nunca antes (criterio de
 * aceptación: "el sistema no debe iniciar la captura sin la aceptación del
 * alumno").
 *
 * Lo normal es que el alumno ya haya aceptado en la página del cuestionario
 * (ver local_samce/consent_gate), antes de que Moodle cree el intento: acá solo
 * se registra. Este aviso queda como respaldo, para quien llega al intento sin
 * pasar por ahí (un enlace directo, otro navegador, retomar un intento).
 *
 * La aceptación es por intento, no por alumno ni por curso: no se guarda en
 * el servidor un consentimiento "de una vez para siempre". Lo único que
 * persiste es una marca liviana en el propio navegador (localStorage, no
 * sessionStorage: tiene que sobrevivir a que se cierre la pestaña o se corte
 * la conexión y el alumno retome el mismo intento), para no volver a
 * preguntar en cada pregunta de un cuestionario paginado. El registro que
 * pide el criterio de aceptación 3 ("con marca temporal") es el propio
 * evento consent_accepted que local_samce/capture manda la primera vez.
 *
 * Si el alumno no acepta ("No acepto"), se lo devuelve a la página del
 * cuestionario y la captura no arranca. Cerrar la pestaña sin aceptar tiene
 * el mismo efecto: sin click no hay captura, y al volver se le pregunta de
 * nuevo.
 *
 * REGLA DE ORO: si algo de este módulo falla, el alumno no puede quedar sin
 * poder rendir por un error nuestro, pero tampoco se lo monitorea sin aviso.
 * Ante cualquier excepción o texto faltante el aviso se saca, el examen queda
 * usable y la captura NO arranca (falla cerrado para la captura y abierto
 * para el examen).
 *
 * @module     local_samce/consent
 * @copyright  SAMCE
 */
define('local_samce/consent', ['local_samce/capture', 'local_samce/consent_notice', 'local_samce/transport'],
function(Capture, Notice, Transport) {
    'use strict';

    var ACCEPT_ATTEMPTS = 3;
    var ACCEPT_RETRY_MS = 1500;

    /**
     * Deja constancia en el servidor de que el alumno vio el aviso de este
     * intento y, solo si quedó registrada, arranca la captura. Sin esa
     * constancia Moodle no acepta ningún evento del intento (send_events.php),
     * así que arrancar igual sería capturar para nada. Si el registro falla, no
     * se captura en esta página (falla cerrado) y se vuelve a intentar en la
     * siguiente.
     *
     * @param {Object} config
     * @param {Object} extra campos que se suman a la config de la captura.
     */
    var startCapture = function(config, extra) {
        var moodleConfig = window.M && window.M.cfg;
        if (!moodleConfig) {
            return;
        }
        var transport = Transport.create({wwwroot: moodleConfig.wwwroot, sesskey: moodleConfig.sesskey});

        var attempt = function(left) {
            transport.accept(config.attemptid).then(function(status) {
                if (status === 'ok') {
                    Capture.init(Object.assign({}, config, extra));
                } else if (status === 'retry' && left > 1) {
                    window.setTimeout(function() {
                        attempt(left - 1);
                    }, ACCEPT_RETRY_MS);
                }
                // 'disabled' o 'rejected': no hay monitoreo para este intento.
            }).catch(function() {
                // Nada que mostrarle al alumno: sin constancia no hay captura.
            });
        };
        attempt(ACCEPT_ATTEMPTS);
    };

    /**
     * Devuelve al alumno a la página del cuestionario (o a la anterior si no
     * se conoce). El intento queda en curso en Moodle: al volver a entrar se
     * le pregunta otra vez.
     *
     * @param {string} [url]
     */
    var leaveExam = function(url) {
        try {
            if (url) {
                window.location.assign(url);
            } else {
                window.history.back();
            }
        } catch (e) {
            // Nada más que intentar.
        }
    };

    return {
        /**
         * Punto de entrada desde Moodle (js_call_amd), en vez de
         * local_samce/capture cuando hace falta pedir el consentimiento.
         *
         * @param {Object} config
         * @param {number} config.attemptid
         * @param {number} [config.cmid] el cuestionario, para consumir una aceptación pendiente.
         * @param {number} [config.flushms]
         * @param {string} config.noticetitle
         * @param {string} config.noticebody
         * @param {string} config.noticeaccept
         * @param {string} config.noticedecline
         * @param {string} [config.declineurl] a dónde vuelve el alumno si no acepta.
         * @param {string} [config.indicatortext]
         * @param {Object} [deps] solo para pruebas: {leave}.
         */
        init: function(config, deps) {
            var closeNotice = null;
            try {
                if (!config || !config.attemptid) {
                    return;
                }
                var leave = (deps && deps.leave) || leaveExam;

                var storage = null;
                try {
                    storage = window.localStorage;
                } catch (e) {
                    storage = null;
                }

                if (Notice.alreadyAccepted(storage, config.attemptid)) {
                    // Puede ser un intento que aceptó antes de que existiera la
                    // constancia del servidor: se registra ahora.
                    startCapture(config, {});
                    return;
                }

                // El alumno ya aceptó en la página del cuestionario, antes de que
                // Moodle creara este intento: la aceptación estaba pendiente y
                // acá, con el intento ya creado, se registra.
                if (Notice.takePending(storage, config.cmid, Date.now())) {
                    Notice.markAccepted(storage, config.attemptid);
                    startCapture(config, {consentacceptedat: true});
                    return;
                }

                if (!config.noticetitle || !config.noticebody || !config.noticeaccept || !config.noticedecline) {
                    // Sin texto no hay aviso que mostrar, y sin aviso no hay
                    // captura: el examen queda usable pero sin monitoreo.
                    return;
                }

                closeNotice = Notice.show(document, {
                    title: config.noticetitle,
                    body: config.noticebody,
                    accept: config.noticeaccept,
                    decline: config.noticedecline
                }, function() {
                    Notice.markAccepted(storage, config.attemptid);
                    startCapture(config, {consentacceptedat: true});
                }, function() {
                    leave(config.declineurl);
                });
            } catch (e) {
                // Si el aviso falla, el alumno rinde igual pero SIN captura:
                // no hubo aceptación, así que no hay monitoreo.
                try {
                    if (closeNotice) {
                        closeNotice();
                    } else {
                        document.body.style.overflow = '';
                    }
                } catch (e2) {
                    // Nada más que intentar.
                }
            }
        }
    };
});
