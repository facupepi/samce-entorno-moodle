<?php
/**
 * Funciones externas de local_samce.
 *
 * Solo para AJAX de las páginas de Moodle (ajax => true): no se habilitan
 * servicios web ni se emite ningún token, así que el Campus Virtual no tiene
 * que exponer una superficie de API más amplia (ver el README del plugin).
 *
 * @package   local_samce
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_samce_send_events' => [
        'classname'     => 'local_samce\external\send_events',
        'description'   => 'Recibe un lote de eventos de interacción del intento en curso y lo reenvía firmado a SAMCE.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'local_samce_accept_notice' => [
        'classname'     => 'local_samce\external\accept_notice',
        'description'   => 'Registra, del lado del servidor, que el alumno vio el aviso de monitoreo de un intento en curso.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
