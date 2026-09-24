/**
 * Aviso de navegador no admitido.
 *
 * Los exámenes monitoreados con SAMCE se rinden en Google Chrome, en una
 * computadora. Si el servidor detecta otro navegador o un teléfono o tablet
 * (ver classes/browser_check.php), este módulo tapa el examen con un aviso sin
 * salida más que volver a la página del cuestionario. No arranca ninguna
 * captura: sin un navegador admitido no hay monitoreo, y sin monitoreo no se
 * rinde.
 *
 * Es el mismo criterio que consent.js: el aviso es un cartel encima de la
 * página, no un bloqueo del lado del servidor, así que lo puede saltear quien
 * desactive el JS o lo edite en las herramientas del navegador. Ordena el uso
 * normal.
 *
 * @module     local_samce/blocked
 * @copyright  SAMCE
 */
define('local_samce/blocked', [], function() {
    'use strict';

    var goBack = function(url) {
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
         * @param {Object} config
         * @param {string} config.title
         * @param {string} config.body
         * @param {string} config.back texto del botón para volver.
         * @param {string} [config.backurl] a dónde vuelve; sin él, a la página anterior.
         * @param {Object} [deps] solo para pruebas: {leave}.
         */
        init: function(config, deps) {
            try {
                if (!config || !config.title || !config.body || !config.back) {
                    return;
                }
                var leave = (deps && deps.leave) || goBack;
                var doc = document;

                var overlay = doc.createElement('div');
                overlay.setAttribute('role', 'alertdialog');
                overlay.setAttribute('aria-modal', 'true');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483647;' +
                    'background:rgba(20,20,20,.96);display:flex;align-items:center;' +
                    'justify-content:center;padding:24px;';

                var box = doc.createElement('div');
                box.style.cssText = 'background:#fff;color:#1a1a1a;max-width:520px;width:100%;' +
                    'border-radius:8px;padding:28px 32px;box-shadow:0 8px 30px rgba(0,0,0,.4);' +
                    'font:15px/1.5 -apple-system,BlinkMacSystemFont,sans-serif;';

                var title = doc.createElement('h2');
                title.style.cssText = 'margin:0 0 12px;font-size:19px;';
                title.textContent = config.title;

                var body = doc.createElement('p');
                body.style.cssText = 'white-space:pre-line;margin:0 0 20px;';
                body.textContent = config.body;

                var button = doc.createElement('button');
                button.type = 'button';
                button.textContent = config.back;
                button.style.cssText = 'background:#7a1f1f;color:#fff;border:0;border-radius:4px;' +
                    'padding:10px 22px;font-size:15px;font-weight:600;cursor:pointer;';
                button.addEventListener('click', function() {
                    leave(config.backurl);
                });

                box.appendChild(title);
                box.appendChild(body);
                box.appendChild(button);
                overlay.appendChild(box);
                doc.body.appendChild(overlay);
                doc.body.style.overflow = 'hidden';
            } catch (e) {
                try {
                    document.body.style.overflow = '';
                } catch (e2) {
                    // Nada más que intentar.
                }
            }
        }
    };
});
