<?php

namespace local_samce\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Declara en el registro de privacidad de Moodle que local_samce manda datos
 * de alumnos a un servidor externo (punto 24 de la revisión externa del
 * 23/09/2026).
 *
 * Es un provider de metadata solamente, a propósito. No implementa exportar ni
 * borrar datos: el plugin no guarda nada localmente (db/ no tiene install.xml
 * y no hay ningún insert_record), y Moodle nunca exporta ni borra datos
 * alojados en un sistema externo. Declarar el envío lo hace visible y
 * documentado para el administrador del sitio; el borrado real de lo que
 * guarda samce-backend es un problema aparte que este archivo no resuelve.
 *
 * @package local_samce
 */
class provider implements \core_privacy\local\metadata\provider {

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

        return $collection;
    }
}
