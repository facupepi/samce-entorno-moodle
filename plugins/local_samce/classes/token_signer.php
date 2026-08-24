<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Firma los tokens de lanzamiento que local_samce envía al panel docente.
 *
 * Formato: base64url(payload_json) + "." + base64url(hmac_sha256(payload_json, secret)).
 * Es el mismo formato que verifica samce-backend en
 * src/api/core/auth/launch_token.go — cualquier cambio acá tiene que
 * reflejarse ahí también.
 *
 * No depende de ninguna API de Moodle a propósito, para poder testearla de
 * forma aislada sin bootstrapear un Moodle completo.
 *
 * @package local_samce
 */
class token_signer {

    /**
     * Firma un conjunto de claims con el secreto compartido.
     *
     * @param array $claims Pares clave-valor a incluir en el payload (por
     *                       ejemplo moodle_user_id, username, course_id,
     *                       role, iat, exp).
     * @param string $secret Secreto compartido con MOODLE_LAUNCH_SECRET en
     *                        samce-backend.
     * @return string Token firmado, listo para ir en la query string del
     *                 redirect al panel.
     */
    public static function sign(array $claims, string $secret): string {
        $payload = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $payload, $secret, true);

        return self::base64url_encode($payload) . '.' . self::base64url_encode($signature);
    }

    private static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
