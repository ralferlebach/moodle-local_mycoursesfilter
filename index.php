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
$rawcatid = optional_param('catid', '', PARAM_RAW_TRIMMED);
$field = optional_param('field', '', PARAM_ALPHANUMEXT);
$fvalue = optional_param('value', '', PARAM_TEXT);
$rawreturnurl = optional_param('returnurl', '', PARAM_RAW_TRIMMED);
$legacyfilter = optional_param('status', '', PARAM_ALPHA);
$titleoverride = optional_param('title', '', PARAM_TEXT);
$explicitcourseid = optional_param('courseid', 0, PARAM_INT);
$only = optional_param('only', 0, PARAM_BOOL) === 1;
$recursive = optional_param('recursive', 0, PARAM_BOOL) === 1;

$returnurl = local_mycoursesfilter_resolve_return_url($rawreturnurl);
$sourcecourseid = local_mycoursesfilter_resolve_source_course_id($explicitcourseid);
$categoryscope = local_mycoursesfilter_resolve_category_scope($only, $recursive);
$resolvedcategoryids = local_mycoursesfilter_resolve_category_ids($rawcatid, $categoryscope, $sourcecourseid);
$normalisedcatid = local_mycoursesfilter_normalise_category_ids_param($resolvedcategoryids);
$pagetitle = local_mycoursesfilter_resolve_page_title($titleoverride);

$filterlabels = local_mycoursesfilter_get_filter_labels();
$sortlabels = local_mycoursesfilter_get_sort_labels();
$viewlabels = local_mycoursesfilter_get_view_labels();

$filterallowed = array_keys($filterlabels);
$sortallowed = array_keys($sortlabels);
$dirallowed = ['asc', 'desc'];
$viewallowed = array_keys($viewlabels);

$filter = local_mycoursesfilter_resolve_toolbar_preference('filter', 'all', PARAM_ALPHA, $filterallowed);
$sort = local_mycoursesfilter_resolve_toolbar_preference('sort', 'lastaccess', PARAM_ALPHA, $sortallowed);
$dir = local_mycoursesfilter_resolve_toolbar_preference('dir', 'desc', PARAM_ALPHA, $dirallowed);
$view = local_mycoursesfilter_resolve_toolbar_preference('view', 'card', PARAM_ALPHA, $viewallowed);

if ($legacyfilter !== '' && !array_key_exists('filter', $_GET) && in_array($legacyfilter, $filterallowed, true)) {
    $filter = $legacyfilter;
    set_user_preference('local_mycoursesfilter_filter', $filter, $USER->id);
}

if ($filter === 'any') {
    $filter = 'all';
}

local_mycoursesfilter_require_any_course_role(['student']);

$pageparams = [
    'q' => $q,
    'tag' => $tag,
    'catid' => $normalisedcatid,
    'field' => $field,
    'value' => $fvalue,
    'filter' => $filter,
    'sort' => $sort,
    'dir' => $dir,
    'view' => $view,
    'title' => local_mycoursesfilter_is_title_override_enabled() ? $titleoverride : '',
];
if ($returnurl !== '') {
    $pageparams['returnurl'] = $returnurl;
}
if ($explicitcourseid > 0) {
    $pageparams['courseid'] = $explicitcourseid;
}
if ($only) {
    $pageparams['only'] = 1;
}
if ($recursive) {
    $pageparams['recursive'] = 1;
}

$pageurl = new moodle_url('/local/mycoursesfilter/index.php', array_filter($pageparams, static function ($item): bool {
    return $item !== '' && $item !== 0;
}));

$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->add_body_classes(['limitedwidth', 'page-mycourses']);
$PAGE->set_pagelayout('mycourses');
$PAGE->set_pagetype('my-index');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
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
    if (!local_mycoursesfilter_match_categories($course, $resolvedcategoryids)) {
        continue;
    }
    if ($tag !== '' && !local_mycoursesfilter_match_tag((int)$course->id, $tag)) {
        continue;
    }
    if ($field !== '' && !local_mycoursesfilter_match_customfield((int)$course->id, $field, $fvalue)) {
        continue;
    }

    $cmeta = $meta[$course->id] ?? null;
    if (!local_mycoursesfilter_match_filter($cmeta, $filter)) {
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

$toolbarparams = [
    'q' => $q,
    'tag' => $tag,
    'catid' => $normalisedcatid,
    'field' => $field,
    'value' => $fvalue,
    'filter' => $filter,
    'sort' => $sort,
    'dir' => $dir,
    'view' => $view,
    'title' => local_mycoursesfilter_is_title_override_enabled() ? $titleoverride : '',
    'returnurl' => $returnurl,
];
if ($explicitcourseid > 0) {
    $toolbarparams['courseid'] = $explicitcourseid;
}
if ($only) {
    $toolbarparams['only'] = 1;
}
if ($recursive) {
    $toolbarparams['recursive'] = 1;
}

$templatecontext = [
    'pageheading' => $pagetitle,
    'showbackbutton' => $returnurl !== '',
    'backurl' => $returnurl,
    'backlabel' => get_string('back'),
    'sectiontitle' => get_string('courseoverview', 'local_mycoursesfilter'),
    'toolbararialabel' => get_string('toolbararia', 'local_mycoursesfilter'),
    'hascourses' => !empty($filtered),
    'nocoursefound' => get_string('nocoursefound', 'local_mycoursesfilter'),
    'courseshtml' => $courseshtml,
    'groupingbuttonlabel' => local_mycoursesfilter_get_active_toolbar_label($filterlabels, $filter, 'all'),
    'groupingitems' => local_mycoursesfilter_build_dropdown_items(
        $filterlabels,
        'filter',
        $filter,
        $toolbarparams
    ),
    'groupingarialabel' => get_string('groupingarialabel', 'local_mycoursesfilter'),
    'searchformurl' => (new moodle_url('/local/mycoursesfilter/index.php'))->out(false),
    'searchhiddeninputs' => local_mycoursesfilter_build_hidden_inputs([
        'tag' => $tag,
        'catid' => $normalisedcatid,
        'field' => $field,
        'value' => $fvalue,
        'filter' => $filter,
        'sort' => $sort,
        'dir' => $dir,
        'view' => $view,
        'title' => local_mycoursesfilter_is_title_override_enabled() ? $titleoverride : '',
        'returnurl' => $returnurl,
        'courseid' => $explicitcourseid,
        'only' => $only ? 1 : 0,
        'recursive' => $recursive ? 1 : 0,
    ]),
    'searchinputlabel' => get_string('searchbyname', 'local_mycoursesfilter'),
    'searchplaceholder' => get_string('search'),
    'searchquery' => $q,
    'sortingbuttonlabel' => local_mycoursesfilter_get_active_toolbar_label($sortlabels, $sort, 'lastaccess'),
    'sortingitems' => local_mycoursesfilter_build_dropdown_items(
        $sortlabels,
        'sort',
        $sort,
        $toolbarparams
    ),
    'sortingarialabel' => get_string('sortingarialabel', 'local_mycoursesfilter'),
    'displaybuttonlabel' => local_mycoursesfilter_get_active_toolbar_label($viewlabels, $view, 'card'),
    'displayitems' => local_mycoursesfilter_build_dropdown_items(
        $viewlabels,
        'view',
        $view,
        $toolbarparams
    ),
    'displayarialabel' => get_string('displayarialabel', 'local_mycoursesfilter'),
    'currentview' => $view,
    'currentfilter' => $filter,
    'currentsort' => $sort,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mycoursesfilter/page', $templatecontext);
echo $OUTPUT->footer();
