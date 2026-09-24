<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Decisiones puras de local_samce_send_events, separadas de la función externa
 * para poder probarlas sin Moodle (igual que event_batch y browser_check):
 * send_events.php necesita un Moodle completo con base de datos y no tenía ni
 * una prueba.
 *
 * @package local_samce
 */
class send_policy {

    /** Segundos desde que arrancó el intento en los que todavía se espera que el aviso de inicio llegue. */
    const RECENT_ATTEMPT_SECONDS = 600;

    /** Topes por intento (ver within_quota). */
    const MAX_EVENTS_PER_MINUTE = 1500;
    const MAX_EVENTS_PER_ATTEMPT = 100000;
    const MAX_BYTES_PER_ATTEMPT = 52428800; // 50 MiB.

    /**
     * Qué estado le devuelve la función externa al navegador según el código
     * HTTP con que contestó el backend.
     *
     * 404 significa "este intento todavía no tiene sesión": el aviso de inicio
     * se perdió (un corte de medio segundo alcanza) y el cron lo va a
     * reintentar. Durante los primeros minutos del intento se conserva el lote
     * y se reintenta, en vez de borrarlo: la cola aguanta unos 20 minutos y el
     * backend es idempotente por sesión y seq. Pasado ese tiempo, un 404 ya no
     * es "todavía", y se descarta.
     *
     * @param int $code Código HTTP (0 si no hubo respuesta).
     * @param int $attemptage Segundos desde que arrancó el intento.
     * @return string 'ok', 'retry' o 'rejected'.
     */
    public static function status_for_http_code(int $code, int $attemptage): string {
        if ($code === 200) {
            return 'ok';
        }
        if ($code === 0 || $code === 429 || $code >= 500) {
            return 'retry';
        }
        if ($code === 404 && $attemptage >= 0 && $attemptage <= self::RECENT_ATTEMPT_SECONDS) {
            return 'retry';
        }
        return 'rejected';
    }

    /**
     * Tope de cuánto puede mandar un intento. El contenido de los eventos lo
     * arma el navegador del alumno y no se puede verificar; esto no lo impide,
     * pero acota cuánto puede llenar la base una sola sesión con unas líneas
     * de fetch en la consola.
     *
     * Suma este lote al estado y devuelve false si con él se pasa algún tope
     * (el estado no se toca en ese caso).
     *
     * @param array $state Estado del intento; se actualiza.
     * @param int $events Cantidad de eventos del lote.
     * @param int $bytes Tamaño del lote.
     * @param int $now Hora actual.
     * @return bool true si el lote entra.
     */
    public static function within_quota(array &$state, int $events, int $bytes, int $now): bool {
        $minute = intdiv($now, 60);
        $state += ['minute' => $minute, 'minuteevents' => 0, 'events' => 0, 'bytes' => 0];
        if ($state['minute'] !== $minute) {
            $state['minute'] = $minute;
            $state['minuteevents'] = 0;
        }

        if ($state['minuteevents'] + $events > self::MAX_EVENTS_PER_MINUTE ||
                $state['events'] + $events > self::MAX_EVENTS_PER_ATTEMPT ||
                $state['bytes'] + $bytes > self::MAX_BYTES_PER_ATTEMPT) {
            return false;
        }

        $state['minuteevents'] += $events;
        $state['events'] += $events;
        $state['bytes'] += $bytes;
        return true;
    }
}
