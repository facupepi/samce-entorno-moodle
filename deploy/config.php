<?php
// config.php de producción para Railway.
//
// A diferencia del config.php que genera moodle-docker en local, este lee
// todo de variables de entorno — no hay nada hardcodeado. Se copia a
// /var/www/html/config.php en build time (Dockerfile); como usa getenv() en
// vez de valores fijos, no hace falta regenerarlo en cada arranque.
//
// Variables de entorno esperadas (se configuran en Railway → Variables):
//   MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
//     -> por referencia al servicio MySQL del proyecto (mismo patrón que
//        samce-backend), NO tipeadas a mano.
//   MOODLE_WWWROOT
//     -> la URL pública que Railway genera para este service (se completa
//        DESPUÉS del primer deploy, cuando ya existe el dominio — mismo
//        procedimiento que FRONTEND_URL en samce-backend).
//   MOODLE_DATAROOT (opcional, default /var/moodledata)
//     -> tiene que apuntar al punto de montaje del volumen persistente.

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MYSQLHOST') ?: '';
$CFG->dbname    = getenv('MYSQLDATABASE') ?: '';
$CFG->dbuser    = getenv('MYSQLUSER') ?: '';
$CFG->dbpass    = getenv('MYSQLPASSWORD') ?: '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist'   => false,
    'dbport'      => getenv('MYSQLPORT') ?: '3306',
    'dbsocket'    => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
];

$CFG->wwwroot  = getenv('MOODLE_WWWROOT') ?: 'http://localhost';
$CFG->dataroot = getenv('MOODLE_DATAROOT') ?: '/var/moodledata';
$CFG->admin    = 'admin';
$CFG->directorypermissions = 02777;

// Railway termina el HTTPS en su proxy antes de llegar al contenedor: sin
// esto Moodle cree que la conexión es HTTP plano y rompe enlaces/cookies.
$CFG->sslproxy = true;

require_once(__DIR__ . '/lib/setup.php');
