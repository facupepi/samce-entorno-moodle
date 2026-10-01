<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/samce/classes/capture_watch.php');
require_once($CFG->dirroot . '/local/samce/classes/preference_cleanup.php');

/**
 * Tests de preference_cleanup. No extiende advanced_testcase a propósito: la
 * clase no toca ninguna API de Moodle, así que no necesita bootstrap de base de
 * datos (mismo criterio que event_batch y token_signer).
 *
 * @package local_samce
 * @covers \local_samce\preference_cleanup
 */
class preference_cleanup_test extends \PHPUnit\Framework\TestCase {

    public function test_a_quiz_with_no_attempts_only_clears_its_start_authorization(): void {
        $this->assertSame(['local_samce_start_7'], preference_cleanup::names_for_quiz(7, []));
    }

    public function test_each_attempt_contributes_its_five_preference_names(): void {
        $names = preference_cleanup::names_for_quiz(7, [100]);

        $this->assertContains('local_samce_start_7', $names);
        $this->assertContains('local_samce_notice_100', $names);
        $this->assertContains(capture_watch::PAGE_PREFIX . '100', $names);
        $this->assertContains(capture_watch::SEEN_PREFIX . '100', $names);
        $this->assertContains(capture_watch::ALERT_PREFIX . 'js_disabled_100', $names);
        $this->assertContains(capture_watch::ALERT_PREFIX . 'no_start_100', $names);
        $this->assertCount(6, $names);
    }

    public function test_several_attempts_do_not_mix_up_their_names(): void {
        $names = preference_cleanup::names_for_quiz(7, [100, 101]);

        $this->assertCount(11, $names, 'una autorización de comienzo más cinco por cada uno de los dos intentos');
        $this->assertContains('local_samce_notice_100', $names);
        $this->assertContains('local_samce_notice_101', $names);
        $this->assertSame($names, array_unique($names), 'sin nombres repetidos');
    }

    public function test_repeated_attempt_ids_do_not_duplicate_names(): void {
        $names = preference_cleanup::names_for_quiz(7, [100, 100]);

        $this->assertCount(6, $names);
    }
}
