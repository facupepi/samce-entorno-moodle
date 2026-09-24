<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Valida y normaliza el lote de eventos de interacción que entrega el
 * navegador del alumno, antes de firmarlo y reenviarlo a samce-backend.
 *
 * El JS de la página corre en el navegador del alumno, así que lo que llega
 * no se da por bueno: el mismo tope de tamaño y la misma lista de tipos que
 * aplica el backend se aplican acá primero, para no firmar ni reenviar un
 * lote que el backend iba a rechazar. Los límites y los tipos tienen que
 * coincidir con los de samce-backend (handlers/interaction_events.go);
 * tests/event_types.txt es la lista de referencia de los tipos, la misma en
 * los dos repos. Al agregar un tipo: primero el backend, después el plugin.
 *
 * No depende de ninguna API de Moodle a propósito, para poder testearla de
 * forma aislada sin bootstrapear un Moodle completo (igual que token_signer).
 *
 * @package local_samce
 */
class event_batch {

    /** Máximo de eventos por lote. */
    const MAX_EVENTS = 100;

    /** Máximo de bytes del data de un solo evento, ya serializado. */
    const MAX_DATA_BYTES = 2048;

    /**
     * Máximo de bytes de la SUMA del data de un lote. Antes solo se validaba
     * cada evento por separado, y el máximo que se podía firmar (100 x 2048
     * bytes, más el base64 del token) superaba el tope del body del backend:
     * respondía 413, send_events.php lo trataba como rechazo y se perdían los
     * cien eventos sin rastro (punto 22 de la revisión externa del
     * 23/09/2026). El mismo valor está en samce-backend
     * (interaction_events.go, maxBatchDataBytes), y el navegador corta los
     * lotes por debajo (capture.js, BATCH_MAX_BYTES).
     */
    const MAX_BATCH_DATA_BYTES = 131072;

    /**
     * Rango válido de `t`, el mismo que aplica el backend
     * (handlers/interaction_events.go): el de una columna TIMESTAMP(3) de
     * MySQL, con margen antes del límite exacto (2038-01-19 03:14:07 UTC).
     * Sin este tope, un evento con la hora corrupta pasaba esta validación,
     * el backend lo rechazaba recién al insertarlo, y como send_events.php
     * trata un 500 como reintentable, ese evento bloqueaba la cola del
     * alumno para siempre (el reintento siempre arranca desde el frente).
     */
    const MIN_EVENT_TIMESTAMP_MS = 1000;
    const MAX_EVENT_TIMESTAMP_MS = 2145916800000; // 2038-01-01 00:00:00 UTC.

    /** Tipos de evento que acepta el backend. */
    const ALLOWED_TYPES = [
        'client_profile',
        'consent_accepted',
        'events_dropped',
        'focus_lost',
        'focus_gained',
        'visibility_hidden',
        'visibility_visible',
        'key_activity',
        'mouse_activity',
        'mouse_leave',
        'mouse_enter',
        'question_time',
        'clipboard',
        'fullscreen',
        'resize',
        'connection',
    ];

    /**
     * Decodifica y valida un lote de eventos en JSON.
     *
     * El JSON se decodifica como objetos y no como arrays asociativos a
     * propósito: con arrays, un data vacío ({}) pasa a ser [] y al volver a
     * codificarlo el backend lo recibiría como una lista y no como un
     * objeto, y rechazaría el lote entero.
     *
     * @param string $json Lista de eventos, cada uno con seq, t, type y data.
     * @return array|null Eventos limpios (seq, t, type y data como objeto), o
     *                    null si el lote no es válido. Un lote inválido se
     *                    rechaza entero, no se aprovecha la mitad.
     */
    public static function parse(string $json): ?array {
        $decoded = json_decode($json);
        if (!is_array($decoded) || count($decoded) === 0 || count($decoded) > self::MAX_EVENTS) {
            return null;
        }

        $clean = [];
        $totaldata = 0;
        foreach ($decoded as $event) {
            if (!is_object($event)) {
                return null;
            }

            $seq = $event->seq ?? null;
            $time = $event->t ?? null;
            $type = $event->type ?? null;
            if (!is_int($seq) || $seq <= 0 || !is_int($time)
                    || $time < self::MIN_EVENT_TIMESTAMP_MS || $time > self::MAX_EVENT_TIMESTAMP_MS) {
                return null;
            }
            if (!is_string($type) || !in_array($type, self::ALLOWED_TYPES, true)) {
                return null;
            }

            $data = $event->data ?? new \stdClass();
            if (!is_object($data)) {
                return null;
            }
            $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false || strlen($encoded) > self::MAX_DATA_BYTES) {
                return null;
            }
            $totaldata += strlen($encoded);
            if ($totaldata > self::MAX_BATCH_DATA_BYTES) {
                return null;
            }

            $clean[] = ['seq' => $seq, 't' => $time, 'type' => $type, 'data' => $data];
        }

        return $clean;
    }

    /**
     * Arma la URL del endpoint de eventos de interacción a partir de la URL
     * ya configurada para los avisos de inicio y entrega.
     *
     * @param string $backendurl Por ejemplo https://backend/sessions/moodle-event.
     * @return string La URL de /sessions/moodle-events, o '' si la URL
     *                configurada no tiene la forma esperada.
     */
    public static function events_url(string $backendurl): string {
        $count = 0;
        $url = preg_replace('#/sessions/moodle-event/?$#', '/sessions/moodle-events', trim($backendurl), 1, $count);

        return $count === 1 ? $url : '';
    }
}
