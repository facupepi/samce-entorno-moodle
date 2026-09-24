<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/samce/classes/gate_policy.php');

/**
 * Tests de gate_policy: cuándo la puerta del servidor frena a un alumno.
 *
 * @package local_samce
 * @covers \local_samce\gate_policy
 */
class gate_policy_test extends \PHPUnit\Framework\TestCase {

    const NOW = 1790000000;

    public function test_only_the_exam_pages_are_controlled(): void {
        $this->assertSame('start', gate_policy::kind_of_script('/mod/quiz/startattempt.php'));
        $this->assertSame('start', gate_policy::kind_of_script('/moodle/mod/quiz/startattempt.php'));
        $this->assertSame('attempt', gate_policy::kind_of_script('/mod/quiz/attempt.php'));
        $this->assertSame('attempt', gate_policy::kind_of_script('/mod/quiz/summary.php'));
        $this->assertNull(gate_policy::kind_of_script('/mod/quiz/view.php'));
        $this->assertNull(gate_policy::kind_of_script('/mod/quiz/review.php'));
        $this->assertNull(gate_policy::kind_of_script('/mod/quiz/processattempt.php'));
        $this->assertNull(gate_policy::kind_of_script('/my/index.php'));
    }

    // Comenzar.

    public function test_starting_without_having_read_the_notice_is_stopped(): void {
        $this->assertSame(gate_policy::NOTICE,
            gate_policy::verdict('start', true, true, false, null, false, self::NOW));
    }

    public function test_starting_right_after_accepting_is_allowed(): void {
        $this->assertSame(gate_policy::ALLOW,
            gate_policy::verdict('start', true, true, false, self::NOW - 60, false, self::NOW));
    }

    public function test_an_old_authorization_does_not_let_a_new_attempt_start(): void {
        $old = self::NOW - gate_policy::START_AUTH_SECONDS - 1;
        $this->assertSame(gate_policy::NOTICE,
            gate_policy::verdict('start', true, true, false, $old, false, self::NOW));
    }

    public function test_resuming_an_attempt_that_already_has_the_notice_does_not_ask_again(): void {
        $this->assertSame(gate_policy::ALLOW,
            gate_policy::verdict('start', true, true, false, null, true, self::NOW));
    }

    // Ver las preguntas.

    public function test_an_attempt_that_already_has_the_notice_loads(): void {
        $this->assertSame(gate_policy::ALLOW,
            gate_policy::verdict('attempt', true, true, true, null, false, self::NOW));
    }

    public function test_the_first_load_of_a_new_attempt_binds_the_notice(): void {
        $this->assertSame(gate_policy::BIND,
            gate_policy::verdict('attempt', true, true, false, self::NOW - 30, false, self::NOW));
    }

    // Saltear el aviso: llegar a las preguntas sin haber pasado por ahí.
    public function test_reaching_the_questions_without_the_notice_is_stopped(): void {
        $this->assertSame(gate_policy::NOTICE,
            gate_policy::verdict('attempt', true, true, false, null, false, self::NOW));
    }

    public function test_an_old_authorization_does_not_open_an_attempt_without_the_notice(): void {
        $old = self::NOW - gate_policy::START_AUTH_SECONDS - 1;
        $this->assertSame(gate_policy::NOTICE,
            gate_policy::verdict('attempt', true, true, false, $old, false, self::NOW));
    }

    // Un intento en curso de antes de que existiera la constancia: al aceptar en la
    // página del cuestionario se lo puede retomar, y queda con constancia.
    public function test_resuming_an_older_attempt_right_after_accepting_binds_the_notice(): void {
        $this->assertSame(gate_policy::BIND,
            gate_policy::verdict('attempt', true, true, false, self::NOW - 20, false, self::NOW));
    }

    public function test_an_authorization_from_the_future_is_not_valid(): void {
        $this->assertSame(gate_policy::NOTICE,
            gate_policy::verdict('attempt', true, true, false, self::NOW + 600, false, self::NOW));
    }

    // Navegador.

    public function test_an_unsupported_browser_is_stopped_before_anything_else(): void {
        foreach (['start', 'attempt'] as $kind) {
            $this->assertSame(gate_policy::BROWSER,
                gate_policy::verdict($kind, true, false, true, self::NOW - 5, true, self::NOW), $kind);
        }
    }

    public function test_with_the_browser_setting_off_any_browser_passes_the_browser_check(): void {
        $this->assertSame(gate_policy::ALLOW,
            gate_policy::verdict('attempt', false, false, true, null, false, self::NOW));
    }
}
