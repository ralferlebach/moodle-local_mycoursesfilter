<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/course/classes/external/course_summary_exporter.php');
require_once(__DIR__ . '/lib.php');

require_login();

// --------- Parameter (sanitised) ----------
$q      = optional_param('q', '', PARAM_TEXT);                 // Name-Teilstring
$tag    = optional_param('tag', '', PARAM_TEXT);               // Tagname oder TagID
$catid  = optional_param('catid', 0, PARAM_INT);               // KategorieID
$field  = optional_param('field', '', PARAM_ALPHANUMEXT);      // Customfield shortname
$fvalue = optional_param('value', '', PARAM_TEXT);             // Customfield value

$status = optional_param('status', 'any', PARAM_ALPHA);        // any|notstarted|inprogress|completed
$sort   = optional_param('sort', 'lastaccess', PARAM_ALPHA);   // lastaccess|alpha|shortname|lastenrolled
$dir    = optional_param('dir', 'desc', PARAM_ALPHA);          // asc|desc

// Whitelists gegen Parameter-Missbrauch/Injection über Sort/Status:
$statusallowed = ['any', 'notstarted', 'inprogress', 'completed'];
$sortallowed   = ['lastaccess', 'alpha', 'shortname', 'lastenrolled'];
$dirallowed    = ['asc', 'desc'];

if (!in_array($status, $statusallowed, true)) { $status = 'any'; }
if (!in_array($sort,   $sortallowed,   true)) { $sort   = 'lastaccess'; }
if (!in_array($dir,    $dirallowed,    true)) { $dir    = 'desc'; }

// Optional: Rollenprüfung (z.B. nur Studierende)
local_mycoursesfilter_require_any_course_role(['student']);

// Back-URL: bevorzugt returnurl aus Param (lokal!), sonst sicherer Referer.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
if (empty($returnurl)) {
    $returnurl = get_local_referer(false) ?: (new moodle_url('/my/courses.php'))->out(false);
}

$PAGE->set_url(new moodle_url('/local/mycoursesfilter/index.php', [
    'q' => $q, 'tag' => $tag, 'catid' => $catid, 'field' => $field, 'value' => $fvalue,
    'status' => $status, 'sort' => $sort, 'dir' => $dir,
    'returnurl' => $returnurl,
]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('mycourses'));
$PAGE->set_heading(get_string('mycourses'));

// --------- Kurse laden (nur eigene) ----------
$fields = 'id, fullname, shortname, category, visible, summary, summaryformat, idnumber';
$courses = enrol_get_my_courses($fields, null, 0);

// --------- Metadaten für Sort/Filter ----------
$courseids = array_keys($courses);
$meta = local_mycoursesfilter_get_meta_for_courses($courseids); // lastaccess, lastenrolled, completion

// --------- Filter anwenden ----------
$filtered = [];
foreach ($courses as $c) {

    if (!local_mycoursesfilter_match_name($c, $q)) {
        continue;
    }
    if ($catid && !local_mycoursesfilter_match_category($c, $catid)) {
        continue;
    }
    if ($tag !== '' && !local_mycoursesfilter_match_tag($c->id, $tag)) {
        continue;
    }
    if ($field !== '' && !local_mycoursesfilter_match_customfield($c->id, $field, $fvalue)) {
        continue;
    }

    $cmeta = $meta[$c->id] ?? null;
    if (!local_mycoursesfilter_match_status($c, $cmeta, $status)) {
        continue;
    }

    $filtered[] = $c;
}

// --------- Sortierung ----------
usort($filtered, function($a, $b) use ($meta, $sort, $dir) {
    $ad = $meta[$a->id] ?? [];
    $bd = $meta[$b->id] ?? [];

    $cmp = 0;
    switch ($sort) {
        case 'alpha':
            $cmp = strcasecmp($a->fullname ?? '', $b->fullname ?? '');
            break;
        case 'shortname':
            $cmp = strcasecmp($a->shortname ?? '', $b->shortname ?? '');
            break;
        case 'lastenrolled':
            $cmp = ($ad['lastenrolled'] ?? 0) <=> ($bd['lastenrolled'] ?? 0);
            break;
        case 'lastaccess':
        default:
            $cmp = ($ad['lastaccess'] ?? 0) <=> ($bd['lastaccess'] ?? 0);
            break;
    }
    return ($dir === 'asc') ? $cmp : -$cmp;
});

// --------- Rendering als Course Cards ----------
$renderer = $PAGE->get_renderer('core_course');

$coursedata = [];
foreach ($filtered as $course) {
    // Moodle-konformer Exporter für Course Cards:
    $exporter = new \course_summary_exporter($course, ['context' => context_course::instance($course->id)]);
    $coursedata[] = $exporter->export($renderer);
}

echo $OUTPUT->header();

// Zurück-Button
echo $OUTPUT->single_button(new moodle_url($returnurl), get_string('back'), 'get');

// Optional: kleine Filterzusammenfassung/Heading
echo $OUTPUT->heading(get_string('mycourses') . ' (' . count($coursedata) . ')');

// Kacheln: core_course/view-cards
echo $OUTPUT->render_from_template('core_course/view-cards', [
    'courses' => $coursedata,
]);

echo $OUTPUT->footer();
