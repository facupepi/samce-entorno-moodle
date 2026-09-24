<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/samce/classes/backend_notifier.php');

/**
 * Tests de las esperas de backend_notifier. El envío en sí necesita curl de
 * Moodle; acá se fija lo que no se puede volver a romper sin querer: que el
 * aviso que corre dentro del request del alumno espere poco, y que el reintento
 * en segundo plano, donde no hay nadie esperando, espere más.
 *
 * @package local_samce
 * @covers \local_samce\backend_notifier
 */
class backend_notifier_test extends \PHPUnit\Framework\TestCase {

    public function test_the_notice_inside_the_students_request_waits_less_than_a_second(): void {
        $this->assertLessThanOrEqual(1000, backend_notifier::REQUEST_TIMEOUT_MS);
        $this->assertLessThan(backend_notifier::REQUEST_TIMEOUT_MS, backend_notifier::REQUEST_CONNECT_TIMEOUT_MS);
    }

    public function test_the_background_retry_is_more_patient(): void {
        $this->assertGreaterThan(backend_notifier::REQUEST_TIMEOUT_MS, backend_notifier::BACKGROUND_TIMEOUT_MS);
        $this->assertGreaterThan(backend_notifier::REQUEST_CONNECT_TIMEOUT_MS, backend_notifier::BACKGROUND_CONNECT_TIMEOUT_MS);
    }
}
