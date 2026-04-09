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
 * PHPUnit tests for extended source course resolution (section, mod, access check).
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mycoursesfilter;

/**
 * PHPUnit tests for extended source course resolution.
 *
 * Covers the dispatcher {@see local_mycoursesfilter_resolve_course_id_from_referer_path()}
 * for section pages, activity module pages, generic `courseid` parameters, and the
 * access-control guard in {@see local_mycoursesfilter_user_can_access_source_course()}.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @coversNothing
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source_context_resolution_test extends \advanced_testcase {
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
     * Saves HTTP referer and elevates to admin before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->savedreferer = $_SERVER['HTTP_REFERER'] ?? null;
    }

    /**
     * Restores HTTP referer after each test.
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

    // ---------------------------------------------------------------------
    // Dispatcher (pure path + params, no superglobal or DB manipulation).
    // ---------------------------------------------------------------------

    /**
     * The /course/view.php path maps id to courseid.
     *
     * @return void
     */
    public function test_dispatcher_course_view(): void {
        $this->resetAfterTest();
        $this->assertSame(
            42,
            \local_mycoursesfilter_resolve_course_id_from_referer_path('/course/view.php', ['id' => '42'])
        );
    }

    /**
     * A generic courseid query parameter wins over path-specific handling.
     *
     * @return void
     */
    public function test_dispatcher_generic_courseid_param(): void {
        $this->resetAfterTest();
        $this->assertSame(
            7,
            \local_mycoursesfilter_resolve_course_id_from_referer_path('/blog/index.php', ['courseid' => '7'])
        );
        $this->assertSame(
            7,
            \local_mycoursesfilter_resolve_course_id_from_referer_path(
                '/badges/view.php',
                ['courseid' => '7', 'type' => '2']
            )
        );
    }

    /**
     * /user/profile.php maps the "course" query parameter.
     *
     * @return void
     */
    public function test_dispatcher_user_profile_course_param(): void {
        $this->resetAfterTest();
        $this->assertSame(
            11,
            \local_mycoursesfilter_resolve_course_id_from_referer_path(
                '/user/profile.php',
                ['id' => '3', 'course' => '11']
            )
        );
    }

    /**
     * Non-digit identifiers are rejected by the dispatcher.
     *
     * @return void
     */
    public function test_dispatcher_rejects_non_numeric(): void {
        $this->resetAfterTest();
        $this->assertSame(
            0,
            \local_mycoursesfilter_resolve_course_id_from_referer_path('/course/view.php', ['id' => 'abc'])
        );
        $this->assertSame(
            0,
            \local_mycoursesfilter_resolve_course_id_from_referer_path('/course/view.php', [])
        );
    }

    // ---------------------------------------------------------------------
    // section.php (DB round-trip).
    // ---------------------------------------------------------------------

    /**
     * /course/section.php?id=SECTIONID resolves the owning course.
     *
     * @return void
     */
    public function test_section_page_resolves_course(): void {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $sectionid = (int)$DB->get_field(
            'course_sections',
            'id',
            ['course' => $course->id, 'section' => 2]
        );
        $this->assertGreaterThan(0, $sectionid);

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/section.php?id=' . $sectionid;
        $this->assertSame((int)$course->id, \local_mycoursesfilter_resolve_source_course_id());
    }

    /**
     * A non-existent section id yields no source course.
     *
     * @return void
     */
    public function test_section_page_unknown_id_returns_zero(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/section.php?id=999999999';
        $this->assertSame(0, \local_mycoursesfilter_resolve_source_course_id());
    }

    // ---------------------------------------------------------------------
    // mod/<modname>/view.php — cmid and instance-id fallback.
    // ---------------------------------------------------------------------

    /**
     * /mod/page/view.php?id=CMID resolves the course via course_modules.
     *
     * @return void
     */
    public function test_mod_view_id_as_cmid(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/mod/page/view.php?id=' . (int)$module->cmid;
        $this->assertSame((int)$course->id, \local_mycoursesfilter_resolve_source_course_id());
    }

    /**
     * An explicit cmid parameter on a mod page is honoured.
     *
     * @return void
     */
    public function test_mod_view_explicit_cmid_param(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/mod/page/someaction.php?cmid=' . (int)$module->cmid;
        $this->assertSame((int)$course->id, \local_mycoursesfilter_resolve_source_course_id());
    }

    /**
     * The instance-id helper resolves a mod table row to its course id.
     *
     * Tested via the helper directly because dispatcher input that happens to
     * match a valid cmid would short-circuit before the instance fallback runs.
     *
     * @return void
     */
    public function test_mod_instance_helper_resolves_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->assertSame(
            (int)$course->id,
            \local_mycoursesfilter_course_id_from_mod_instance('page', (int)$module->id)
        );
        $this->assertSame(
            0,
            \local_mycoursesfilter_course_id_from_mod_instance('page', 999999999)
        );
    }

    /**
     * End-to-end: a referer with a bogus id on a mod path resolves to 0.
     *
     * @return void
     */
    public function test_mod_view_unknown_id_returns_zero(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/mod/page/view.php?id=999999999';
        $this->assertSame(0, \local_mycoursesfilter_resolve_source_course_id());
    }

    /**
     * The mod path dispatcher rejects unknown module names.
     *
     * @return void
     */
    public function test_mod_view_unknown_module_returns_zero(): void {
        $this->resetAfterTest();
        $this->assertSame(
            0,
            \local_mycoursesfilter_course_id_from_mod_instance('not_a_real_module', 1)
        );
        $this->assertSame(
            0,
            \local_mycoursesfilter_course_id_from_mod_instance('../evil', 1)
        );
    }

    // ---------------------------------------------------------------------
    // Access control guard.
    // ---------------------------------------------------------------------

    /**
     * A user without access to the referenced course receives no source course id.
     *
     * @return void
     */
    public function test_access_check_blocks_inaccessible_course(): void {
        global $CFG;

        $this->resetAfterTest();

        // A hidden course is not visible to a regular non-enrolled user.
        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . (int)$course->id;
        $this->assertSame(0, \local_mycoursesfilter_resolve_source_course_id());

        // Explicit parameter path is guarded as well.
        $this->assertSame(0, \local_mycoursesfilter_resolve_source_course_id((int)$course->id));
    }

    /**
     * An enrolled user on a hidden-but-enrolled course is allowed through.
     *
     * @return void
     */
    public function test_access_check_allows_enrolled_user(): void {
        global $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . (int)$course->id;
        $this->assertSame(
            (int)$course->id,
            \local_mycoursesfilter_resolve_source_course_id()
        );
    }

    /**
     * A non-existent explicit course id is rejected gracefully.
     *
     * @return void
     */
    public function test_access_check_nonexistent_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(0, \local_mycoursesfilter_resolve_source_course_id(999999999));
    }

    // ---------------------------------------------------------------------
    // Helper functions.
    // ---------------------------------------------------------------------

    /**
     * The cmid helper returns 0 for unknown/invalid ids and the course id otherwise.
     *
     * @return void
     */
    public function test_course_id_from_cmid_helper(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->assertSame(
            (int)$course->id,
            \local_mycoursesfilter_course_id_from_cmid((int)$module->cmid)
        );
        $this->assertSame(0, \local_mycoursesfilter_course_id_from_cmid(0));
        $this->assertSame(0, \local_mycoursesfilter_course_id_from_cmid(-1));
        $this->assertSame(0, \local_mycoursesfilter_course_id_from_cmid(999999999));
    }
}
