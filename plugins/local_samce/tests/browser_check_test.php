<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/samce/classes/browser_check.php');

/**
 * Tests de browser_check. No extiende advanced_testcase a propósito: la clase
 * no toca ninguna API de Moodle.
 *
 * @package local_samce
 * @covers \local_samce\browser_check
 */
class browser_check_test extends \PHPUnit\Framework\TestCase {

    public static function supported_provider(): array {
        return [
            'Chrome en Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'],
            'Chrome en macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'],
            'Chrome en Linux' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'],
            // A propósito: sin esto ningún caso de prueba automatizado podría rendir.
            'HeadlessChrome (navegador de pruebas)' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/153.0.0.0 Safari/537.36'],
            'Chrome en ChromeOS' => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'],
        ];
    }

    public static function unsupported_provider(): array {
        return [
            'vacío' => [''],
            'Firefox' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0'],
            'Safari de escritorio' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15'],
            'Edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0'],
            'Opera' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/112.0.0.0'],
            'Chrome en Android (teléfono)' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36'],
            'Chrome en Android (tablet)' => ['Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'],
            'Chrome en iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.0.0 Mobile/15E148 Safari/604.1'],
            'Chrome en iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.0.0 Mobile/15E148 Safari/604.1'],
        ];
    }

    /** @dataProvider supported_provider */
    public function test_chrome_on_a_computer_is_supported(string $useragent): void {
        $this->assertTrue(browser_check::is_supported($useragent));
    }

    /** @dataProvider unsupported_provider */
    public function test_anything_else_is_not_supported(string $useragent): void {
        $this->assertFalse(browser_check::is_supported($useragent));
    }
}
