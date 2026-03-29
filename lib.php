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
    }

    foreach ($courseids as $courseid) {
        $course = get_course($courseid);
        $completioninfo = new completion_info($course);
        $meta[$courseid]['completionenabled'] = $completioninfo->is_enabled();

        if (!$meta[$courseid]['completionenabled']) {
            continue;
        }

        $meta[$courseid]['iscompleted'] = $completioninfo->is_course_complete($USER->id);
    }

    return $meta;
}

/**
 * Checks whether the course matches the provided name query.
 *
 * @param stdClass $course The course record.
 * @param string $query The search query.
 * @return bool
 */
function local_mycoursesfilter_match_name(stdClass $course, string $query): bool {
    if ($query === '') {
        return true;
    }

    $query = core_text::strtolower(trim($query));
    $fullname = core_text::strtolower((string)($course->fullname ?? ''));
    $shortname = core_text::strtolower((string)($course->shortname ?? ''));

    return core_text::strpos($fullname, $query) !== false || core_text::strpos($shortname, $query) !== false;
}

/**
 * Checks whether the course matches the selected category.
 *
 * @param stdClass $course The course record.
 * @param int $categoryid The expected category id or 0 for any category.
 * @return bool
 */
function local_mycoursesfilter_match_category(stdClass $course, int $categoryid): bool {
    if ($categoryid === 0) {
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
 * Checks whether the course matches the selected progress status.
 *
 * @param stdClass $course The course record.
 * @param array|null $meta The metadata for the course.
 * @param string $status The selected status.
 * @return bool
 */
function local_mycoursesfilter_match_status(stdClass $course, ?array $meta, string $status): bool {
    if ($status === 'any') {
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
