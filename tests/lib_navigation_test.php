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
 * PHPUnit tests for navigation and request helpers in local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mycoursesfilter;

/**
 * PHPUnit tests for navigation and request helper functions.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @coversNothing
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_navigation_test extends \advanced_testcase {
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

        $this->assertSame([(int)$root->id], \local_mycoursesfilter_resolve_category_ids((string)$root->id, 'only'));
        $this->assertSame(
            [(int)$root->id],
            \local_mycoursesfilter_resolve_category_ids((string)$root->id . ',999999999', 'only')
        );
        $this->assertSame(
            [(int)$root->id, (int)$child->id, (int)$grandchild->id],
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

        $this->assertSame([(int)$child->id], \local_mycoursesfilter_resolve_category_ids('this', 'only', $course->id));
        $this->assertSame([(int)$grandchild->id], \local_mycoursesfilter_resolve_category_ids('children', 'only', $course->id));
        $this->assertSame(
            [(int)$root->id, (int)$child->id, (int)$sibling->id, (int)$grandchild->id],
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

        $sitepath = (string)(parse_url($CFG->wwwroot, PHP_URL_PATH) ?? '');
        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=17';

        $this->assertSame($sitepath . '/course/view.php?id=17', \local_mycoursesfilter_resolve_return_url('this'));
        $this->assertSame($sitepath . '/my/courses.php', \local_mycoursesfilter_resolve_return_url('/my/courses.php'));
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('https://evil.example/'));
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('//evil.example/'));

        $_SERVER['HTTP_REFERER'] = 'https://evil.example/course/view.php?id=17';
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('this'));
    }

    /**
     * Tests source course resolution from an explicit course id and a local referrer.
     *
     * @return void
     */
    public function test_resolve_source_course_id_prefers_explicit_and_reads_referrer(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $explicitcourse = $generator->create_course();
        $referercourse = $generator->create_course();

        $this->assertSame(
            (int)$explicitcourse->id,
            \local_mycoursesfilter_resolve_source_course_id((int)$explicitcourse->id)
        );
        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . (int)$referercourse->id;
        $this->assertSame((int)$referercourse->id, \local_mycoursesfilter_resolve_source_course_id());
    }

    /**
     * Tests toolbar persistence mode and compatible core mappings.
     *
     * @return void
     */
    public function test_toolbar_persistence_mode_and_core_mappings(): void {
        $this->resetAfterTest();

        $this->assertSame('none', \local_mycoursesfilter_get_toolbar_persistence_mode());
        set_config('persisttoolbar', 'core', 'local_mycoursesfilter');
        $this->assertSame('core', \local_mycoursesfilter_get_toolbar_persistence_mode());

        $this->assertSame('title', \local_mycoursesfilter_map_toolbar_value_to_core('sort', 'coursename'));
        $this->assertSame('coursename', \local_mycoursesfilter_map_toolbar_value_from_core('sort', 'title', 'lastaccess'));
        $this->assertSame('', \local_mycoursesfilter_map_toolbar_value_to_core('filter', 'completed'));
        $this->assertSame('all', \local_mycoursesfilter_map_toolbar_value_from_core('filter', 'future', 'all'));
    }

    /**
     * Tests the combined custom field parameter helpers and default sort order logic.
     *
     * @return void
     */
    public function test_customfield_helpers_and_default_sortorder(): void {
        $this->assertSame(
            ['shortname' => 'department', 'value' => 'bio'],
            \local_mycoursesfilter_parse_customfield_param('department:bio')
        );
        $this->assertSame(
            ['shortname' => 'department', 'value' => ''],
            \local_mycoursesfilter_parse_customfield_param('department')
        );
        $this->assertSame('department:bio', \local_mycoursesfilter_build_customfield_param('department', 'bio'));
        $this->assertSame('department', \local_mycoursesfilter_build_customfield_param('department', ''));
        $this->assertSame('asc', \local_mycoursesfilter_get_default_sortorder('coursename'));
        $this->assertSame('desc', \local_mycoursesfilter_get_default_sortorder('lastaccess'));
    }
}
