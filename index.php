<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

require_login();

// 1) Rollenprüfung (optional aktivieren)
require_once(__DIR__ . '/lib.php');
local_mycoursesfilter_require_any_course_role(['student']);

// 2) Parameter
$q      = optional_param('q', '', PARAM_TEXT);          // Teilstring Kursname
$tag    = optional_param('tag', '', PARAM_TEXT);        // Tag-Name (oder ID-Variante, siehe unten)
$catid  = optional_param('catid', 0, PARAM_INT);        // Kategorie-ID (Kursbereich)
$field  = optional_param('field', '', PARAM_ALPHANUMEXT); // Customfield shortname
$fvalue = optional_param('value', '', PARAM_TEXT);      // Customfield value

$PAGE->set_url(new moodle_url('/local/mycoursesfilter/index.php', [
    'q' => $q, 'tag' => $tag, 'catid' => $catid, 'field' => $field, 'value' => $fvalue,
]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Meine Kurse (gefiltert)');
$PAGE->set_heading('Meine Kurse (gefiltert)');

// 3) Kurse des Nutzers laden
$courses = enrol_get_my_courses(
    null,
    'visible DESC, sortorder ASC',
    0
);

// 4) Filter anwenden
$filtered = [];
foreach ($courses as $c) {
    if (!local_mycoursesfilter_match_name($c, $q)) {
        continue;
    }
    if ($catid && !local_mycoursesfilter_match_category($c, $catid)) {
        continue;
    }
    if ($tag && !local_mycoursesfilter_match_tag($c->id, $tag)) {
        continue;
    }
    if ($field !== '' && !local_mycoursesfilter_match_customfield($c->id, $field, $fvalue)) {
        continue;
    }
    $filtered[] = $c;
}

// 5) Ausgabe
echo $OUTPUT->header();
echo $OUTPUT->heading('Gefilterte Kurse: ' . count($filtered));

if (empty($filtered)) {
    echo $OUTPUT->notification('Keine Kurse gefunden.', 'info');
} else {
    echo html_writer::start_tag('ul');
    foreach ($filtered as $c) {
        $url = new moodle_url('/course/view.php', ['id' => $c->id]);
        echo html_writer::tag('li', html_writer::link($url, format_string($c->fullname)));
    }
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();


// ---------- Filterfunktionen ----------
function local_mycoursesfilter_match_name(stdClass $course, string $q): bool {
    if ($q === '') return true;
    $hay = mb_strtolower($course->fullname ?? '');
    $needle = mb_strtolower($q);
    return (mb_strpos($hay, $needle) !== false);
}

function local_mycoursesfilter_match_category(stdClass $course, int $targetcatid): bool {
    if (!$targetcatid) return true;

    // Direkt oder in Unterkategorie? -> prüfe Category-Pfad
    $cat = \core_course_category::get($course->category, IGNORE_MISSING);
    if (!$cat) return false;

    // path ist z.B. "/1/5/23"
    $path = $cat->path ?? '';
    return (strpos($path . '/', '/' . $targetcatid . '/') !== false);
}

function local_mycoursesfilter_match_tag(int $courseid, string $tag): bool {
    global $DB;

    // Unterstütze entweder Tag-ID ("123") oder Tag-Name ("Mathematik")
    if (ctype_digit($tag)) {
        $tagid = (int)$tag;
    } else {
        $tagid = (int)$DB->get_field('tag', 'id', ['name' => $tag], IGNORE_MISSING);
        if (!$tagid) return false;
    }

    // tag_instance: component='core', itemtype='course', itemid=<courseid>
    return $DB->record_exists('tag_instance', [
        'component' => 'core',
        'itemtype'  => 'course',
        'itemid'    => $courseid,
        'tagid'     => $tagid,
    ]);
}

function local_mycoursesfilter_match_customfield(int $courseid, string $shortname, string $value): bool {
    // Customfield handler für Kurse
    $handler = \core_course\customfield\course_handler::get_handler();
    $data = $handler->get_instance_data($courseid, true);

    // $data ist Array von DataController-Objekten; jedes hat ->get_field()->get('shortname') und ->export_value()
    foreach ($data as $d) {
        $f = $d->get_field();
        if ($f && $f->get('shortname') === $shortname) {
            $actual = trim((string)$d->export_value());
            if ($value === '') {
                // nur "ist gesetzt"
                return ($actual !== '');
            }
            return (mb_strtolower($actual) === mb_strtolower(trim($value)));
        }
    }
    return false;
}
