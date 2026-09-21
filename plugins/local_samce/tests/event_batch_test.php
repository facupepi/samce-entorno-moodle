<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/samce/classes/event_batch.php');
require_once($CFG->dirroot . '/local/samce/classes/token_signer.php');

/**
 * Tests de event_batch. No extiende advanced_testcase a propósito: la clase
 * no toca ninguna API de Moodle, así que no necesita bootstrap de base de
 * datos (mismo criterio que token_signer_test).
 *
 * @package local_samce
 * @covers \local_samce\event_batch
 */
class event_batch_test extends \PHPUnit\Framework\TestCase {

    private function batch(array $events): string {
        return json_encode($events);
    }

    private function event(array $override = []): array {
        return array_merge(['seq' => 1, 't' => 1790000000000, 'type' => 'focus_lost', 'data' => new \stdClass()], $override);
    }

    public function test_valid_batch_is_returned_clean(): void {
        $clean = event_batch::parse($this->batch([
            $this->event(),
            $this->event(['seq' => 2, 'type' => 'clipboard', 'data' => ['action' => 'paste', 'length' => 80]]),
        ]));

        $this->assertCount(2, $clean);
        $this->assertSame(1, $clean[0]['seq']);
        $this->assertSame('clipboard', $clean[1]['type']);
        $this->assertSame(80, $clean[1]['data']->length);
    }

    /**
     * Un data vacío tiene que seguir siendo un objeto al volver a
     * serializarse: como lista, el backend rechazaría el lote entero.
     */
    public function test_empty_data_stays_an_object_when_reserialized(): void {
        $clean = event_batch::parse('[{"seq":1,"t":1790000000000,"type":"focus_lost","data":{}}]');

        $this->assertSame('[{"seq":1,"t":1790000000000,"type":"focus_lost","data":{}}]', json_encode($clean));
    }

    public function test_missing_data_becomes_an_empty_object(): void {
        $clean = event_batch::parse('[{"seq":1,"t":1790000000000,"type":"focus_lost"}]');

        $this->assertSame('{}', json_encode($clean[0]['data']));
    }

    public function test_invalid_json_is_rejected(): void {
        $this->assertNull(event_batch::parse('esto no es json'));
        $this->assertNull(event_batch::parse('{"seq":1}'));
        $this->assertNull(event_batch::parse('[]'));
        $this->assertNull(event_batch::parse('["texto"]'));
    }

    public function test_too_many_events_is_rejected(): void {
        $events = [];
        for ($i = 1; $i <= event_batch::MAX_EVENTS + 1; $i++) {
            $events[] = $this->event(['seq' => $i]);
        }

        $this->assertNull(event_batch::parse($this->batch($events)));
    }

    public function test_the_maximum_batch_size_is_accepted(): void {
        $events = [];
        for ($i = 1; $i <= event_batch::MAX_EVENTS; $i++) {
            $events[] = $this->event(['seq' => $i]);
        }

        $this->assertCount(event_batch::MAX_EVENTS, event_batch::parse($this->batch($events)));
    }

    public function test_unknown_type_is_rejected(): void {
        $this->assertNull(event_batch::parse($this->batch([$this->event(['type' => 'keylogger'])])));
    }

    public function test_every_allowed_type_is_accepted(): void {
        foreach (event_batch::ALLOWED_TYPES as $type) {
            $this->assertNotNull(event_batch::parse($this->batch([$this->event(['type' => $type])])), $type);
        }
    }

    public function test_invalid_seq_or_timestamp_is_rejected(): void {
        $this->assertNull(event_batch::parse($this->batch([$this->event(['seq' => 0])])));
        $this->assertNull(event_batch::parse($this->batch([$this->event(['seq' => '1'])])));
        $this->assertNull(event_batch::parse($this->batch([$this->event(['seq' => 1.5])])));
        $this->assertNull(event_batch::parse($this->batch([$this->event(['t' => 0])])));
        $this->assertNull(event_batch::parse($this->batch([$this->event(['t' => null])])));
    }

    public function test_data_that_is_not_an_object_is_rejected(): void {
        $this->assertNull(event_batch::parse('[{"seq":1,"t":1790000000000,"type":"focus_lost","data":"texto"}]'));
        $this->assertNull(event_batch::parse('[{"seq":1,"t":1790000000000,"type":"focus_lost","data":[1,2]}]'));
    }

    public function test_oversized_data_is_rejected(): void {
        $big = ['x' => str_repeat('a', event_batch::MAX_DATA_BYTES)];

        $this->assertNull(event_batch::parse($this->batch([$this->event(['data' => $big])])));
    }

    public function test_a_single_bad_event_rejects_the_whole_batch(): void {
        $this->assertNull(event_batch::parse($this->batch([$this->event(), $this->event(['type' => 'keylogger', 'seq' => 2])])));
    }

    public function test_events_url_is_derived_from_the_backend_url(): void {
        $this->assertSame(
            'https://backend.example/sessions/moodle-events',
            event_batch::events_url('https://backend.example/sessions/moodle-event')
        );
        $this->assertSame(
            'https://backend.example/sessions/moodle-events',
            event_batch::events_url('  https://backend.example/sessions/moodle-event/ ')
        );
    }

    public function test_events_url_is_empty_when_the_backend_url_has_another_shape(): void {
        $this->assertSame('', event_batch::events_url(''));
        $this->assertSame('', event_batch::events_url('https://backend.example/otra/ruta'));
        $this->assertSame('', event_batch::events_url('https://backend.example/sessions/moodle-events'));
    }

    /**
     * El lote firmado que sale de acá es el que verifica el backend: el
     * payload tiene que llevar los eventos como lista de objetos.
     */
    public function test_signed_batch_carries_events_as_a_list_of_objects(): void {
        $clean = event_batch::parse('[{"seq":1,"t":1790000000000,"type":"focus_lost","data":{}}]');
        $token = token_signer::sign([
            'event_type' => 'interaction_events',
            'moodle_attempt_id' => 42,
            'events' => $clean,
            'iat' => 1000,
            'exp' => 1060,
        ], 'secreto');

        $payload = base64_decode(strtr(explode('.', $token)[0], '-_', '+/'));

        $this->assertStringContainsString('"events":[{"seq":1,"t":1790000000000,"type":"focus_lost","data":{}}]', $payload);
    }
}
