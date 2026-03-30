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

$legacyfilter = optional_param('status', '', PARAM_ALPHA);
$filter = optional_param('filter', $legacyfilter !== '' ? $legacyfilter : 'all', PARAM_ALPHA);
$sort = optional_param('sort', 'lastaccess', PARAM_ALPHA);
$dir = optional_param('dir', 'desc', PARAM_ALPHA);
$view = optional_param('view', 'card', PARAM_ALPHA);

if ($filter === 'any') {
    $filter = 'all';
}

$filterallowed = ['all', 'notstarted', 'inprogress', 'completed', 'favourites', 'hidden'];
$sortallowed = ['lastaccess', 'alpha', 'shortname', 'lastenrolled'];
$dirallowed = ['asc', 'desc'];
$viewallowed = ['card', 'list', 'summary'];

if (!in_array($filter, $filterallowed, true)) {
    $filter = 'all';
}
if (!in_array($sort, $sortallowed, true)) {
    $sort = 'lastaccess';
}
if (!in_array($dir, $dirallowed, true)) {
    $dir = 'desc';
}
if (!in_array($view, $viewallowed, true)) {
    $view = 'card';
}

local_mycoursesfilter_require_any_course_role(['student']);

$pageparams = [
    'q' => $q,
    'tag' => $tag,
    'catid' => $catid,
    'field' => $field,
    'value' => $fvalue,
    'filter' => $filter,
    'sort' => $sort,
    'dir' => $dir,
    'view' => $view,
];
if ($returnurl !== '') {
    $pageparams['returnurl'] = $returnurl;
}

$pageurl = new moodle_url('/local/mycoursesfilter/index.php', $pageparams);

$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pagetitle', 'local_mycoursesfilter'));
$PAGE->set_heading(get_string('pagetitle', 'local_mycoursesfilter'));
$PAGE->requires->css(new moodle_url('/local/mycoursesfilter/styles.css'));

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
    if (!local_mycoursesfilter_match_filter($course, $cmeta, $filter)) {
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

$cardscontext = local_mycoursesfilter_export_course_cards_context($filtered, $meta);
$courseshtml = '';
if (!empty($filtered)) {
    if ($view === 'list') {
        $courseshtml = $OUTPUT->render_from_template('local_mycoursesfilter/course_list', $cardscontext);
    } else if ($view === 'summary') {
        $courseshtml = $OUTPUT->render_from_template('local_mycoursesfilter/course_summary_list', $cardscontext);
    } else {
        $courseshtml = $OUTPUT->render_from_template('local_mycoursesfilter/course_grid', $cardscontext);
    }
}

$templatecontext = [
    'showbackbutton' => $returnurl !== '',
    'backurl' => $returnurl,
    'backlabel' => get_string('back'),
    'sectiontitle' => get_string('courseoverview', 'local_mycoursesfilter'),
    'resultsheading' => get_string('filteredcourses', 'local_mycoursesfilter', count($filtered)),
    'hascourses' => !empty($filtered),
    'nocoursefound' => get_string('nocoursefound', 'local_mycoursesfilter'),
    'courseshtml' => $courseshtml,
    'filterlinks' => local_mycoursesfilter_build_filter_links([
        'q' => $q,
        'tag' => $tag,
        'catid' => $catid,
        'field' => $field,
        'value' => $fvalue,
        'sort' => $sort,
        'dir' => $dir,
        'view' => $view,
        'returnurl' => $returnurl,
    ], $filter),
    'formurl' => (new moodle_url('/local/mycoursesfilter/index.php'))->out(false),
    'hiddeninputs' => local_mycoursesfilter_build_hidden_inputs([
        'tag' => $tag,
        'catid' => $catid,
        'field' => $field,
        'value' => $fvalue,
        'dir' => $dir,
        'returnurl' => $returnurl,
    ]),
    'searchquery' => $q,
    'searchlabel' => get_string('searchbyname', 'local_mycoursesfilter'),
    'filterlabel' => get_string('filterlabel', 'local_mycoursesfilter'),
    'sortlabel' => get_string('sortlabel', 'local_mycoursesfilter'),
    'viewlabel' => get_string('viewlabel', 'local_mycoursesfilter'),
    'submitlabel' => get_string('applyfilters', 'local_mycoursesfilter'),
    'reseturl' => local_mycoursesfilter_build_reset_url([
        'tag' => $tag,
        'catid' => $catid,
        'field' => $field,
        'value' => $fvalue,
        'returnurl' => $returnurl,
    ])->out(false),
    'resetlabel' => get_string('resetfilters', 'local_mycoursesfilter'),
    'sortoptions' => local_mycoursesfilter_get_sort_options($sort),
    'viewoptions' => local_mycoursesfilter_get_view_options($view),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mycoursesfilter/page', $templatecontext);
echo $OUTPUT->footer();
