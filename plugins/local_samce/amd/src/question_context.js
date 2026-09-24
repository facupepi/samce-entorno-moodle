/**
 * Ubica a qué pregunta del cuestionario pertenece un elemento de la página.
 *
 * Moodle dibuja cada pregunta como un contenedor con la clase "que", seguida
 * de la de su tipo (multichoice, essay, shortanswer…), y con un id de la forma
 * question-<uso>-<posición>. Los eventos que ocurren dentro de una pregunta
 * llevan esa posición (slot) y ese tipo (qtype), para que el análisis sepa en
 * qué pregunta pasó cada cosa: una pregunta de opción múltiple no tiene
 * tecleo, y no tenerlo ahí no es una desviación.
 *
 * @module     local_samce/question_context
 * @copyright  SAMCE
 */
define('local_samce/question_context', [], function() {
    'use strict';

    /**
     * Forma que puede tener un qtype: es texto que sale de una clase CSS de la
     * página, el único campo libre que el navegador puede hacer crecer, y un
     * valor largo o raro hacía que el plugin descartara el lote entero sin
     * dejar rastro (punto 26 de la revisión externa del 23/09/2026). Los tipos
     * reales de Moodle (multichoice, essay, shortanswer, ddwtos…) entran de
     * sobra; lo que no encaja se omite en vez de mandarse.
     */
    var QTYPE_PATTERN = /^[a-z][a-z0-9_]{0,31}$/;

    /**
     * @param {Node|null} node un elemento cualquiera de la página.
     * @param {Element|null} [frame] el iframe que contiene al nodo, si el nodo vive en el
     *        documento de un editor de texto: desde adentro no se ve la pregunta, se busca
     *        a partir del iframe.
     * @return {Object} {slot, qtype}, con solo lo que se pudo determinar; vacío si el nodo
     *         no está dentro de una pregunta.
     */
    var of = function(node, frame) {
        var start = frame || node;
        if (start && start.nodeType === 3) {
            start = start.parentElement;
        }
        var question = start && typeof start.closest === 'function' ? start.closest('.que') : null;
        if (!question) {
            return {};
        }

        var result = {};

        var match = /^question-\d+-(\d+)$/.exec(question.id || '');
        if (match) {
            result.slot = parseInt(match[1], 10);
        }

        var classes = (question.className || '').split(/\s+/);
        var index = classes.indexOf('que');
        if (index !== -1 && QTYPE_PATTERN.test(classes[index + 1] || '')) {
            result.qtype = classes[index + 1];
        }

        return result;
    };

    /**
     * Devuelve una copia de data con slot y qtype agregados, si se conocen.
     *
     * @param {Object} data
     * @param {Object} context lo que devuelve of()
     * @return {Object}
     */
    var withContext = function(data, context) {
        var result = {};
        if (context.slot !== undefined) {
            result.slot = context.slot;
        }
        if (context.qtype !== undefined) {
            result.qtype = context.qtype;
        }
        Object.keys(data).forEach(function(key) {
            result[key] = data[key];
        });
        return result;
    };

    return {of: of, withContext: withContext};
});
