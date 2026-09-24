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
 * La aceptación es por intento, no por alumno ni por curso: no se guarda en
 * el servidor un consentimiento "de una vez para siempre". Lo único que
 * persiste es una marca liviana en el propio navegador (localStorage, no
 * sessionStorage: tiene que sobrevivir a que se cierre la pestaña o se corte
 * la conexión y el alumno retome el mismo intento), para no volver a
 * preguntar en cada pregunta de un cuestionario paginado. El registro que
 * pide el criterio de aceptación 3 ("con marca temporal") es el propio
 * evento consent_accepted que local_samce/capture manda la primera vez.
 *
 * REGLA DE ORO, igual que en capture.js: si algo de este módulo falla, el
 * alumno no puede quedar sin poder rendir por un error nuestro. Cualquier
 * excepción cae al arranque directo de la captura, sin aviso.
 *
 * @module     local_samce/consent
 * @copyright  SAMCE
 */
define('local_samce/consent', ['local_samce/capture'], function(Capture) {
    'use strict';

    var storageKey = function(attemptId) {
        return 'local_samce:consent:' + attemptId;
    };

    var alreadyAccepted = function(storage, attemptId) {
        try {
            return !!storage && storage.getItem(storageKey(attemptId)) === '1';
        } catch (e) {
            return false;
        }
    };

    var markAccepted = function(storage, attemptId) {
        try {
            if (storage) {
                storage.setItem(storageKey(attemptId), '1');
            }
        } catch (e) {
            // Sin almacenamiento (modo privado, cuota llena): sigue
            // funcionando, solo que va a volver a preguntar en la próxima
            // pregunta de este mismo intento.
        }
    };

    /**
     * Arma el aviso bloqueante. No usa innerHTML con el texto de Moodle: todo
     * va por textContent, así que no hace falta sanitizar nada.
     *
     * @param {Document} doc
     * @param {Object} texts {title, body, accept}
     * @param {Function} onAccept
     */
    var showNotice = function(doc, texts, onAccept) {
        var overlay = doc.createElement('div');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483647;' +
            'background:rgba(20,20,20,.92);display:flex;align-items:center;' +
            'justify-content:center;padding:24px;';

        var box = doc.createElement('div');
        box.style.cssText = 'background:#fff;color:#1a1a1a;max-width:560px;width:100%;' +
            'border-radius:8px;padding:28px 32px;box-shadow:0 8px 30px rgba(0,0,0,.4);' +
            'font:15px/1.5 -apple-system,BlinkMacSystemFont,sans-serif;max-height:80vh;overflow:auto;';

        var title = doc.createElement('h2');
        title.style.cssText = 'margin:0 0 12px;font-size:19px;';
        title.textContent = texts.title;

        var body = doc.createElement('p');
        body.style.cssText = 'white-space:pre-line;margin:0 0 20px;';
        body.textContent = texts.body;

        var button = doc.createElement('button');
        button.type = 'button';
        button.textContent = texts.accept;
        button.style.cssText = 'background:#2a6b2a;color:#fff;border:0;border-radius:4px;' +
            'padding:10px 22px;font-size:15px;font-weight:600;cursor:pointer;';
        button.addEventListener('click', function() {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            doc.body.style.overflow = '';
            onAccept();
        });

        box.appendChild(title);
        box.appendChild(body);
        box.appendChild(button);
        overlay.appendChild(box);

        doc.body.style.overflow = 'hidden';
        doc.body.appendChild(overlay);
        button.focus();
    };

    return {
        /**
         * Punto de entrada desde Moodle (js_call_amd), en vez de
         * local_samce/capture cuando hace falta pedir el consentimiento.
         *
         * @param {Object} config
         * @param {number} config.attemptid
         * @param {number} [config.flushms]
         * @param {string} config.noticetitle
         * @param {string} config.noticebody
         * @param {string} config.noticeaccept
         * @param {string} [config.indicatortext]
         */
        init: function(config) {
            try {
                if (!config || !config.attemptid) {
                    return;
                }

                var storage = null;
                try {
                    storage = window.localStorage;
                } catch (e) {
                    storage = null;
                }

                if (alreadyAccepted(storage, config.attemptid)) {
                    Capture.init(config);
                    return;
                }

                if (!config.noticetitle || !config.noticebody || !config.noticeaccept) {
                    // Sin texto no hay nada bloqueante que mostrar: mejor
                    // dejar rendir sin pedir aceptación que trabar el examen.
                    Capture.init(config);
                    return;
                }

                showNotice(document, {
                    title: config.noticetitle,
                    body: config.noticebody,
                    accept: config.noticeaccept
                }, function() {
                    markAccepted(storage, config.attemptid);
                    Capture.init(Object.assign({}, config, {consentacceptedat: true}));
                });
            } catch (e) {
                // Si el aviso falla, el alumno rinde como si el complemento
                // no estuviera (mismo criterio que capture.js).
                try {
                    Capture.init(config);
                } catch (e2) {
                    // Nada más que intentar.
                }
            }
        }
    };
});
