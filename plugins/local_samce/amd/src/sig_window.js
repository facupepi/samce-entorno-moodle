/**
 * Señales de la ventana (RF10): pantalla completa y tamaño de ventana; y la
 * conexión (perdida y recuperada) y el perfil del cliente.
 *
 * PANTALLA COMPLETA. El evento fullscreenchange solo se dispara cuando la
 * pantalla completa se pide desde un script; con F11 el navegador no avisa.
 * Por eso además se compara el tamaño de la ventana con el de la pantalla, y
 * cada cambio lleva su origen: "api" o "size".
 *
 * CONEXIÓN. Explica los huecos de eventos, y evita leer como sospechosa una
 * pausa que en realidad fue una caída de red.
 *
 * PERFIL. Una sola vez por intento, para trazabilidad: familia y versión mayor
 * del navegador, y si el dispositivo es táctil. No se guarda el user agent
 * completo (RNF02).
 *
 * @module     local_samce/sig_window
 * @copyright  SAMCE
 */
define('local_samce/sig_window', [], function() {
    'use strict';

    var RESIZE_DEBOUNCE_MS = 300;

    /**
     * Familia y versión mayor del navegador a partir del user agent.
     *
     * @param {string} userAgent
     * @return {Object} {browser_family, browser_major}
     */
    var browserOf = function(userAgent) {
        // El orden importa: Edge y Opera también dicen "Chrome"; Chrome también dice "Safari".
        var candidates = [
            ['Edge', /Edg\/(\d+)/],
            ['Opera', /OPR\/(\d+)/],
            ['Firefox', /Firefox\/(\d+)/],
            ['Chrome', /Chrome\/(\d+)/],
            ['Safari', /Version\/(\d+).*Safari/]
        ];
        for (var i = 0; i < candidates.length; i++) {
            var match = candidates[i][1].exec(userAgent || '');
            if (match) {
                return {browser_family: candidates[i][0], browser_major: parseInt(match[1], 10)};
            }
        }
        return {browser_family: 'Other', browser_major: 0};
    };

    /**
     * @param {Window} win
     * @return {Object} datos del evento client_profile.
     */
    var profileOf = function(win) {
        var navigatorRef = win.navigator || {};
        var profile = browserOf(navigatorRef.userAgent);
        profile.is_touch = (navigatorRef.maxTouchPoints || 0) > 0;
        return profile;
    };

    var fillsScreen = function(win) {
        var screen = win.screen;
        if (!screen) {
            return false;
        }
        return Math.abs(win.innerWidth - screen.width) <= 1 && Math.abs(win.innerHeight - screen.height) <= 1;
    };

    /**
     * @param {Object} env entorno de la captura (win, doc, now, emit, safe).
     * @return {Object} {stop, flush}
     */
    var start = function(env) {
        var win = env.win;
        var doc = env.doc;
        var resizeTimer = null;
        var sizeFullscreen = fillsScreen(win);

        var emitResize = env.safe(function() {
            resizeTimer = null;
            env.emit('resize', {w: win.innerWidth, h: win.innerHeight});

            var filled = fillsScreen(win);
            if (filled !== sizeFullscreen && !doc.fullscreenElement) {
                env.emit('fullscreen', {on: filled, source: 'size'});
            }
            sizeFullscreen = filled;
        });

        var onResize = env.safe(function() {
            if (resizeTimer !== null) {
                win.clearTimeout(resizeTimer);
            }
            resizeTimer = win.setTimeout(emitResize, RESIZE_DEBOUNCE_MS);
        });

        var onFullscreen = env.safe(function() {
            env.emit('fullscreen', {on: !!doc.fullscreenElement, source: 'api'});
        });

        var onOnline = env.safe(function() {
            env.emit('connection', {online: true});
        });
        var onOffline = env.safe(function() {
            env.emit('connection', {online: false});
        });

        win.addEventListener('resize', onResize);
        doc.addEventListener('fullscreenchange', onFullscreen);
        win.addEventListener('online', onOnline);
        win.addEventListener('offline', onOffline);

        return {
            stop: function() {
                if (resizeTimer !== null) {
                    win.clearTimeout(resizeTimer);
                }
                win.removeEventListener('resize', onResize);
                doc.removeEventListener('fullscreenchange', onFullscreen);
                win.removeEventListener('online', onOnline);
                win.removeEventListener('offline', onOffline);
            },

            /** Un cambio de tamaño que todavía espera su pausa se informa antes de que salga el lote. */
            flush: function() {
                if (resizeTimer !== null) {
                    win.clearTimeout(resizeTimer);
                    emitResize();
                }
            }
        };
    };

    return {start: start, browserOf: browserOf, profileOf: profileOf};
});
