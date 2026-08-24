<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/samce/classes/token_signer.php');

/**
 * Tests de token_signer. No extiende advanced_testcase a propósito: la
 * clase no toca ninguna API de Moodle, así que no necesita bootstrap de
 * base de datos.
 *
 * @package local_samce
 * @covers \local_samce\token_signer
 */
class token_signer_test extends \PHPUnit\Framework\TestCase {

    private function base64url_decode(string $data): string {
        $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + 4 - strlen($data) % 4, '=');
        return base64_decode(strtr($padded, '-_', '+/'));
    }

    public function test_sign_produces_two_dot_separated_parts(): void {
        $token = token_signer::sign(['username' => 'docente.demo'], 'secreto');
        $parts = explode('.', $token);

        $this->assertCount(2, $parts);
    }

    public function test_sign_payload_roundtrips_to_original_claims(): void {
        $claims = [
            'moodle_user_id' => 42,
            'username' => 'docente.demo',
            'course_id' => 2,
            'role' => 'docente',
            'iat' => 1000,
            'exp' => 1060,
        ];

        $token = token_signer::sign($claims, 'secreto');
        [$payloadPart] = explode('.', $token, 2);
        $decoded = json_decode($this->base64url_decode($payloadPart), true);

        $this->assertSame($claims, $decoded);
    }

    public function test_sign_is_verifiable_with_same_secret(): void {
        $secret = 'secreto-compartido';
        $token = token_signer::sign(['username' => 'docente.demo'], $secret);
        [$payloadPart, $signaturePart] = explode('.', $token, 2);

        $payload = $this->base64url_decode($payloadPart);
        $expectedSignature = $this->base64url_decode($signaturePart);
        $recomputed = hash_hmac('sha256', $payload, $secret, true);

        $this->assertTrue(hash_equals($expectedSignature, $recomputed));
    }

    public function test_sign_differs_with_different_secret(): void {
        $tokenA = token_signer::sign(['username' => 'docente.demo'], 'secreto-a');
        $tokenB = token_signer::sign(['username' => 'docente.demo'], 'secreto-b');

        $this->assertNotSame($tokenA, $tokenB);
    }

    public function test_token_has_no_padding_or_url_unsafe_characters(): void {
        $token = token_signer::sign(['username' => 'docente/demo+raro'], 'secreto');

        $this->assertStringNotContainsString('=', $token);
        $this->assertStringNotContainsString('+', $token);
        $this->assertStringNotContainsString('/', $token);
    }
}
