<?php
/**
 * Agrega preguntas de desarrollo a la evaluación de prueba del SAMCE.
 * Ejecutar: docker exec moodle-docker-webserver-1 php samce_preguntas.php
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

\core\session\manager::set_user(get_admin());

cli_heading('Preguntas de la evaluación de prueba');

$curso = $DB->get_record('course', ['shortname' => 'SI2-2026'], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['course' => $curso->id, 'name' => 'Primer Parcial'], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $curso->id, false, MUST_EXIST);
$quiz->cmid = $cm->id;
$contextocurso = context_course::instance($curso->id);

$yatiene = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
if ($yatiene > 0) {
    cli_writeln("La evaluación ya tiene {$yatiene} preguntas. Nada que hacer.");
    exit(0);
}

$consignas = [
    'Explique el concepto de integridad académica en evaluaciones en línea.',
    'Describa tres señales de comportamiento observables durante un examen virtual.',
    'Fundamente por qué un enfoque no invasivo resulta preferible al proctoring por video.',
];

$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n";
foreach ($consignas as $i => $texto) {
    $xml .= "  <question type=\"essay\">\n";
    $xml .= "    <name><text>Consigna " . ($i + 1) . "</text></name>\n";
    $xml .= "    <questiontext format=\"html\"><text><![CDATA[<p>" . $texto . "</p>]]></text></questiontext>\n";
    $xml .= "    <generalfeedback format=\"html\"><text></text></generalfeedback>\n";
    $xml .= "    <defaultgrade>1.0000000</defaultgrade>\n";
    $xml .= "    <penalty>0.0000000</penalty>\n";
    $xml .= "    <hidden>0</hidden>\n";
    $xml .= "    <responseformat>editor</responseformat>\n";
    $xml .= "    <responserequired>1</responserequired>\n";
    $xml .= "    <responsefieldlines>10</responsefieldlines>\n";
    $xml .= "    <minwordlimit></minwordlimit>\n";
    $xml .= "    <maxwordlimit></maxwordlimit>\n";
    $xml .= "    <attachments>0</attachments>\n";
    $xml .= "    <attachmentsrequired>0</attachmentsrequired>\n";
    $xml .= "    <graderinfo format=\"html\"><text></text></graderinfo>\n";
    $xml .= "    <responsetemplate format=\"html\"><text></text></responsetemplate>\n";
    $xml .= "  </question>\n";
}
$xml .= "</quiz>\n";

$ruta = $CFG->tempdir . '/samce_preguntas.xml';
file_put_contents($ruta, $xml);

$categoria = question_make_default_categories([$contextocurso]);
cli_writeln('Categoría de preguntas: ' . $categoria->name . ' (id ' . $categoria->id . ')');

$formato = new qformat_xml();
$formato->setCategory($categoria);
$formato->setContexts([$contextocurso]);
$formato->setCourse($curso);
$formato->setFilename($ruta);
$formato->setRealfilename('samce_preguntas.xml');
$formato->setMatchgrades('error');
$formato->setCatfromfile(false);
$formato->setContextfromfile(false);
$formato->setStoponerror(true);

ob_start();
$ok = $formato->importpreprocess() && $formato->importprocess() && $formato->importpostprocess();
$salida = ob_get_clean();

if (!$ok) {
    cli_writeln('Falló la importación:');
    cli_writeln(strip_tags($salida));
    exit(1);
}

$preguntas = $DB->get_records_sql(
    "SELECT q.id, q.name
       FROM {question} q
       JOIN {question_versions} qv ON qv.questionid = q.id
       JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
      WHERE qbe.questioncategoryid = ?
   ORDER BY q.id", [$categoria->id]);

foreach ($preguntas as $p) {
    quiz_add_quiz_question($p->id, $quiz, 0);
    cli_writeln('  agregada: ' . $p->name);
}

@unlink($ruta);
purge_all_caches();
cli_writeln('Total de preguntas en la evaluación: ' . $DB->count_records('quiz_slots', ['quizid' => $quiz->id]));
