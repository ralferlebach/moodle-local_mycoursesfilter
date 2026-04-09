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
 * PHPUnit tests for catid tokens (this/parent/children) and source course resolution.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mycoursesfilter;

/**
 * PHPUnit tests for catid tokens and referer-based source course resolution.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @coversNothing
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_tokens_test extends \advanced_testcase {
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
     * Preserves the HTTP referer superglobal between tests.
     *
     * @var string|null
     */
    private $savedreferer = null;

    /**
     * Saves the current HTTP referer before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->savedreferer = $_SERVER['HTTP_REFERER'] ?? null;
    }

    /**
     * Restores the HTTP referer after each test.
     *
     * @return void
     */
    protected function tearDown(): void {
        if ($this->savedreferer === null) {
            unset($_SERVER['HTTP_REFERER']);
        } else {
            $_SERVER['HTTP_REFERER'] = $this->savedreferer;
        }
        parent::tearDown();
    }

    /**
     * Creates a small category tree and courses for reuse in tests.
     *
     * Structure:
     *   - parentcat
     *       - thiscat  (contains $thiscourse)
     *           - childcat_a  (contains $childcourse)
     *           - childcat_b
     *
     * @return object[] Keyed structure with categories and courses.
     */
    private function build_category_tree(): array {
        $gen = $this->getDataGenerator();

        $parentcat = $gen->create_category(['name' => 'ParentCat']);
        $thiscat = $gen->create_category(['name' => 'ThisCat', 'parent' => $parentcat->id]);
        $childcata = $gen->create_category(['name' => 'ChildA', 'parent' => $thiscat->id]);
        $childcatb = $gen->create_category(['name' => 'ChildB', 'parent' => $thiscat->id]);

        $thiscourse = $gen->create_course(['category' => $thiscat->id, 'shortname' => 'THISCRS']);
        $childcourse = $gen->create_course(['category' => $childcata->id, 'shortname' => 'CHILDCRS']);

        return [
            'parentcat' => $parentcat,
            'thiscat' => $thiscat,
            'childcata' => $childcata,
            'childcatb' => $childcatb,
            'thiscourse' => $thiscourse,
            'childcourse' => $childcourse,
        ];
    }

    /**
     * The "this" token resolves to the source category id.
     *
     * @return void
     */
    public function test_token_this_returns_source_category(): void {
        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $result = \local_mycoursesfilter_resolve_category_token('this', (int)$tree['thiscat']->id);
        $this->assertSame([(int)$tree['thiscat']->id], $result);
    }

    /**
     * The "parent" token resolves to the parent category id.
     *
     * @return void
     */
    public function test_token_parent_returns_parent_category(): void {
        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $result = \local_mycoursesfilter_resolve_category_token('parent', (int)$tree['thiscat']->id);
        $this->assertSame([(int)$tree['parentcat']->id], $result);
    }

    /**
     * The "parent" token yields nothing for a top-level category.
     *
     * @return void
     */
    public function test_token_parent_returns_empty_for_toplevel(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $top = $gen->create_category(['name' => 'TopLevel']);

        $result = \local_mycoursesfilter_resolve_category_token('parent', (int)$top->id);
        $this->assertSame([], $result);
    }

    /**
     * The "children" token resolves to immediate child category ids.
     *
     * @return void
     */
    public function test_token_children_returns_immediate_children(): void {
        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $result = \local_mycoursesfilter_resolve_category_token('children', (int)$tree['thiscat']->id);
        sort($result);
        $expected = [(int)$tree['childcata']->id, (int)$tree['childcatb']->id];
        sort($expected);
        $this->assertSame($expected, $result);
    }

    /**
     * Any token without a source category id resolves to an empty list.
     *
     * @return void
     */
    public function test_tokens_require_source_category(): void {
        $this->resetAfterTest();
        $this->assertSame([], \local_mycoursesfilter_resolve_category_token('this', 0));
        $this->assertSame([], \local_mycoursesfilter_resolve_category_token('parent', 0));
        $this->assertSame([], \local_mycoursesfilter_resolve_category_token('children', 0));
    }

    /**
     * An explicit courseid parameter is returned unchanged.
     *
     * @return void
     */
    public function test_resolve_source_course_id_uses_explicit_param(): void {
        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $result = \local_mycoursesfilter_resolve_source_course_id((int)$tree['thiscourse']->id);
        $this->assertSame((int)$tree['thiscourse']->id, $result);
    }

    /**
     * A same-site HTTP referer pointing to /course/view.php resolves the source course id.
     *
     * @return void
     */
    public function test_resolve_source_course_id_uses_same_site_referer(): void {
        global $CFG;

        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . (int)$tree['thiscourse']->id;

        $this->assertSame((int)$tree['thiscourse']->id, \local_mycoursesfilter_resolve_source_course_id(0));
    }

    /**
     * An off-site referer must not influence the source course id.
     *
     * @return void
     */
    public function test_resolve_source_course_id_ignores_offsite_referer(): void {
        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $_SERVER['HTTP_REFERER'] = 'https://evil.example.org/course/view.php?id=' . (int)$tree['thiscourse']->id;

        $this->assertSame(0, \local_mycoursesfilter_resolve_source_course_id(0));
    }

    /**
     * A same-site referer that does not point at /course/view.php is ignored.
     *
     * @return void
     */
    public function test_resolve_source_course_id_ignores_other_paths(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->build_category_tree();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/my/index.php';

        $this->assertSame(0, \local_mycoursesfilter_resolve_source_course_id(0));
    }

    /**
     * End-to-end: the "this" token resolves against a source course derived from the referer.
     *
     * @return void
     */
    public function test_resolve_category_ids_with_this_token_via_referer(): void {
        global $CFG;

        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . (int)$tree['thiscourse']->id;
        $sourcecourseid = \local_mycoursesfilter_resolve_source_course_id(0);
        $this->assertSame((int)$tree['thiscourse']->id, $sourcecourseid);

        $scope = \local_mycoursesfilter_resolve_category_scope(true, false);
        $ids = \local_mycoursesfilter_resolve_category_ids('this', $scope, $sourcecourseid);

        $this->assertContains((int)$tree['thiscat']->id, $ids);
    }

    /**
     * End-to-end: the "children" token resolves against a source course derived from the referer.
     *
     * @return void
     */
    public function test_resolve_category_ids_with_children_token_via_referer(): void {
        global $CFG;

        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . (int)$tree['thiscourse']->id;
        $sourcecourseid = \local_mycoursesfilter_resolve_source_course_id(0);

        $scope = \local_mycoursesfilter_resolve_category_scope(true, false);
        $ids = \local_mycoursesfilter_resolve_category_ids('children', $scope, $sourcecourseid);

        $this->assertContains((int)$tree['childcata']->id, $ids);
        $this->assertContains((int)$tree['childcatb']->id, $ids);
        $this->assertNotContains((int)$tree['thiscat']->id, $ids);
    }

    /**
     * End-to-end: the "parent" token resolves against a source course derived from the referer.
     *
     * @return void
     */
    public function test_resolve_category_ids_with_parent_token_via_referer(): void {
        global $CFG;

        $this->resetAfterTest();
        $tree = $this->build_category_tree();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . (int)$tree['thiscourse']->id;
        $sourcecourseid = \local_mycoursesfilter_resolve_source_course_id(0);

        $scope = \local_mycoursesfilter_resolve_category_scope(true, false);
        $ids = \local_mycoursesfilter_resolve_category_ids('parent', $scope, $sourcecourseid);

        $this->assertContains((int)$tree['parentcat']->id, $ids);
    }
}
