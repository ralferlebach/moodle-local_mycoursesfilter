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
     * @covers ::local_mycoursesfilter_match_name
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
     * @covers ::local_mycoursesfilter_match_category
     * @return void
     */
    public function test_match_category_checks_exact_category(): void {
        $course = (object)[
            'category' => 12,
        ];

        $this->assertTrue(\local_mycoursesfilter_match_category($course, 0));
        $this->assertTrue(\local_mycoursesfilter_match_category($course, 12));
        $this->assertFalse(\local_mycoursesfilter_match_category($course, 99));
    }

    /**
     * Tests tag matching by tag name and id.
     *
     * @covers ::local_mycoursesfilter_match_tag
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
     * @covers ::local_mycoursesfilter_match_status
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
     * @covers ::local_mycoursesfilter_match_status
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
