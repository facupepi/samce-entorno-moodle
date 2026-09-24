<?php

namespace local_samce\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Declara en el registro de privacidad de Moodle que local_samce manda datos
 * de alumnos a un servidor externo (punto 24 de la revisión externa del
 * 23/09/2026).
 *
 * Declara el envío al backend (metadata) y la única cosa que el plugin guarda
 * localmente: una preferencia de usuario por intento con la hora en que el
 * alumno vio el aviso (local_samce_notice_<intento>), que se exporta con los
 * datos del usuario y Moodle borra cuando borra al usuario. Moodle nunca
 * exporta ni borra datos alojados en un sistema externo: lo que vive en el
 * backend es problema aparte de este archivo (ver docs/PRIVACIDAD.md). Declarar el envío lo hace visible y
 * documentado para el administrador del sitio; el borrado real de lo que
 * guarda samce-backend es un problema aparte que este archivo no resuelve.
 *
 * @package local_samce
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\user_preference_provider {

    /**
     * @param \core_privacy\local\metadata\collection $collection
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection):
            \core_privacy\local\metadata\collection {
        $collection->add_external_location_link('samce_backend', [
            'attemptid'   => 'privacy:metadata:samce_backend:attemptid',
            'userid'      => 'privacy:metadata:samce_backend:userid',
            'fullname'    => 'privacy:metadata:samce_backend:fullname',
            'courseid'    => 'privacy:metadata:samce_backend:courseid',
            'quizid'      => 'privacy:metadata:samce_backend:quizid',
            'timestamps'  => 'privacy:metadata:samce_backend:timestamps',
            'interaction' => 'privacy:metadata:samce_backend:interaction',
        ], 'privacy:metadata:samce_backend');

        $collection->add_user_preference(
            \local_samce\external\accept_notice::PREFERENCE_PREFIX . '<intento>',
            'privacy:preference:notice'
        );
        $collection->add_user_preference(
            \local_samce\external\accept_notice::START_PREFERENCE_PREFIX . '<cuestionario>',
            'privacy:preference:start'
        );
        $collection->add_user_preference(\local_samce\capture_watch::PAGE_PREFIX . '<intento>', 'privacy:preference:page');
        $collection->add_user_preference(\local_samce\capture_watch::SEEN_PREFIX . '<intento>', 'privacy:preference:seen');
        $collection->add_user_preference(\local_samce\capture_watch::ALERT_PREFIX . '<estado>_<intento>', 'privacy:preference:alert');

        return $collection;
    }

    /**
     * Exporta la hora en que el usuario vio el aviso de cada intento.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        $strings = [
            \local_samce\external\accept_notice::PREFERENCE_PREFIX => 'privacy:preference:notice',
            \local_samce\external\accept_notice::START_PREFERENCE_PREFIX => 'privacy:preference:start',
            \local_samce\capture_watch::PAGE_PREFIX => 'privacy:preference:page',
            \local_samce\capture_watch::SEEN_PREFIX => 'privacy:preference:seen',
            \local_samce\capture_watch::ALERT_PREFIX => 'privacy:preference:alert',
        ];
        $preferences = get_user_preferences(null, null, $userid);
        foreach ((array) $preferences as $name => $value) {
            foreach ($strings as $prefix => $stringid) {
                if (strpos((string) $name, $prefix) !== 0) {
                    continue;
                }
                \core_privacy\local\request\writer::export_user_preference(
                    'local_samce',
                    $name,
                    \core_privacy\local\request\transform::datetime((int) $value),
                    get_string($stringid, 'local_samce')
                );
            }
        }
    }
}
