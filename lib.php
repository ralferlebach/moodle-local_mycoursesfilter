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
 * Plugin library for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');


/**
 * Resolves a persisted toolbar preference.
 *
 * Request parameters override stored preferences and are persisted immediately.
 *
 * @param string $name The request parameter and preference suffix.
 * @param string $default The default value.
 * @param string $paramtype The Moodle PARAM_* type.
 * @param string[] $allowed Allowed values.
 * @return string
 */
function local_mycoursesfilter_resolve_toolbar_preference(
    string $name,
    string $default,
    string $paramtype,
    array $allowed
): string {
    global $USER;

    $prefname = 'local_mycoursesfilter_' . $name;

    if (array_key_exists($name, $_GET)) {
        $value = optional_param($name, $default, $paramtype);
    } else {
        $value = get_user_preferences($prefname, $default, $USER->id);
    }

    if (!in_array($value, $allowed, true)) {
        $value = $default;
    }

    if (array_key_exists($name, $_GET)) {
        set_user_preference($prefname, $value, $USER->id);
    }

    return $value;
}

/**
 * Returns the available filter labels.
 *
 * @return array<string, string>
 */
function local_mycoursesfilter_get_filter_labels(): array {
    return [
        'all' => get_string('filter_all', 'local_mycoursesfilter'),
        'notstarted' => get_string('filter_notstarted', 'local_mycoursesfilter'),
        'inprogress' => get_string('filter_inprogress', 'local_mycoursesfilter'),
        'completed' => get_string('filter_completed', 'local_mycoursesfilter'),
        'favourites' => get_string('filter_favourites', 'local_mycoursesfilter'),
        'hidden' => get_string('filter_hidden', 'local_mycoursesfilter'),
    ];
}

/**
 * Ensures that the current user has at least one of the supplied course roles.
 *
 * @param string[] $roleshortnames The role shortnames to accept.
 * @return void
 * @throws moodle_exception If the current user does not match the required roles.
 */
