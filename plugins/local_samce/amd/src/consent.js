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
     * @param {Object} texts {title, body, accept, decline}
     * @param {Function} onAccept
     * @param {Function} onDecline
     * @return {Function} quita el aviso y devuelve el scroll de la página.
     */
    var showNotice = function(doc, texts, onAccept, onDecline) {
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

        var close = function() {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            doc.body.style.overflow = '';
        };

        var button = doc.createElement('button');
        button.type = 'button';
        button.textContent = texts.accept;
        button.style.cssText = 'background:#2a6b2a;color:#fff;border:0;border-radius:4px;' +
            'padding:10px 22px;font-size:15px;font-weight:600;cursor:pointer;';
        button.addEventListener('click', function() {
            close();
            onAccept();
        });

        var decline = doc.createElement('button');
        decline.type = 'button';
        decline.textContent = texts.decline;
        decline.style.cssText = 'background:#fff;color:#1a1a1a;border:1px solid #888;border-radius:4px;' +
            'padding:10px 22px;font-size:15px;font-weight:600;cursor:pointer;margin-left:12px;';
        decline.addEventListener('click', function() {
            // No se saca el aviso: el alumno se va de la página, y hasta que
            // la navegación termine el examen sigue tapado.
            onDecline();
        });

        box.appendChild(title);
        box.appendChild(body);
        box.appendChild(button);
        box.appendChild(decline);
        overlay.appendChild(box);

        // El bloqueo del scroll va después de insertar el aviso: si algo de
        // arriba falla, la página no queda trabada.
        doc.body.appendChild(overlay);
        doc.body.style.overflow = 'hidden';
        try {
            button.focus();
        } catch (e) {
            // El foco es un detalle: sin él el aviso funciona igual.
        }
        return close;
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

                if (alreadyAccepted(storage, config.attemptid)) {
                    Capture.init(config);
                    return;
                }

                if (!config.noticetitle || !config.noticebody || !config.noticeaccept || !config.noticedecline) {
                    // Sin texto no hay aviso que mostrar, y sin aviso no hay
                    // captura: el examen queda usable pero sin monitoreo.
                    return;
                }

                closeNotice = showNotice(document, {
                    title: config.noticetitle,
                    body: config.noticebody,
                    accept: config.noticeaccept,
                    decline: config.noticedecline
                }, function() {
                    markAccepted(storage, config.attemptid);
                    Capture.init(Object.assign({}, config, {consentacceptedat: true}));
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
