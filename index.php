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
 * Main entry point for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

require_login();

// Role check (optional – can be enabled/disabled).
require_once(__DIR__ . '/lib.php');
local_mycoursesfilter_require_any_course_role(['student']);

// Get filter parameters.
$q      = optional_param('q', '', PARAM_TEXT);           // Course name substring.
$tag    = optional_param('tag', '', PARAM_TEXT);         // Tag name (or ID).
$catid  = optional_param('catid', 0, PARAM_INT);         // Category ID (course area).
$field  = optional_param('field', '', PARAM_ALPHANUMEXT); // Custom field shortname.
$fvalue = optional_param('value', '', PARAM_TEXT);       // Custom field value.

$PAGE->set_url(new moodle_url('/local/mycoursesfilter/index.php', [
    'q' => $q,
    'tag' => $tag,
    'catid' => $catid,
    'field' => $field,
    'value' => $fvalue,
]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pagetitle', 'local_mycoursesfilter'));
$PAGE->set_heading(get_string('pagetitle', 'local_mycoursesfilter'));

// Load the user's courses.
$courses = enrol_get_my_courses(
    null,
    'visible DESC, sortorder ASC',
    0
);

// Apply filters.
$filtered = [];
foreach ($courses as $course) {
    if (!local_mycoursesfilter_match_name($course, $q)) {
        continue;
    }
    if ($catid && !local_mycoursesfilter_match_category($course, $catid)) {
        continue;
    }
    if ($tag && !local_mycoursesfilter_match_tag($course->id, $tag)) {
        continue;
    }
    if ($field !== '' && !local_mycoursesfilter_match_customfield($course->id, $field, $fvalue)) {
        continue;
    }
    $filtered[] = $course;
}

// Output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('filteredcourses', 'local_mycoursesfilter', count($filtered)));

if (empty($filtered)) {
    echo $OUTPUT->notification(get_string('nocoursefound', 'local_mycoursesfilter'), 'info');
} else {
    echo html_writer::start_tag('ul');
    foreach ($filtered as $course) {
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        echo html_writer::tag('li', html_writer::link($url, format_string($course->fullname)));
    }
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();

/**
 * Check if the course name matches the given search string.
 *
 * @param stdClass $course The course object.
 * @param string $q The search string.
 * @return bool True if matches, false otherwise.
 */
function local_mycoursesfilter_match_name(stdClass $course, string $q): bool {
    if ($q === '') {
        return true;
    }
    $haystack = mb_strtolower($course->fullname ?? '');
    $needle = mb_strtolower($q);
    return (mb_strpos($haystack, $needle) !== false);
}

/**
 * Check if the course belongs to the given category or one of its subcategories.
 *
 * @param stdClass $course The course object.
 * @param int $targetcatid The target category ID.
 * @return bool True if matches, false otherwise.
 */
function local_mycoursesfilter_match_category(stdClass $course, int $targetcatid): bool {
    if (!$targetcatid) {
        return true;
    }

    // Check if the course is directly in the category or in a subcategory.
    $cat = \core_course_category::get($course->category, IGNORE_MISSING);
    if (!$cat) {
        return false;
    }

    // Path format is e.g. "/1/5/23".
    $path = $cat->path ?? '';
    return (strpos($path . '/', '/' . $targetcatid . '/') !== false);
}

/**
 * Check if the course has the specified tag.
 *
 * @param int $courseid The course ID.
 * @param string $tag The tag name or ID.
 * @return bool True if the course has the tag, false otherwise.
 */
function local_mycoursesfilter_match_tag(int $courseid, string $tag): bool {
    global $DB;

    // Support either tag ID ("123") or tag name ("Mathematics").
    if (ctype_digit($tag)) {
        $tagid = (int) $tag;
    } else {
        $tagid = (int) $DB->get_field('tag', 'id', ['name' => $tag], IGNORE_MISSING);
        if (!$tagid) {
            return false;
        }
    }

    // Check tag_instance: component='core', itemtype='course', itemid=<courseid>.
    return $DB->record_exists('tag_instance', [
        'component' => 'core',
        'itemtype'  => 'course',
        'itemid'    => $courseid,
        'tagid'     => $tagid,
    ]);
}

/**
 * Check if a course custom field matches the given value.
 *
 * @param int $courseid The course ID.
 * @param string $shortname The custom field shortname.
 * @param string $value The expected value (empty string means "is set").
 * @return bool True if matches, false otherwise.
 */
function local_mycoursesfilter_match_customfield(int $courseid, string $shortname, string $value): bool {
    // Custom field handler for courses.
    $handler = \core_course\customfield\course_handler::get_handler();
    $data = $handler->get_instance_data($courseid, true);

    // $data is an array of DataController objects.
    // Each has ->get_field()->get('shortname') and ->export_value().
    foreach ($data as $d) {
        $field = $d->get_field();
        if ($field && $field->get('shortname') === $shortname) {
            $actual = trim((string) $d->export_value());
            if ($value === '') {
                // Only check if the field is set.
                return ($actual !== '');
            }
            return (mb_strtolower($actual) === mb_strtolower(trim($value)));
        }
    }
    return false;
}
