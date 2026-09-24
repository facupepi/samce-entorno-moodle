/**
 * Pide el consentimiento en la página del cuestionario, ANTES de que Moodle
 * cree el intento.
 *
 * Cuando el alumno toca "Comenzar intento" (o "Continuar"), Moodle crea el
 * intento y avisa al backend antes de cargar la página del examen. Si el aviso
 * de monitoreo aparecía recién ahí, "No acepto" dejaba un intento y una sesión
 * abierta sin eventos. Acá se intercepta el botón: se muestra el aviso, y solo
 * con "Acepto" sigue el envío del formulario. Con "No acepto" el aviso se cierra
 * y el alumno se queda en la página, sin que se haya creado nada.
 *
 * La aceptación queda como pendiente (ver local_samce/consent_notice) hasta que
 * la página del intento la consume y registra consent_accepted, ya con un
 * intento al que colgarlo.
 *
 * REGLA DE ORO: si algo de este módulo falla, no se interpone: el alumno puede
 * comenzar igual, y la página del intento vuelve a pedir el consentimiento (el
 * respaldo de local_samce/consent), así que sin aceptación sigue sin haber
 * captura.
 *
 * @module     local_samce/consent_gate
 * @copyright  SAMCE
 */
define('local_samce/consent_gate', ['local_samce/consent_notice'], function(Notice) {
    'use strict';

    var START_FORM = 'form[action*="/mod/quiz/startattempt.php"]';

    return {
        /**
         * @param {Object} config
         * @param {number} config.cmid el cuestionario.
         * @param {number} [config.unfinishedattemptid] intento en curso del alumno, si lo hay.
         * @param {string} config.noticetitle
         * @param {string} config.noticebody
         * @param {string} config.noticeaccept
         * @param {string} config.noticedecline
         * @param {Object} [deps] solo para pruebas: {now}.
         */
        init: function(config, deps) {
            try {
                if (!config || !config.cmid || !config.noticetitle || !config.noticebody ||
                        !config.noticeaccept || !config.noticedecline) {
                    return;
                }

                var storage = null;
                try {
                    storage = window.localStorage;
                } catch (e) {
                    storage = null;
                }
                var now = (deps && deps.now) || Date.now;

                // Continuar un intento en el que ya aceptó en este navegador: no hay nada que preguntar.
                if (config.unfinishedattemptid && Notice.alreadyAccepted(storage, config.unfinishedattemptid)) {
                    return;
                }

                var doc = document;
                var allowed = false;
                var showing = false;

                var startForm = function(node) {
                    return node && typeof node.closest === 'function' ? node.closest(START_FORM) : null;
                };

                // Muestra el aviso. Puede lanzar (un texto de idioma faltante, un tema
                // del campus que deja el DOM en un estado raro): quien la llama tiene
                // que estar preparado para dejar seguir el envío.
                var ask = function(retry) {
                    if (showing) {
                        return;
                    }
                    showing = true;
                    var close = Notice.show(doc, {
                        title: config.noticetitle,
                        body: config.noticebody,
                        accept: config.noticeaccept,
                        decline: config.noticedecline
                    }, function() {
                        showing = false;
                        // Si guardar la aceptación pendiente falla, igual sigue: la
                        // página del intento vuelve a pedirla.
                        try {
                            Notice.setPending(storage, config.cmid, now());
                        } catch (e) {
                            // Nada más que intentar.
                        }
                        allowed = true;
                        retry();
                    }, function() {
                        // No aceptó: el aviso se cierra y no se crea ningún intento.
                        showing = false;
                        close();
                    });
                };

                // El envío solo se cancela DESPUÉS de haber mostrado el aviso. Si mostrarlo
                // falla, no se interpone: el alumno puede comenzar igual, y la página del
                // intento vuelve a pedir el aviso (el respaldo de local_samce/consent).
                // Antes se cancelaba primero, y una falla dejaba el botón sin hacer nada y
                // sin ningún mensaje (revisión del 24/09/2026, punto 5.1).
                var intercept = function(event, retry) {
                    try {
                        ask(retry);
                    } catch (e) {
                        showing = false;
                        allowed = true;
                        return;
                    }
                    event.preventDefault();
                    event.stopImmediatePropagation();
                };

                // Se intercepta el click en la fase de captura, antes que el JS de
                // Moodle (que con contraseña o chequeos previos abre su propio
                // cuadro), y también el envío del formulario, por si Moodle lo
                // dispara por otro camino.
                doc.addEventListener('click', function(event) {
                    try {
                        if (allowed) {
                            return;
                        }
                        var button = event.target && typeof event.target.closest === 'function' ?
                            event.target.closest('button, input[type="submit"]') : null;
                        if (!button || !startForm(button)) {
                            return;
                        }
                        intercept(event, function() {
                            button.click();
                        });
                    } catch (e) {
                        allowed = true;
                    }
                }, true);

                doc.addEventListener('submit', function(event) {
                    try {
                        var form = startForm(event.target);
                        if (allowed || !form) {
                            return;
                        }
                        intercept(event, function() {
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                            } else {
                                form.submit();
                            }
                        });
                    } catch (e) {
                        allowed = true;
                    }
                }, true);
            } catch (e) {
                // Sin la compuerta el alumno puede comenzar igual, y el respaldo de la página
                // del intento vuelve a pedir el consentimiento.
            }
        }
    };
});