function local_mycoursesfilter_require_any_course_role(array $roleshortnames): void {
    global $DB, $USER;

    if (is_siteadmin($USER)) {
        return;
    }

    if (empty($roleshortnames)) {
        return;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($roleshortnames, SQL_PARAMS_NAMED, 'role');
    $params = $inparams + [
        'userid' => $USER->id,
        'contextlevel' => CONTEXT_COURSE,
    ];

    $sql = "SELECT 1
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ra.userid = :userid
               AND ctx.contextlevel = :contextlevel
               AND r.shortname {$insql}";

    if (!$DB->record_exists_sql($sql, $params)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_mycoursesfilter'));
    }
}

/**
 * Fetches metadata required for filtering and sorting the selected courses.
 *
 * @param int[] $courseids Course IDs.
 * @return array<int, array<string, int|bool>>
 */
function local_mycoursesfilter_get_meta_for_courses(array $courseids): array {
    global $DB, $USER;

    if (empty($courseids)) {
        return [];
    }

    $meta = [];
    foreach ($courseids as $courseid) {
        $meta[$courseid] = [
            'lastaccess' => 0,
            'lastenrolled' => 0,
            'completionenabled' => false,
            'iscompleted' => false,
            'timecompleted' => 0,
            'timestarted' => 0,
            'timeenrolled' => 0,
            'ishidden' => false,
            'isfavourite' => false,
        ];
    }

    [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
    $params['userid'] = $USER->id;

    $sql = "SELECT courseid, timeaccess
              FROM {user_lastaccess}
             WHERE userid = :userid
               AND courseid {$insql}";
    foreach ($DB->get_records_sql($sql, $params) as $record) {
        $meta[(int)$record->courseid]['lastaccess'] = (int)$record->timeaccess;
    }

    $sql = "SELECT e.courseid, MAX(ue.timecreated) AS lastenrolled
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
             WHERE ue.userid = :userid
               AND e.courseid {$insql}
          GROUP BY e.courseid";
    foreach ($DB->get_records_sql($sql, $params) as $record) {
        $meta[(int)$record->courseid]['lastenrolled'] = (int)$record->lastenrolled;
        $meta[(int)$record->courseid]['timeenrolled'] = (int)$record->lastenrolled;
    }

    $sql = "SELECT c.id, c.enablecompletion, cc.timecompleted, cc.timestarted
              FROM {course} c
         LEFT JOIN {course_completions} cc
                ON cc.course = c.id
               AND cc.userid = :userid
             WHERE c.id {$insql}";
    foreach ($DB->get_records_sql($sql, $params) as $record) {
        $courseid = (int)$record->id;
        $meta[$courseid]['completionenabled'] = !empty($record->enablecompletion);
        $meta[$courseid]['timecompleted'] = (int)($record->timecompleted ?? 0);
        $meta[$courseid]['timestarted'] = (int)($record->timestarted ?? 0);
        $meta[$courseid]['iscompleted'] = (int)($record->timecompleted ?? 0) > 0;
    }

    foreach ($courseids as $courseid) {
        $meta[$courseid]['ishidden'] = (int)get_user_preferences('block_myoverview_hidden_course_' . $courseid, 0, $USER) === 1;
    }

    $usercontext = context_user::instance($USER->id);
    $favouriteservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
    [$favsql, $favparams] = $favouriteservice->get_join_sql_by_type('core_course', 'courses', 'f', 'c.id');
    $favouritesql = "SELECT c.id, f.id AS favouriteid
                       FROM {course} c
                  {$favsql}
                      WHERE c.id {$insql}
                        AND f.id IS NOT NULL";
    foreach ($DB->get_records_sql($favouritesql, $params + $favparams) as $record) {
        $meta[(int)$record->id]['isfavourite'] = true;
    }

    return $meta;
}

/**
 * Checks whether the course matches the selected name query.
 *
 * @param stdClass $course The course record.
 * @param string $query The user query.
 * @return bool
 */
function local_mycoursesfilter_match_name(stdClass $course, string $query): bool {
    if ($query === '') {
        return true;
    }

    $needle = core_text::strtolower(trim($query));
    $fullname = core_text::strtolower((string)($course->fullname ?? ''));
    $shortname = core_text::strtolower((string)($course->shortname ?? ''));

    return (core_text::strpos($fullname, $needle) !== false) || (core_text::strpos($shortname, $needle) !== false);
}

/**
 * Checks whether the course matches the selected category.
 *
 * @param stdClass $course The course record.
 * @param int $categoryid The selected category id.
 * @return bool
 */
function local_mycoursesfilter_match_category(stdClass $course, int $categoryid): bool {
    if ($categoryid <= 0) {
        return true;
    }

    return (int)($course->category ?? 0) === $categoryid;
}

/**
 * Checks whether the course matches the selected tag.
 *
 * The tag parameter can either be a numeric tag id or a tag name.
 *
 * @param int $courseid The course id.
 * @param string $tag The tag id or tag name.
 * @return bool
 */
function local_mycoursesfilter_match_tag(int $courseid, string $tag): bool {
    if ($tag === '') {
        return true;
    }

    if (ctype_digit($tag)) {
        foreach (core_tag_tag::get_item_tags('core', 'course', $courseid) as $coursetag) {
            if ((int)$coursetag->id === (int)$tag) {
                return true;
            }
        }

        return false;
    }

    return core_tag_tag::is_item_tagged_with('core', 'course', $courseid, $tag);
}

/**
 * Checks whether the course matches the selected custom field value.
 *
 * If the expected value is empty, any non-empty custom field value is accepted.
 *
 * @param int $courseid The course id.
 * @param string $fieldshortname The custom field shortname.
 * @param string $expectedvalue The expected value.
 * @return bool
 */
function local_mycoursesfilter_match_customfield(int $courseid, string $fieldshortname, string $expectedvalue): bool {
    if ($fieldshortname === '') {
        return true;
    }

    $handler = core_customfield\handler::get_handler('core_course', 'course');
    $fielddata = $handler->get_instance_data($courseid, true);

    foreach ($fielddata as $data) {
        $field = $data->get_field();
        if ($field->get('shortname') !== $fieldshortname) {
            continue;
        }

        $actualvalue = $data->export_value();
        if (is_array($actualvalue)) {
            $actualvalue = implode(', ', array_map('strval', $actualvalue));
        } else if (is_bool($actualvalue)) {
            $actualvalue = $actualvalue ? '1' : '0';
        } else if ($actualvalue === null) {
            $actualvalue = '';
        } else {
            $actualvalue = (string)$actualvalue;
        }

        if ($expectedvalue === '') {
            return trim($actualvalue) !== '';
        }

        return core_text::strtolower(trim($actualvalue)) === core_text::strtolower(trim($expectedvalue));
    }

    return false;
}

/**
 * Checks whether the course matches the selected filter bucket.
 *
 * @param stdClass $course The course record.
 * @param array|null $meta The metadata for the course.
 * @param string $filter The selected filter.
 * @return bool
 */
function local_mycoursesfilter_match_filter(stdClass $course, ?array $meta, string $filter): bool {
    $meta = $meta ?? [];
    $ishidden = !empty($meta['ishidden']);
    $isfavourite = !empty($meta['isfavourite']);

    if ($filter === 'hidden') {
        return $ishidden;
    }

    if ($ishidden) {
        return false;
    }

    if ($filter === 'favourites') {
        return $isfavourite;
    }

    if ($filter === 'all') {
        return true;
    }

    return local_mycoursesfilter_match_status($course, $meta, $filter);
}

/**
 * Checks whether the course matches the selected progress status.
 *
 * @param stdClass $course The course record.
 * @param array|null $meta The metadata for the course.
 * @param string $status The selected status.
 * @return bool
 */
function local_mycoursesfilter_match_status(stdClass $course, ?array $meta, string $status): bool {
    if ($status === 'all' || $status === 'any') {
        return true;
    }

    $meta = $meta ?? [];

    $completionenabled = !empty($meta['completionenabled']);
    $timecompleted = (int)($meta['timecompleted'] ?? 0);
    $timestarted = (int)($meta['timestarted'] ?? 0);
    $lastaccess = (int)($meta['lastaccess'] ?? 0);
    $timeenrolled = (int)($meta['timeenrolled'] ?? 0);
    $iscompleted = !empty($meta['iscompleted']);

    $hascompletionrecord = ($timecompleted > 0) || ($timestarted > 0) || ($timeenrolled > 0);

    if ($status === 'completed') {
        if ($completionenabled && $hascompletionrecord) {
            return $timecompleted > 0;
        }

        if ($completionenabled) {
            return $iscompleted;
        }

        return false;
    }

    if ($status === 'notstarted') {
        if ($completionenabled && $hascompletionrecord) {
            return ($timestarted === 0) && ($timecompleted === 0);
        }

        return ($lastaccess === 0) && !$iscompleted;
    }

    if ($status === 'inprogress') {
        if ($completionenabled && $hascompletionrecord) {
            return ($timestarted > 0) && ($timecompleted === 0);
        }

        return ($lastaccess > 0) && !$iscompleted;
    }

    return true;
}

/**
 * Builds dropdown item definitions for the toolbar.
 *
 * @param array<string, string> $options Menu options keyed by parameter value.
 * @param string $paramname Parameter name to write.
 * @param string $currentvalue Current selected value.
 * @param array<string, int|string> $baseparams The base URL parameters.
 * @return array<int, array<string, bool|string>>
 */
function local_mycoursesfilter_build_dropdown_items(
    array $options,
    string $paramname,
    string $currentvalue,
    array $baseparams
): array {
    $items = [];

    foreach ($options as $value => $label) {
        $params = $baseparams;
        $params[$paramname] = $value;
        $items[] = [
            'label' => $label,
            'url' => (new moodle_url('/local/mycoursesfilter/index.php', array_filter($params, static function ($item): bool {
                return $item !== '' && $item !== 0;
            })))->out(false),
            'active' => $value === $currentvalue,
        ];
    }

    return $items;
}

/**
 * Returns the currently active toolbar label.
 *
 * @param array<string, string> $options Available option labels.
 * @param string $currentvalue The selected value.
 * @param string $default Default value.
 * @return string
 */
function local_mycoursesfilter_get_active_toolbar_label(array $options, string $currentvalue, string $default): string {
    if (!empty($options[$currentvalue])) {
        return $options[$currentvalue];
    }

    return $options[$default] ?? reset($options) ?: '';
}

/**
 * Builds hidden input definitions for the toolbar form.
 *
 * @param array<string, int|string> $params Additional parameters to preserve.
 * @return array<int, array{name: string, value: string}>
 */
function local_mycoursesfilter_build_hidden_inputs(array $params): array {
    $inputs = [];

    foreach ($params as $name => $value) {
        if ($value === '' || $value === 0) {
            continue;
        }

        $inputs[] = [
            'name' => $name,
            'value' => (string)$value,
        ];
    }

    return $inputs;
}

/**
 * Builds the reset URL while preserving advanced integration parameters.
 *
 * @param array<string, int|string> $params Parameters to preserve.
 * @return moodle_url
 */
function local_mycoursesfilter_build_reset_url(array $params): moodle_url {
    $cleanparams = [];

    foreach ($params as $name => $value) {
        if ($value === '' || $value === 0) {
            continue;
        }

        $cleanparams[$name] = $value;
    }

    return new moodle_url('/local/mycoursesfilter/index.php', $cleanparams);
}

/**
 * Returns the sort selector labels.
 *
 * @return array<string, string>
 */
function local_mycoursesfilter_get_sort_labels(): array {
    return [
        'lastaccess' => get_string('sort_lastaccess', 'local_mycoursesfilter'),
        'alpha' => get_string('sort_alpha', 'local_mycoursesfilter'),
        'shortname' => get_string('sort_shortname', 'local_mycoursesfilter'),
        'lastenrolled' => get_string('sort_lastenrolled', 'local_mycoursesfilter'),
    ];
}

/**
 * Returns the view selector labels.
 *
 * @return array<string, string>
 */
function local_mycoursesfilter_get_view_labels(): array {
    return [
        'card' => get_string('view_card', 'local_mycoursesfilter'),
        'list' => get_string('view_list', 'local_mycoursesfilter'),
        'summary' => get_string('view_summary', 'local_mycoursesfilter'),
    ];
}

/**
 * Exports the filtered courses as template context data.
 *
 * @param stdClass[] $courses The filtered courses.
 * @param array<int, array<string, int|bool>> $meta Metadata keyed by course id.
 * @return array<string, array<int, array<string, bool|string>>>
 */
function local_mycoursesfilter_export_course_cards_context(array $courses, array $meta = []): array {
    global $DB;

    if (empty($courses)) {
        return ['courses' => []];
    }

    $categoryids = [];
    foreach ($courses as $course) {
        $categoryid = (int)($course->category ?? 0);
        if ($categoryid > 0) {
            $categoryids[$categoryid] = $categoryid;
        }
    }

    $categorynames = [];
    if (!empty($categoryids)) {
        [$insql, $params] = $DB->get_in_or_equal(array_values($categoryids), SQL_PARAMS_NAMED, 'cat');
        $categorynames = $DB->get_records_select_menu('course_categories', "id {$insql}", $params, '', 'id, name');
    }

    $cards = [];
    foreach ($courses as $course) {
        $courseid = (int)$course->id;
        $coursecontext = context_course::instance($courseid);
        $categoryid = (int)($course->category ?? 0);
        $categoryname = '';

        if (!empty($categorynames[$categoryid])) {
            $categorycontext = context_coursecat::instance($categoryid);
            $categoryname = format_string((string)$categorynames[$categoryid], true, ['context' => $categorycontext]);
        }

        $summaryhtml = '';
        if (!empty($course->summary)) {
            $summaryhtml = format_text(
                $course->summary,
                $course->summaryformat ?? FORMAT_HTML,
                ['context' => $coursecontext, 'overflowdiv' => false]
            );
        }

        $cards[] = [
            'viewurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            'courseimage' => local_mycoursesfilter_get_course_image_url($course),
            'fullname' => format_string((string)$course->fullname, true, ['context' => $coursecontext]),
            'shortname' => format_string((string)($course->shortname ?? ''), true, ['context' => $coursecontext]),
            'showshortname' => !empty($course->shortname),
            'isfavourite' => !empty($meta[$courseid]['isfavourite']),
            'favouritelabel' => get_string('filter_favourites', 'local_mycoursesfilter'),
            'coursecategory' => $categoryname,
            'showcoursecategory' => $categoryname !== '',
            'summaryhtml' => $summaryhtml,
            'hassummary' => $summaryhtml !== '',
            'visible' => !empty($course->visible),
        ];
    }

    return ['courses' => $cards];
}

/**
 * Returns a course card image URL.
 *
 * The function prefers a course overview image and falls back to Moodle's
 * generated placeholder image when no overview image exists.
 *
 * @param stdClass $course The course record.
 * @return string
 */
function local_mycoursesfilter_get_course_image_url(stdClass $course): string {
    global $OUTPUT;

    $context = context_course::instance((int)$course->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder, filepath, filename', false);

    foreach ($files as $file) {
        if (!$file->is_valid_image()) {
            continue;
        }

        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    return $OUTPUT->get_generated_image_for_id((int)$course->id);
}
