<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/samce/classes/send_policy.php');

/**
 * Tests de send_policy: las decisiones de local_samce_send_events que no
 * dependen de Moodle. La función externa entera necesita un Moodle con base
 * de datos y no se puede probar acá.
 *
 * @package local_samce
 * @covers \local_samce\send_policy
 */
class send_policy_test extends \PHPUnit\Framework\TestCase {

    public function test_200_is_ok(): void {
        $this->assertSame('ok', send_policy::status_for_http_code(200, 10));
    }

    public function test_server_errors_rate_limits_and_no_answer_are_retried(): void {
        foreach ([0, 429, 500, 502, 503] as $code) {
            $this->assertSame('retry', send_policy::status_for_http_code($code, 10), (string) $code);
        }
    }

    // Punto 4.1 de la revisión del 24/09/2026: con el aviso de inicio perdido
    // el intento todavía no tiene sesión, y borrar el lote perdía lo que el
    // alumno hizo hasta que el cron creara la sesión.
    public function test_404_is_retried_during_the_first_minutes_of_the_attempt(): void {
        $this->assertSame('retry', send_policy::status_for_http_code(404, 0));
        $this->assertSame('retry', send_policy::status_for_http_code(404, send_policy::RECENT_ATTEMPT_SECONDS));
    }

    public function test_404_on_an_old_attempt_is_discarded(): void {
        $this->assertSame('rejected', send_policy::status_for_http_code(404, send_policy::RECENT_ATTEMPT_SECONDS + 1));
    }

    public function test_definitive_rejections_are_discarded(): void {
        foreach ([400, 401, 403, 413] as $code) {
            $this->assertSame('rejected', send_policy::status_for_http_code($code, 10), (string) $code);
        }
    }

    public function test_a_normal_batch_is_within_the_quota(): void {
        $state = [];
        $this->assertTrue(send_policy::within_quota($state, 100, 20000, 1790000000));
        $this->assertSame(100, $state['events']);
    }

    public function test_the_per_minute_cap_rejects_and_resets_next_minute(): void {
        $state = [];
        $now = 1790000000;
        for ($i = 0; $i < 15; $i++) {
            $this->assertTrue(send_policy::within_quota($state, 100, 100, $now));
        }
        $before = $state;
        $this->assertFalse(send_policy::within_quota($state, 1, 100, $now));
        $this->assertSame($before, $state, 'un lote rechazado no suma');

        $this->assertTrue(send_policy::within_quota($state, 100, 100, $now + 61), 'al minuto siguiente vuelve a entrar');
    }

    public function test_the_total_caps_per_attempt_reject(): void {
        $state = ['events' => send_policy::MAX_EVENTS_PER_ATTEMPT - 5];
        $this->assertFalse(send_policy::within_quota($state, 6, 10, 1790000000));

        $state = ['bytes' => send_policy::MAX_BYTES_PER_ATTEMPT - 5];
        $this->assertFalse(send_policy::within_quota($state, 1, 6, 1790000000));
    }
}
