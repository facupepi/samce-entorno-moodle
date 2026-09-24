<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Detecta, del lado del servidor, que una página del examen se entregó y la
 * captura no arrancó (JavaScript apagado o bloqueado), y se lo avisa al backend
 * para que el docente vea la marca "Sin captura".
 *
 * No bloquea a nadie: el alumno ya aceptó el aviso previo, y del lado del
 * navegador no se puede impedir que alguien apague o bloquee el JavaScript. Lo que
 * sí se puede es que quede a la vista.
 *
 * Dos señales, ambas sin ningún pedido periódico:
 * - nojs.php: el navegador la pide solo si el JavaScript está apagado, desde un
 *   bloque <noscript> de la página del examen.
 * - "la página se entregó y la captura no llamó": el servidor anota cuándo entrega
 *   cada página del intento (local_samce_page_<intento>) y la captura anota cuándo
 *   arranca en cada una (local_samce_seen_<intento>, en accept_notice). Si la
 *   entrega es anterior y no hubo arranque después, esa página corrió sin captura.
 *
 * @package local_samce
 */
class capture_watch {

    const PAGE_PREFIX = 'local_samce_page_';
    const SEEN_PREFIX = 'local_samce_seen_';
    const ALERT_PREFIX = 'local_samce_alert_';

    /** Segundos que se le dan a la captura, tras entregarse la página, para arrancar. */
    const START_GRACE_SECONDS = 30;

    const STATE_JS_DISABLED = 'js_disabled';
    const STATE_NO_START = 'no_start';

    /**
     * Si una página entregada antes corrió sin que la captura arrancara.
     *
     * @param int|null $delivered Cuándo se entregó la página anterior del intento.
     * @param int|null $seen Cuándo arrancó la captura por última vez.
     * @param int $now Hora actual.
     * @return bool
     */
    public static function missed(?int $delivered, ?int $seen, int $now): bool {
        if ($delivered === null || $now - $delivered < self::START_GRACE_SECONDS) {
            return false;
        }
        return $seen === null || $seen < $delivered;
    }

    /**
     * Le avisa al backend, una sola vez por intento y estado, que la captura no
     * estuvo. Se encola como tarea en segundo plano: no demora la página del
     * examen, y si el intento todavía no tiene sesión se reintenta solo.
     *
     * @param int $userid
     * @param int $attemptid
     * @param string $state STATE_JS_DISABLED o STATE_NO_START.
     */
    public static function report(int $userid, int $attemptid, string $state): void {
        $name = self::ALERT_PREFIX . $state . '_' . $attemptid;
        if (get_user_preferences($name, null, $userid) !== null) {
            return;
        }
        set_user_preference($name, time(), $userid);

        $task = new \local_samce\task\send_capture_status();
        $task->set_custom_data(['attemptid' => $attemptid, 'state' => $state]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Revisa si la página anterior del intento corrió sin captura, y en ese caso
     * lo avisa.
     *
     * @param int $userid
     * @param int $attemptid
     */
    public static function check_previous_page(int $userid, int $attemptid): void {
        $delivered = get_user_preferences(self::PAGE_PREFIX . $attemptid, null, $userid);
        $seen = get_user_preferences(self::SEEN_PREFIX . $attemptid, null, $userid);
        if (self::missed($delivered === null ? null : (int) $delivered, $seen === null ? null : (int) $seen, time())) {
            self::report($userid, $attemptid, self::STATE_NO_START);
        }
    }

    /**
     * Anota que se entregó una página del intento.
     *
     * @param int $userid
     * @param int $attemptid
     */
    public static function mark_delivered(int $userid, int $attemptid): void {
        set_user_preference(self::PAGE_PREFIX . $attemptid, time(), $userid);
    }
}
