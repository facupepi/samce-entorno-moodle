<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/samce/classes/capture_watch.php');

/**
 * Tests de capture_watch::missed: cuándo se da por hecho que una página del examen
 * corrió sin que la captura arrancara.
 *
 * @package local_samce
 * @covers \local_samce\capture_watch
 */
class capture_watch_test extends \PHPUnit\Framework\TestCase {

    const NOW = 1790000000;

    public function test_no_previous_page_means_nothing_to_report(): void {
        $this->assertFalse(capture_watch::missed(null, null, self::NOW));
    }

    public function test_a_page_delivered_a_moment_ago_still_has_time_to_start(): void {
        $this->assertFalse(capture_watch::missed(self::NOW - 5, null, self::NOW));
    }

    public function test_a_page_where_the_capture_started_is_fine(): void {
        $this->assertFalse(capture_watch::missed(self::NOW - 120, self::NOW - 118, self::NOW));
    }

    public function test_a_page_delivered_long_ago_without_any_start_is_reported(): void {
        $this->assertTrue(capture_watch::missed(self::NOW - 120, null, self::NOW));
    }

    // La captura arrancó en la página de antes, pero no en esta.
    public function test_a_start_from_an_earlier_page_does_not_count_for_this_one(): void {
        $this->assertTrue(capture_watch::missed(self::NOW - 120, self::NOW - 600, self::NOW));
    }

    public function test_the_grace_boundary(): void {
        $this->assertFalse(capture_watch::missed(self::NOW - capture_watch::START_GRACE_SECONDS + 1, null, self::NOW));
        $this->assertTrue(capture_watch::missed(self::NOW - capture_watch::START_GRACE_SECONDS, null, self::NOW));
    }
}
