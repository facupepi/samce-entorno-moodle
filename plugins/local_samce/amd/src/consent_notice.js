/**
 * Piezas compartidas del aviso de monitoreo (HU11): el cartel bloqueante y las
 * marcas de aceptación guardadas en el navegador.
 *
 * Las usan dos módulos: local_samce/consent_gate, que lo muestra en la página
 * del cuestionario ANTES de que Moodle cree el intento, y local_samce/consent,
 * que lo muestra en la página del intento si el alumno no lo vio antes (por
 * ejemplo, si entró directo por un enlace al intento).
 *
 * Marcas, ambas en localStorage (sobrevive a cerrar la pestaña o a un corte de
 * conexión):
 * - por intento (`local_samce:consent:<attemptid>`): ya aceptó en este intento.
 * - pendiente por cuestionario (`local_samce:consent-pending:<cmid>`): aceptó
 *   en la página del cuestionario y todavía no hay intento al que colgar el
 *   registro. La página del intento la consume y emite consent_accepted.
 *
 * @module     local_samce/consent_notice
 * @copyright  SAMCE
 */
define('local_samce/consent_notice', [], function() {
    'use strict';

    /** Cuánto dura una aceptación pendiente: lo que tarda Moodle en crear el intento y cargarlo. */
    var PENDING_TTL_MS = 30 * 60 * 1000;

    var attemptKey = function(attemptId) {
        return 'local_samce:consent:' + attemptId;
    };

    var pendingKey = function(cmid) {
        return 'local_samce:consent-pending:' + cmid;
    };

    var alreadyAccepted = function(storage, attemptId) {
        try {
            return !!storage && storage.getItem(attemptKey(attemptId)) === '1';
        } catch (e) {
            return false;
        }
    };

    var markAccepted = function(storage, attemptId) {
        try {
            if (storage) {
                storage.setItem(attemptKey(attemptId), '1');
            }
        } catch (e) {
            // Sin almacenamiento (modo privado, cuota llena): sigue
            // funcionando, solo que va a volver a preguntar.
        }
    };

    var setPending = function(storage, cmid, now) {
        try {
            if (storage) {
                storage.setItem(pendingKey(cmid), String(now));
            }
        } catch (e) {
            // Sin almacenamiento: la página del intento va a volver a preguntar.
        }
    };

    /**
     * Consume la aceptación pendiente de un cuestionario, si hay una y no
     * venció. La saca, para que valga una sola vez.
     *
     * @return {boolean}
     */
    var takePending = function(storage, cmid, now) {
        try {
            if (!storage || !cmid) {
                return false;
            }
            var raw = storage.getItem(pendingKey(cmid));
            if (raw === null) {
                return false;
            }
            storage.removeItem(pendingKey(cmid));
            var at = parseInt(raw, 10);
            return !isNaN(at) && now - at >= 0 && now - at <= PENDING_TTL_MS;
        } catch (e) {
            return false;
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
        var errorLine = doc.createElement('p');
        errorLine.setAttribute('role', 'alert');
        errorLine.style.cssText = 'display:none;margin:0 0 14px;color:#7a1f1f;font-weight:600;';

        button.addEventListener('click', function() {
            if (!texts.manual) {
                close();
                onAccept();
                return;
            }
            // Modo manual: el aviso queda hasta que quien lo llamó confirma. Sirve
            // para esperar al servidor antes de dejar seguir, y para mostrar un error
            // si no pudo registrar la lectura.
            button.disabled = true;
            errorLine.style.display = 'none';
            onAccept({
                close: close,
                fail: function(message) {
                    button.disabled = false;
                    errorLine.textContent = message;
                    errorLine.style.display = 'block';
                }
            });
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
        box.appendChild(errorLine);
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

    return {
        show: showNotice,
        alreadyAccepted: alreadyAccepted,
        markAccepted: markAccepted,
        setPending: setPending,
        takePending: takePending
    };
});
