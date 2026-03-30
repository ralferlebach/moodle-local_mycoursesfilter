<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Filtered view of the current user's courses.
 *
 * @package    local_mycoursesfilter
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/lib.php');

require_login();

$q = optional_param('q', '', PARAM_TEXT);
$tag = optional_param('tag', '', PARAM_TEXT);
$catid = optional_param('catid', 0, PARAM_INT);
$field = optional_param('field', '', PARAM_ALPHANUMEXT);
$fvalue = optional_param('value', '', PARAM_TEXT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$status = optional_param('status', 'any', PARAM_ALPHA);
$sort = optional_param('sort', 'lastaccess', PARAM_ALPHA);
$dir = optional_param('dir', 'desc', PARAM_ALPHA);

$statusallowed = ['any', 'notstarted', 'inprogress', 'completed'];
$sortallowed = ['lastaccess', 'alpha', 'shortname', 'lastenrolled'];
$dirallowed = ['asc', 'desc'];

if (!in_array($status, $statusallowed, true)) {
    $status = 'any';
}
if (!in_array($sort, $sortallowed, true)) {
    $sort = 'lastaccess';
}
if (!in_array($dir, $dirallowed, true)) {
    $dir = 'desc';
}

local_mycoursesfilter_require_any_course_role(['student']);

$pageparams = [
    'q' => $q,
    'tag' => $tag,
    'catid' => $catid,
    'field' => $field,
    'value' => $fvalue,
    'status' => $status,
    'sort' => $sort,
    'dir' => $dir,
];
if ($returnurl !== '') {
    $pageparams['returnurl'] = $returnurl;
}

$pageurl = new moodle_url('/local/mycoursesfilter/index.php', $pageparams);

$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pagetitle', 'local_mycoursesfilter'));
$PAGE->set_heading(get_string('pagetitle', 'local_mycoursesfilter'));

$fields = 'id, fullname, shortname, category, visible, summary, summaryformat, idnumber';
$courses = enrol_get_my_courses($fields, null, 0);
$courseids = array_keys($courses);
$meta = local_mycoursesfilter_get_meta_for_courses($courseids);

$filtered = [];
foreach ($courses as $course) {
    if (!local_mycoursesfilter_match_name($course, $q)) {
        continue;
    }
    if (!local_mycoursesfilter_match_category($course, $catid)) {
        continue;
    }
    if ($tag !== '' && !local_mycoursesfilter_match_tag((int)$course->id, $tag)) {
        continue;
    }
    if ($field !== '' && !local_mycoursesfilter_match_customfield((int)$course->id, $field, $fvalue)) {
        continue;
    }

    $cmeta = $meta[$course->id] ?? null;
    if (!local_mycoursesfilter_match_status($course, $cmeta, $status)) {
        continue;
    }

    $filtered[] = $course;
}

usort($filtered, static function (stdClass $a, stdClass $b) use ($meta, $sort, $dir): int {
    $adata = $meta[$a->id] ?? [];
    $bdata = $meta[$b->id] ?? [];

    switch ($sort) {
        case 'alpha':
            $comparison = strcasecmp($a->fullname ?? '', $b->fullname ?? '');
            break;
        case 'shortname':
            $comparison = strcasecmp($a->shortname ?? '', $b->shortname ?? '');
            break;
        case 'lastenrolled':
            $comparison = ($adata['lastenrolled'] ?? 0) <=> ($bdata['lastenrolled'] ?? 0);
            break;
        case 'lastaccess':
        default:
            $comparison = ($adata['lastaccess'] ?? 0) <=> ($bdata['lastaccess'] ?? 0);
            break;
    }

    if ($dir === 'asc') {
        return $comparison;
    }

    return -$comparison;
});

$templatecontext = [
    'showbackbutton' => $returnurl !== '',
    'backurl' => $returnurl,
    'backlabel' => get_string('back'),
    'heading' => get_string('filteredcourses', 'local_mycoursesfilter', count($filtered)),
    'hascourses' => !empty($filtered),
    'nocoursefound' => get_string('nocoursefound', 'local_mycoursesfilter'),
    'cardshtml' => '',
];

if (!empty($filtered)) {
    $cardscontext = local_mycoursesfilter_export_course_cards_context($filtered);
    $templatecontext['cardshtml'] = $OUTPUT->render_from_template('core_course/view-cards', $cardscontext);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mycoursesfilter/page', $templatecontext);
echo $OUTPUT->footer();
