<?php

namespace local_samce;

defined('MOODLE_INTERNAL') || die();

/**
 * Qué preferencias de usuario del complemento quedan huérfanas cuando se borra
 * un cuestionario monitoreado, para poder limpiarlas antes de que se pierda la
 * referencia.
 *
 * `classes/privacy/provider.php` documenta que Moodle borra estas preferencias
 * cuando borra a un USUARIO (`mdl_user_preferences` se purga por `userid`),
 * pero esa tabla no tiene columna de curso ni de cuestionario: borrar un curso
 * (o solo el cuestionario) nunca las toca, y quedan en la base sin forma de
 * asociarlas a nada porque el intento al que apuntan ya no existe (revisión
 * externa del 28/09/2026, punto 8).
 *
 * Separada de la limpieza real (`lib.php`,
 * `local_samce_pre_course_module_delete`) para poder probar qué nombres
 * corresponden sin requerir un Moodle completo, igual que `event_batch` y
 * `token_signer`.
 *
 * @package local_samce
 */
class preference_cleanup {

    /**
     * Duplicado a propósito de `accept_notice::PREFERENCE_PREFIX` y
     * `::START_PREFERENCE_PREFIX`: ese archivo extiende `external_api`, que no
     * carga sin un Moodle completo, y esta clase necesita poder probarse sin
     * uno. Si esos prefijos cambian, cambiar también acá.
     */
    const NOTICE_PREFIX = 'local_samce_notice_';
    const START_PREFIX = 'local_samce_start_';

    /**
     * Los nombres de preferencia que hay que borrar al borrar un cuestionario
     * monitoreado: la autorización de comienzo (una por cuestionario, la deja
     * cualquier alumno que haya pasado por el aviso en la página del
     * cuestionario) y, por cada intento que tuvo, la constancia del aviso y las
     * marcas de `capture_watch` (página entregada, captura vista, alertas).
     *
     * @param int $cmid El cuestionario que se va a borrar.
     * @param int[] $attemptids Los intentos de ESE cuestionario, leídos ANTES de
     *                          borrarlos: `quiz_delete_instance()` los borra
     *                          antes de que Moodle dispare
     *                          `course_module_deleted`, así que hay que
     *                          capturarlos con `pre_course_module_delete`, que
     *                          corre antes (ver `lib.php`).
     * @return string[] Sin duplicados.
     */
    public static function names_for_quiz(int $cmid, array $attemptids): array {
        $names = [self::START_PREFIX . $cmid];
        foreach ($attemptids as $attemptid) {
            $names[] = self::NOTICE_PREFIX . $attemptid;
            $names[] = capture_watch::PAGE_PREFIX . $attemptid;
            $names[] = capture_watch::SEEN_PREFIX . $attemptid;
            $names[] = capture_watch::ALERT_PREFIX . capture_watch::STATE_JS_DISABLED . '_' . $attemptid;
            $names[] = capture_watch::ALERT_PREFIX . capture_watch::STATE_NO_START . '_' . $attemptid;
        }
        return array_values(array_unique($names));
    }
}
