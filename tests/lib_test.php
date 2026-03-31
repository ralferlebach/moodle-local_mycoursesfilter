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
     * Tests category matching.
     *
     * @return void
     */
    public function test_match_category_checks_selected_categories(): void {
        $course = (object)[
            'category' => 12,
        ];

        $this->assertTrue(\local_mycoursesfilter_match_category($course, [], false));
        $this->assertTrue(\local_mycoursesfilter_match_category($course, [12, 14], true));
        $this->assertFalse(\local_mycoursesfilter_match_category($course, [99], true));
    }

    /**
     * Tests recursive category id resolution.
     *
     * @return void
     */
    public function test_resolve_category_ids_expands_descendants_when_recursive(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $parent = $this->getDataGenerator()->create_category(['name' => 'Parent']);
        $child = $this->getDataGenerator()->create_category(['name' => 'Child', 'parent' => $parent->id]);
        $grandchild = $this->getDataGenerator()->create_category(['name' => 'Grandchild', 'parent' => $child->id]);

        $resolved = \local_mycoursesfilter_resolve_category_ids((string)$parent->id, [], 'recursive');

        $this->assertEqualsCanonicalizing([$parent->id, $child->id, $grandchild->id], $resolved);
        $this->assertEqualsCanonicalizing(
            [$parent->id],
            \local_mycoursesfilter_resolve_category_ids((string)$parent->id, [], 'only')
        );
    }

    /**
     * Tests keyword-based category resolution.
     *
     * @return void
     */
    public function test_resolve_category_ids_supports_this_parent_and_children_keywords(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $root = $this->getDataGenerator()->create_category(['name' => 'Root']);
        $current = $this->getDataGenerator()->create_category(['name' => 'Current', 'parent' => $root->id]);
        $childone = $this->getDataGenerator()->create_category(['name' => 'Child one', 'parent' => $current->id]);
        $childtwo = $this->getDataGenerator()->create_category(['name' => 'Child two', 'parent' => $current->id]);

        $reference = [
            'categoryid' => $current->id,
            'parentid' => $root->id,
        ];

        $resolved = \local_mycoursesfilter_resolve_category_ids('this,parent,children', $reference, 'only');

        $this->assertEqualsCanonicalizing([$current->id, $root->id, $childone->id, $childtwo->id], $resolved);
    }

    /**
     * Tests the special returnurl=this handling.
     *
     * @return void
     */
    public function test_resolve_return_url_maps_this_to_referrer(): void {
        global $CFG;

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=77';

        $this->assertSame('/course/view.php?id=77', \local_mycoursesfilter_resolve_return_url('this'));
        $this->assertSame('/my/courses.php', \local_mycoursesfilter_resolve_return_url('/my/courses.php'));
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
