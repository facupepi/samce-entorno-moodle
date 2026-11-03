<?php
// Réplica local del Segundo Parcial de Railway (5 preguntas de desarrollo, una por página) y
// alumnos rend01 a rend06 matriculados en SI2-2026. La clave llega por la variable REND_PASS.
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/user/lib.php');
global $DB;
\core\session\manager::set_user(get_admin());

$datos = json_decode(file_get_contents('/tmp/parcial_railway.json'));
$clave = getenv('REND_PASS');
if (!$clave) { fwrite(STDERR, "falta REND_PASS\n"); exit(1); }
$curso = $DB->get_record('course', ['shortname' => 'SI2-2026'], '*', MUST_EXIST);
$nombre = 'Segundo Parcial (réplica del campus)';

$gen = new testing_data_generator();
$cm = null;
if ($q = $DB->get_record('quiz', ['course' => $curso->id, 'name' => $nombre])) {
    $cm = get_coursemodule_from_instance('quiz', $q->id);
    $quiz = (object)['id' => $q->id, 'cmid' => $cm->id];
    echo "ya existía: cmid {$cm->id}\n";
} else {
    $quiz = $gen->create_module('quiz', [
        'course' => $curso->id, 'name' => $nombre, 'intro' => $datos->ajustes->intro,
        'timelimit' => 3600, 'attempts' => 0, 'questionsperpage' => 1, 'navmethod' => 'free',
        'overduehandling' => 'autosubmit', 'grade' => 10,
    ]);
}
if (!$DB->record_exists('quiz_slots', ['quizid' => $quiz->id])) {
    require_once($CFG->libdir . '/questionlib.php');
    $ctx = context_module::instance($quiz->cmid);
    $cat = question_make_default_categories([$ctx]);
    $html = fn($t) => ['text' => $t, 'format' => FORMAT_HTML];
    $pagina = 1;
    foreach ($datos->preguntas as $p) {
        $form = (object)[
            'category' => $cat->id . ',' . $ctx->id, 'name' => $p->nombre, 'questiontext' => $html($p->texto),
            'generalfeedback' => $html(''), 'defaultmark' => (float)$p->nota, 'penalty' => 0,
            'responseformat' => $p->formato ?: 'editor', 'responserequired' => 1,
            'responsefieldlines' => (int)($p->lineas ?: 15), 'minwordlimit' => 0, 'maxwordlimit' => 0,
            'attachments' => 0, 'attachmentsrequired' => 0, 'maxbytes' => 0, 'filetypeslist' => '',
            'graderinfo' => $html(''), 'responsetemplate' => $html(''), 'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        ];
        $pr = question_bank::get_qtype('essay')->save_question((object)['category' => $cat->id, 'qtype' => 'essay', 'createdby' => get_admin()->id], $form);
        quiz_add_quiz_question($pr->id, $DB->get_record('quiz', ['id' => $quiz->id]), $pagina++);
    }
    $cm = get_coursemodule_from_id('quiz', $quiz->cmid);
    echo "creado: cmid {$cm->id}\n";
}

$rol = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$enrol = enrol_get_plugin('manual');
$inst = $DB->get_record('enrol', ['courseid' => $curso->id, 'enrol' => 'manual'], '*', MUST_EXIST);
for ($i = 1; $i <= 6; $i++) {
    $u = sprintf('rend%02d', $i);
    if (!$usr = $DB->get_record('user', ['username' => $u, 'deleted' => 0])) {
        $id = user_create_user((object)['username' => $u, 'password' => $clave, 'firstname' => 'Rendidor', 'lastname' => sprintf('%02d', $i),
            'email' => "$u@example.com", 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id, 'lang' => 'es'], true, false);
        $usr = $DB->get_record('user', ['id' => $id]);
    } else {
        update_internal_user_password($usr, $clave);
    }
    $enrol->enrol_user($inst, $usr->id, $rol->id);
    echo "$u listo\n";
}
foreach ($DB->get_records_sql("SELECT s.slot, s.page FROM {quiz_slots} s WHERE s.quizid = ? ORDER BY s.slot", [$cm->instance]) as $s) echo "slot {$s->slot} página {$s->page}\n";
