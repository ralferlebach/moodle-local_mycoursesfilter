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
 * PHPUnit tests for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mycoursesfilter;

/**
 * PHPUnit tests for local_mycoursesfilter helper functions.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @coversNothing
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Includes the plugin library before any tests are run.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/mycoursesfilter/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Tests matching the course name against fullname and shortname.
     *
     * @return void
     */
    public function test_match_name_checks_fullname_and_shortname(): void {
        $course = (object)[
            'fullname' => 'Applied Biology',
            'shortname' => 'BIO-101',
        ];

        $this->assertTrue(\local_mycoursesfilter_match_name($course, 'biology'));
        $this->assertTrue(\local_mycoursesfilter_match_name($course, 'bio-101'));
        $this->assertTrue(\local_mycoursesfilter_match_name($course, ''));
        $this->assertFalse(\local_mycoursesfilter_match_name($course, 'history'));
    }

    /**
     * Tests category matching against multiple category ids.
     *
     * @return void
     */
    public function test_match_categories_checks_membership(): void {
        $course = (object)[
            'category' => 12,
        ];

        $this->assertTrue(\local_mycoursesfilter_match_categories($course, []));
        $this->assertTrue(\local_mycoursesfilter_match_categories($course, [12, 99]));
        $this->assertFalse(\local_mycoursesfilter_match_categories($course, [99]));
    }

    /**
     * Tests category token parsing for ids and keywords.
     *
     * @return void
     */
    public function test_parse_category_tokens_accepts_safe_values_only(): void {
        $this->assertSame(
            ['2', '3', 'this', 'parent', 'children'],
            \local_mycoursesfilter_parse_category_tokens('2, 3, this, parent, children')
        );
        $this->assertSame([], \local_mycoursesfilter_parse_category_tokens('1 OR 1=1'));
        $this->assertSame([], \local_mycoursesfilter_parse_category_tokens('../../etc/passwd'));
    }

    /**
     * Tests the effective category scope resolution.
     *
     * @return void
     */
    public function test_resolve_category_scope_uses_switches_and_setting(): void {
        $this->resetAfterTest();

        set_config('categoryscope', 'only', 'local_mycoursesfilter');
        $this->assertSame('only', \local_mycoursesfilter_resolve_category_scope(false, false));
        $this->assertSame('recursive', \local_mycoursesfilter_resolve_category_scope(false, true));
        $this->assertSame('only', \local_mycoursesfilter_resolve_category_scope(true, true));
    }

    /**
     * Tests recursive category resolution for numeric ids.
     *
     * @return void
     */
    public function test_resolve_category_ids_handles_recursive_and_only_modes(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $child = $generator->create_category(['name' => 'Child', 'parent' => $root->id]);
        $grandchild = $generator->create_category(['name' => 'Grandchild', 'parent' => $child->id]);

        $this->assertSame([$root->id], \local_mycoursesfilter_resolve_category_ids((string)$root->id, 'only'));
        $this->assertSame(
            [$root->id, $child->id, $grandchild->id],
            \local_mycoursesfilter_resolve_category_ids((string)$root->id, 'recursive')
        );
    }

    /**
     * Tests contextual category keywords.
     *
     * @return void
     */
    public function test_resolve_category_ids_supports_this_parent_and_children(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $child = $generator->create_category(['name' => 'Child', 'parent' => $root->id]);
        $sibling = $generator->create_category(['name' => 'Sibling', 'parent' => $root->id]);
        $grandchild = $generator->create_category(['name' => 'Grandchild', 'parent' => $child->id]);
        $course = $generator->create_course(['category' => $child->id]);

        $this->assertSame([$child->id], \local_mycoursesfilter_resolve_category_ids('this', 'only', $course->id));
        $this->assertSame([$grandchild->id], \local_mycoursesfilter_resolve_category_ids('children', 'only', $course->id));
        $this->assertSame(
            [$root->id, $child->id, $sibling->id, $grandchild->id],
            \local_mycoursesfilter_resolve_category_ids('parent', 'recursive', $course->id)
        );
    }

    /**
     * Tests category parameter normalisation.
     *
     * @return void
     */
    public function test_normalise_category_ids_param_deduplicates_and_sorts(): void {
        $this->assertSame('2,3,4', \local_mycoursesfilter_normalise_category_ids_param([4, 2, 3, 2]));
        $this->assertSame('', \local_mycoursesfilter_normalise_category_ids_param([]));
    }

    /**
     * Tests page title resolution with settings and override flag.
     *
     * @return void
     */
    public function test_resolve_page_title_uses_setting_and_override_flag(): void {
        $this->resetAfterTest();

        set_config('defaulttitle', 'Configured title', 'local_mycoursesfilter');
        set_config('allowtitleoverride', 1, 'local_mycoursesfilter');
        $this->assertSame('Configured title', \local_mycoursesfilter_resolve_page_title(''));
        $this->assertSame('Runtime title', \local_mycoursesfilter_resolve_page_title('Runtime title'));

        set_config('allowtitleoverride', 0, 'local_mycoursesfilter');
        $this->assertSame('Configured title', \local_mycoursesfilter_resolve_page_title('Runtime title'));
    }

    /**
     * Tests return URL resolution for explicit local URLs and the special this value.
     *
     * @return void
     */
    public function test_resolve_return_url_accepts_local_and_this_only(): void {
        global $CFG;

        $this->resetAfterTest();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=17';
        $this->assertSame('/course/view.php?id=17', \local_mycoursesfilter_resolve_return_url('this'));
        $this->assertSame('/my/courses.php', \local_mycoursesfilter_resolve_return_url('/my/courses.php'));
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('https://evil.example/'));
    }

    /**
     * Tests source course resolution from an explicit course id.
     *
     * @return void
     */
    public function test_resolve_source_course_id_prefers_explicit_courseid(): void {
        $this->assertSame(42, \local_mycoursesfilter_resolve_source_course_id(42));
    }

    /**
     * Tests source course resolution from a local referrer.
     *
     * @return void
     */
    public function test_resolve_source_course_id_reads_local_referrer(): void {
        global $CFG;

        $this->resetAfterTest();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=77';
        $this->assertSame(77, \local_mycoursesfilter_resolve_source_course_id());
    }

    /**
     * Tests tag matching by tag name and id.
     *
     * @return void
     */
    public function test_match_tag_checks_name_and_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        \core_tag_tag::set_item_tags('core', 'course', $course->id, $context, ['Mandatory', 'Online']);

        $this->assertTrue(\local_mycoursesfilter_match_tag($course->id, 'Mandatory'));
        $this->assertFalse(\local_mycoursesfilter_match_tag($course->id, 'Optional'));

        $tags = \core_tag_tag::get_item_tags('core', 'course', $course->id);
        $firsttag = reset($tags);
        $this->assertNotFalse($firsttag);
        $this->assertTrue(\local_mycoursesfilter_match_tag($course->id, (string)$firsttag->id));
    }

    /**
     * Tests status matching when completion metadata is available.
     *
     * @return void
     */
    public function test_match_status_uses_completion_metadata_when_available(): void {
        $course = (object)['id' => 42];

        $notstartedmeta = [
            'completionenabled' => true,
            'timestarted' => 0,
            'timecompleted' => 0,
            'timeenrolled' => 123,
            'lastaccess' => 0,
            'iscompleted' => false,
        ];
        $inprogressmeta = [
            'completionenabled' => true,
            'timestarted' => 456,
            'timecompleted' => 0,
            'timeenrolled' => 123,
            'lastaccess' => 456,
            'iscompleted' => false,
        ];
        $completedmeta = [
            'completionenabled' => true,
            'timestarted' => 456,
            'timecompleted' => 789,
            'timeenrolled' => 123,
            'lastaccess' => 456,
            'iscompleted' => true,
        ];

        $this->assertTrue(\local_mycoursesfilter_match_status($course, $notstartedmeta, 'notstarted'));
        $this->assertTrue(\local_mycoursesfilter_match_status($course, $inprogressmeta, 'inprogress'));
        $this->assertTrue(\local_mycoursesfilter_match_status($course, $completedmeta, 'completed'));
        $this->assertFalse(\local_mycoursesfilter_match_status($course, $completedmeta, 'inprogress'));
    }

    /**
     * Tests status matching fallback when completion metadata is unavailable.
     *
     * @return void
     */
    public function test_match_status_falls_back_to_last_access(): void {
        $course = (object)['id' => 42];

        $untouchedmeta = [
            'completionenabled' => false,
            'lastaccess' => 0,
            'iscompleted' => false,
        ];
        $visitedmeta = [
            'completionenabled' => false,
            'lastaccess' => 1234,
            'iscompleted' => false,
        ];

        $this->assertTrue(\local_mycoursesfilter_match_status($course, $untouchedmeta, 'notstarted'));
        $this->assertTrue(\local_mycoursesfilter_match_status($course, $visitedmeta, 'inprogress'));
        $this->assertFalse(\local_mycoursesfilter_match_status($course, $visitedmeta, 'completed'));
    }
}
