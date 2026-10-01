<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * El ajuste `backendurl` del plugin, con una validación extra: que la URL tenga
 * la forma exacta que `event_batch::events_url()` necesita.
 *
 * Antes, `PARAM_URL` solo exigía una URL sintácticamente válida: cualquier
 * desvío del sufijo exacto `/sessions/moodle-event` (un typo, una barra de más,
 * `moodle_event` en vez de `moodle-event`) hacía que `events_url()` devolviera
 * `''`, y ese `''` apaga TODO el monitoreo —el aviso, la captura, la puerta del
 * servidor, los avisos de captura— en silencio: la casilla `capture_enabled`
 * sigue tildada, y no queda ningún error visible para nadie (revisión externa
 * del 28/09/2026, punto 1). Esta clase rechaza guardar un valor así, con un
 * mensaje explícito, en vez de aceptar cualquier URL sintácticamente correcta.
 *
 * No tiene test propio: extiende `admin_setting_configtext` de Moodle y usa
 * `get_string()`, así que necesita el bootstrap completo que los tests "puros"
 * del plugin no tienen (igual que `hook_callbacks.php`). La parte que sí se
 * prueba sin Moodle es `event_batch::events_url()`, de la que depende
 * enteramente (`event_batch_test.php`).
 *
 * @package local_samce
 */
class admin_setting_backendurl extends \admin_setting_configtext {

    /**
     * @param mixed $data El valor que se intenta guardar.
     * @return string|true true si pasa; un mensaje de error si no.
     */
    public function validate($data) {
        $base = parent::validate($data);
        if ($base !== true) {
            return $base;
        }

        $trimmed = trim((string) $data);
        if ($trimmed === '') {
            // Vacío es válido: es el valor por defecto, y hasta que no se
            // configure, el monitoreo simplemente no manda nada a ningún lado.
            return true;
        }

        if (event_batch::events_url($trimmed) === '') {
            return get_string('backendurlinvalid', 'local_samce');
        }
        return true;
    }
}
