<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Decisiones puras de la puerta del servidor: cuándo se frena a un alumno que
 * quiere comenzar o continuar un examen monitoreado.
 *
 * Hasta ahora el aviso de monitoreo y la restricción de navegador eran un
 * cartel de JavaScript encima de la página: quien lo borraba con las
 * herramientas del navegador, o desactivaba JavaScript, rendía igual. La
 * puerta (hook_callbacks::after_config) frena en el servidor, donde no hay nada
 * que borrar. Esta clase decide sin depender de Moodle, para poder probarla.
 *
 * @package local_samce
 */
class gate_policy {

    /**
     * Cuánto vale la autorización que se guarda al tocar "Leí el aviso y
     * continúo" en la página del cuestionario: lo que tarda el navegador en
     * pedir crear el intento y cargar la primera página.
     */
    const START_AUTH_SECONDS = 1800;

    const ALLOW = 'allow';
    const BIND = 'bind';
    const NOTICE = 'notice';
    const BROWSER = 'browser';

    /**
     * Qué tipo de pedido es, según el script.
     *
     * @param string $script SCRIPT_NAME del pedido.
     * @return string|null 'start' (crear o retomar el intento), 'attempt'
     *                     (ver las preguntas o la pantalla previa a entregar) o
     *                     null si no es una página que se controle.
     */
    public static function kind_of_script(string $script): ?string {
        if (preg_match('~/mod/quiz/startattempt\.php$~', $script)) {
            return 'start';
        }
        if (preg_match('~/mod/quiz/(attempt|summary)\.php$~', $script)) {
            return 'attempt';
        }
        return null;
    }

    /**
     * @param string $kind 'start' o 'attempt'.
     * @param bool $restrict Si el ajuste de navegador está encendido.
     * @param bool $browserok Si el navegador es Chrome de escritorio.
     * @param bool $attemptnoticed Si ya consta el aviso de ESTE intento.
     * @param int|null $startauth Hora de la autorización de comienzo de este cuestionario.
     * @param bool $unfinishednoticed Si tiene un intento en curso con el aviso ya visto (solo 'start').
     * @param int $now Hora actual.
     * @return string ALLOW, BIND (se deja pasar y hay que dejar constancia del
     *                aviso para este intento), NOTICE (falta leer el aviso) o
     *                BROWSER (navegador no admitido).
     */
    public static function verdict(string $kind, bool $restrict, bool $browserok, bool $attemptnoticed,
            ?int $startauth, bool $unfinishednoticed, int $now): string {
        if ($restrict && !$browserok) {
            return self::BROWSER;
        }

        $freshauth = $startauth !== null && $startauth <= $now && $now - $startauth <= self::START_AUTH_SECONDS;

        if ($kind === 'start') {
            // Retomar un intento en el que ya vio el aviso no lo pide de nuevo;
            // comenzar uno nuevo exige haberlo leído hace un momento.
            return ($unfinishednoticed || $freshauth) ? self::ALLOW : self::NOTICE;
        }

        if ($attemptnoticed) {
            return self::ALLOW;
        }
        // Recién aceptó el aviso en la página del cuestionario y viene a este
        // intento (recién creado, o uno en curso que retoma): deja pasar y deja
        // constancia para este intento, así entrar de nuevo (por ejemplo, tras una
        // desconexión) no lo vuelve a pedir.
        return $freshauth ? self::BIND : self::NOTICE;
    }
}
