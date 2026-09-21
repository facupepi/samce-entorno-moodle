/**
 * Engancha los detectores de eventos también dentro de los editores de texto.
 *
 * Las preguntas de ensayo con editor de texto enriquecido usan TinyMCE, que
 * dibuja lo que escribe el alumno dentro de un iframe. Los eventos de teclado,
 * de entrada de texto y de pegado que ocurren adentro de un iframe no llegan a
 * un listener puesto en el documento de la página, así que sin esto el tecleo
 * y el pegado no se verían justo en los ensayos, que es donde más importan.
 * Los iframes de los editores son del mismo origen, así que se puede llegar a
 * su documento.
 *
 * Los editores aparecen después de cargada la página (TinyMCE se inicializa
 * tarde) y pueden reescribir el documento de su iframe, con lo que se pierden
 * los listeners: por eso se vuelve a mirar cuando se agrega un iframe, cuando
 * uno termina de cargar y cada tanto, y se re-engancha si el cuerpo del
 * documento cambió.
 *
 * @module     local_samce/frames
 * @copyright  SAMCE
 */
define('local_samce/frames', [], function() {
    'use strict';

    /** Iframes que viven dentro de una pregunta del cuestionario. */
    var FRAME_SELECTOR = '.que iframe';
    var RESCAN_DELAY_MS = 100;
    var RESCAN_INTERVAL_MS = 2000;

    /**
     * @param {Window} win
     * @param {Document} doc
     * @param {Function} callback se llama con (raíz, iframe) para el documento de la página
     *        (iframe en null) y para cada iframe de editor; puede devolver una función que
     *        deshace lo que enganchó.
     * @return {Object} {stop}
     */
    var watch = function(win, doc, callback) {
        var attached = new Map();
        var scheduled = null;
        var stopped = false;

        var mainCleanup = callback(doc, null);

        var rescan = function() {
            scheduled = null;
            if (stopped) {
                return;
            }

            attached.forEach(function(entry, frame) {
                if (!doc.contains(frame)) {
                    if (typeof entry.cleanup === 'function') {
                        entry.cleanup();
                    }
                    attached.delete(frame);
                }
            });

            var frames = doc.querySelectorAll(FRAME_SELECTOR);
            Array.prototype.forEach.call(frames, function(frame) {
                var body = null;
                try {
                    body = frame.contentDocument && frame.contentDocument.body;
                } catch (e) {
                    return;
                }
                if (!body) {
                    return;
                }

                var entry = attached.get(frame);
                if (entry && entry.body === body) {
                    return;
                }
                if (entry && typeof entry.cleanup === 'function') {
                    entry.cleanup();
                }
                attached.set(frame, {body: body, cleanup: callback(body, frame)});
            });
        };

        var schedule = function() {
            if (!stopped && scheduled === null) {
                scheduled = win.setTimeout(rescan, RESCAN_DELAY_MS);
            }
        };

        var observer = null;
        if (typeof win.MutationObserver === 'function') {
            observer = new win.MutationObserver(schedule);
            observer.observe(doc.documentElement, {childList: true, subtree: true});
        }
        // Los eventos de carga no burbujean, pero se pueden atrapar en la fase de captura.
        doc.addEventListener('load', schedule, true);
        var interval = win.setInterval(rescan, RESCAN_INTERVAL_MS);

        rescan();

        return {
            stop: function() {
                stopped = true;
                if (observer) {
                    observer.disconnect();
                }
                doc.removeEventListener('load', schedule, true);
                win.clearInterval(interval);
                if (scheduled !== null) {
                    win.clearTimeout(scheduled);
                }
                attached.forEach(function(entry) {
                    if (typeof entry.cleanup === 'function') {
                        entry.cleanup();
                    }
                });
                attached.clear();
                if (typeof mainCleanup === 'function') {
                    mainCleanup();
                }
            }
        };
    };

    return {watch: watch};
});
