<?php
/**
 * Prepara el entorno local de pruebas del SAMCE:
 *  - Ajusta el nombre del sitio para replicar el Campus Virtual de la FRSFCO-UTN.
 *  - Crea una categoría y un curso de prueba.
 *  - Crea un docente y alumnos con número de legajo.
 *  - Los matricula en el curso.
 *  - Crea una evaluación (quiz) con preguntas, sobre la cual probar el complemento.
 *
 * Ejecutar:  docker exec moodle-docker-webserver-1 php samce_setup.php
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

// El script se ejecuta con la identidad del administrador del sitio.
\core\session\manager::set_user(get_admin());

cli_heading('Preparación del entorno de pruebas SAMCE');

// ---------------------------------------------------------------- Sitio
$sitio = $DB->get_record('course', ['id' => SITEID]);
$sitio->fullname = 'CAMPUS VIRTUAL - FAC. REGIONAL SAN FRANCISCO';
$sitio->shortname = 'CV FRSF';
$sitio->summary = 'Entorno local de pruebas para el desarrollo y la validación del SAMCE.';
$DB->update_record('course', $sitio);
cli_writeln('Sitio renombrado: ' . $sitio->fullname);

// ---------------------------------------------------------------- Categoría
$catnombre = 'Ingeniería en Sistemas de Información';
if (!$cat = $DB->get_record('course_categories', ['name' => $catnombre])) {
    $cat = core_course_category::create(['name' => $catnombre, 'idnumber' => 'ISI']);
    cli_writeln('Categoría creada: ' . $catnombre);
} else {
    cli_writeln('Categoría existente: ' . $catnombre);
}

// ---------------------------------------------------------------- Curso
$cursoshort = 'SI2-2026';
if (!$curso = $DB->get_record('course', ['shortname' => $cursoshort])) {
    $datos = new stdClass();
    $datos->fullname = 'Sistemas de Información II';
    $datos->shortname = $cursoshort;
    $datos->idnumber = 'SI2';
    $datos->category = $cat->id;
    $datos->summary = 'Curso de prueba para la validación del SAMCE.';
    $datos->format = 'topics';
    $datos->numsections = 3;
    $datos->visible = 1;
    $curso = create_course($datos);
    cli_writeln('Curso creado: ' . $datos->fullname . ' (id ' . $curso->id . ')');
} else {
    cli_writeln('Curso existente: ' . $curso->fullname . ' (id ' . $curso->id . ')');
}
$contextocurso = context_course::instance($curso->id);

// ---------------------------------------------------------------- Usuarios
function samce_usuario($username, $nombre, $apellido, $legajo) {
    global $DB, $CFG;
    if ($u = $DB->get_record('user', ['username' => $username])) {
        return $u;
    }
    $u = new stdClass();
    $u->username = $username;
    $u->password = 'Samce.2026';
    $u->firstname = $nombre;
    $u->lastname = $apellido;
    $u->email = $username . '@samce.local';
    $u->idnumber = $legajo;          // legajo institucional
    $u->auth = 'manual';
    $u->confirmed = 1;
    $u->mnethostid = $CFG->mnet_localhost_id;
    $u->lang = 'es';
    $u->timezone = 'America/Argentina/Cordoba';
    $u->id = user_create_user($u, true, false);
    return $DB->get_record('user', ['id' => $u->id]);
}

$docente = samce_usuario('docente.demo', 'Docente', 'de Prueba', 'DOC-001');
cli_writeln('Docente: ' . $docente->username . ' (legajo ' . $docente->idnumber . ')');

$alumnos = [];
$nombres = [['Ana', 'Gómez', '16001'], ['Bruno', 'Pérez', '16002'], ['Carla', 'Suárez', '16003']];
foreach ($nombres as $i => $n) {
    $a = samce_usuario('alumno' . ($i + 1), $n[0], $n[1], $n[2]);
    $alumnos[] = $a;
    cli_writeln('Alumno: ' . $a->username . ' (legajo ' . $a->idnumber . ')');
}

// ---------------------------------------------------------------- Matriculación
$enrol = enrol_get_plugin('manual');
$instancias = enrol_get_instances($curso->id, true);
$instancia = null;
foreach ($instancias as $ins) {
    if ($ins->enrol === 'manual') {
        $instancia = $ins;
        break;
    }
}
if ($instancia) {
    $roldocente = $DB->get_record('role', ['shortname' => 'editingteacher']);
    $rolalumno = $DB->get_record('role', ['shortname' => 'student']);
    $enrol->enrol_user($instancia, $docente->id, $roldocente->id);
    foreach ($alumnos as $a) {
        $enrol->enrol_user($instancia, $a->id, $rolalumno->id);
    }
    cli_writeln('Usuarios matriculados en el curso');
}

// ---------------------------------------------------------------- Evaluación
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/testing/generator/data_generator.php');

if (!$DB->record_exists('quiz', ['course' => $curso->id, 'name' => 'Primer Parcial'])) {
    $generador = new testing_data_generator();
    $genquiz = $generador->get_plugin_generator('mod_quiz');
    $quiz = $genquiz->create_instance([
        'course' => $curso->id,
        'name' => 'Primer Parcial',
        'intro' => 'Evaluación de prueba para la validación del SAMCE.',
        'timelimit' => 3600,
        'grade' => 100.0,
        'questionsperpage' => 1,
        'navmethod' => 'free',
        'preferredbehaviour' => 'deferredfeedback',
    ]);
    cli_writeln('Evaluación creada: Primer Parcial (cmid ' . $quiz->cmid . ')');

    // Preguntas de desarrollo, que requieren escritura y permiten observar la cadencia de tipeo.
    require_once($CFG->dirroot . '/question/format.php');
    require_once($CFG->dirroot . '/question/format/xml/format.php');

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
        $xml .= "    <defaultgrade>1</defaultgrade>\n";
        $xml .= "    <responseformat>editor</responseformat>\n";
        $xml .= "    <responsefieldlines>10</responsefieldlines>\n";
        $xml .= "    <attachments>0</attachments>\n";
        $xml .= "  </question>\n";
    }
    $xml .= "</quiz>\n";

    $rutaxml = $CFG->tempdir . '/samce_preguntas.xml';
    file_put_contents($rutaxml, $xml);

    $categoria = question_make_default_categories([$contextocurso]);
    $formato = new qformat_xml();
    $formato->setCategory($categoria);
    $formato->setContexts([$contextocurso]);
    $formato->setCourse($curso);
    $formato->setFilename($rutaxml);
    $formato->setRealfilename('samce_preguntas.xml');
    $formato->setMatchgrades('error');
    $formato->setCatfromfile(false);
    $formato->setContextfromfile(false);
    $formato->setStoponerror(true);

    ob_start();
    $ok = $formato->importpreprocess() && $formato->importprocess() && $formato->importpostprocess();
    ob_end_clean();

    if ($ok) {
        $preguntas = $DB->get_records_sql(
            "SELECT q.id, q.name
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qbe.questioncategoryid = ?
           ORDER BY q.id", [$categoria->id]);
        foreach ($preguntas as $p) {
            quiz_add_quiz_question($p->id, $quiz, 0);
        }
        cli_writeln('Preguntas agregadas a la evaluación: ' . count($preguntas));
    } else {
        cli_writeln('AVISO: no se pudieron importar las preguntas; agregarlas desde la interfaz.');
    }
    @unlink($rutaxml);
} else {
    cli_writeln('La evaluación ya existía');
}

purge_all_caches();
cli_heading('Entorno listo');
cli_writeln('Sitio:    http://localhost:8000');
cli_writeln('Admin:    admin / Samce.2026');
cli_writeln('Docente:  docente.demo / Samce.2026');
cli_writeln('Alumnos:  alumno1, alumno2, alumno3 / Samce.2026');
cli_writeln('Curso:    Sistemas de Información II  ->  evaluación "Primer Parcial"');
