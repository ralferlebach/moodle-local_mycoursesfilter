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
 * PHPUnit tests for the tolerant source-course resolution chain.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mycoursesfilter;

/**
 * Covers courseid=last, id/cmid conflicts, returnurl fallback, session fallback,
 * and block-instance context resolution.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @coversNothing
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tolerant_context_resolution_test extends \advanced_testcase {
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
     * Saved HTTP referer.
     *
     * @var string|null
     */
    private $savedreferer = null;

    /**
     * Saves HTTP referer before each test.
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
    // courseid=last
    // ---------------------------------------------------------------------

    /**
     * The forcelast flag returns the user's most recently accessed enrolment.
     *
     * @return void
     */
    public function test_forcelast_returns_last_accessed_course(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $oldcourse = $generator->create_course();
        $recentcourse = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $oldcourse->id, 'student');
        $generator->enrol_user($user->id, $recentcourse->id, 'student');
        $this->setUser($user);

        // Seed user_lastaccess so enrol_get_my_courses' timeaccess sort is deterministic.
        $now = time();
        $DB->insert_record('user_lastaccess', (object)[
            'userid' => $user->id,
            'courseid' => $oldcourse->id,
            'timeaccess' => $now - 3600,
        ]);
        $DB->insert_record('user_lastaccess', (object)[
            'userid' => $user->id,
            'courseid' => $recentcourse->id,
            'timeaccess' => $now,
        ]);

        $resolved = \local_mycoursesfilter_resolve_source_course_id(0, true);
        $this->assertSame((int)$recentcourse->id, $resolved);
    }

    /**
     * The forcelast flag ignores referer, returnurl, and explicit courseid.
     *
     * @return void
     */
    public function test_forcelast_ignores_other_sources(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $enrolled = $generator->create_course();
        $other = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $enrolled->id, 'student');
        $this->setUser($user);

        $DB->insert_record('user_lastaccess', (object)[
            'userid' => $user->id,
            'courseid' => $enrolled->id,
            'timeaccess' => time(),
        ]);

        // Referer, returnurl, and an explicit courseid all point at $other, yet forcelast wins.
        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . (int)$other->id;
        $resolved = \local_mycoursesfilter_resolve_source_course_id(
            (int)$other->id,
            true,
            '/course/view.php?id=' . (int)$other->id
        );
        $this->assertSame((int)$enrolled->id, $resolved);
    }

    /**
     * The forcelast flag returns 0 for a user without any accessible enrolments.
     *
     * @return void
     */
    public function test_forcelast_returns_zero_without_enrolments(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertSame(0, \local_mycoursesfilter_resolve_source_course_id(0, true));
    }

    /**
     * Guest users never get a last-accessed course back.
     *
     * @return void
     */
    public function test_forcelast_ignores_guest_user(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->assertSame(
            0,
            \local_mycoursesfilter_resolve_last_accessed_course_id_for_current_user()
        );
    }

    // ---------------------------------------------------------------------
    // id/cmid conflict -> last-accessed fallback
    // ---------------------------------------------------------------------

    /**
     * When a mod id parameter matches a cmid in course A and an instance id in course B,
     * the dispatcher flags a conflict and returns 0.
     *
     * @return void
     */
    public function test_dispatcher_flags_id_vs_cmid_conflict(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $coursea = $generator->create_course();
        $courseb = $generator->create_course();

        // Create a page instance in course A. course_modules.id and page.id are both
        // the auto-assigned values. We then rewrite page.course to course B so that
        // looking the same id up as a cmid yields course A while looking it up as a
        // page instance yields course B.
        $mod = $generator->create_module('page', ['course' => $coursea->id]);
        $instanceid = (int)$mod->id;
        $cmid = (int)$mod->cmid;

        // Make the numeric ids collide: use the cmid as our test id and make sure the
        // mdl_page row with that id (if different from $instanceid) belongs to course B.
        // In a fresh test DB the first cmid equals the first instance id, so this is
        // usually a single row — we simply rewrite its course to B.
        $DB->set_field('page', 'course', $courseb->id, ['id' => $instanceid]);

        // Sanity: cmid lookup still says course A (course_modules.course is untouched),
        // instance lookup now says course B.
        $this->assertSame(
            (int)$coursea->id,
            \local_mycoursesfilter_course_id_from_cmid($cmid)
        );
        $this->assertSame(
            (int)$courseb->id,
            \local_mycoursesfilter_course_id_from_mod_instance('page', $instanceid)
        );
        // We need the dispatcher test id to be valid both as a cmid AND as an instance id.
        // When create_module assigns the same numeric value to both (typical in a fresh
        // test DB), $cmid === $instanceid and we can use it directly.
        if ($cmid !== $instanceid) {
            $this->markTestSkipped('Test DB assigned different cmid and instance ids; cannot force collision.');
        }

        $conflict = false;
        $courseid = \local_mycoursesfilter_resolve_course_id_from_referer_path(
            '/mod/page/view.php',
            ['id' => (string)$cmid],
            $conflict
        );
        $this->assertTrue($conflict);
        $this->assertSame(0, $courseid);
    }

    /**
     * An id that matches the same course as cmid and as instance is NOT a conflict.
     *
     * @return void
     */
    public function test_dispatcher_no_conflict_when_same_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $mod = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $conflict = null;
        $courseid = \local_mycoursesfilter_resolve_course_id_from_referer_path(
            '/mod/page/view.php',
            ['id' => (string)$mod->cmid],
            $conflict
        );
        $this->assertFalse((bool)$conflict);
        $this->assertSame((int)$course->id, $courseid);
    }

    /**
     * End-to-end: a mod id/cmid conflict on the referer falls back to last-accessed.
     *
     * @return void
     */
    public function test_mod_conflict_falls_back_to_last_accessed(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $coursea = $generator->create_course();
        $courseb = $generator->create_course();
        $enrolled = $generator->create_course(['fullname' => 'Enrolled Course']);

        // Create a page in course A and rewrite mdl_page.course to course B, creating
        // the same kind of collision used in the dispatcher test above.
        $mod = $generator->create_module('page', ['course' => $coursea->id]);
        $instanceid = (int)$mod->id;
        $cmid = (int)$mod->cmid;
        $DB->set_field('page', 'course', $courseb->id, ['id' => $instanceid]);

        if ($cmid !== $instanceid) {
            $this->markTestSkipped('Test DB assigned different cmid and instance ids; cannot force collision.');
        }

        // Enrol the user in a third course and seed user_lastaccess so the
        // last-accessed fallback has a deterministic target.
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $enrolled->id, 'student');
        $this->setUser($user);
        $DB->insert_record('user_lastaccess', (object)[
            'userid' => $user->id,
            'courseid' => $enrolled->id,
            'timeaccess' => time(),
        ]);

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/mod/page/view.php?id=' . $cmid;
        $resolved = \local_mycoursesfilter_resolve_source_course_id();

        $this->assertSame((int)$enrolled->id, $resolved);
    }

    // ---------------------------------------------------------------------
    // returnurl fallback
    // ---------------------------------------------------------------------

    /**
     * When the HTTP referer is empty, the returnurl is used to derive the source course.
     *
     * @return void
     */
    public function test_returnurl_fallback_resolves_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        unset($_SERVER['HTTP_REFERER']);
        $resolved = \local_mycoursesfilter_resolve_source_course_id(
            0,
            false,
            '/course/view.php?id=' . (int)$course->id
        );
        $this->assertSame((int)$course->id, $resolved);
    }

    /**
     * A returnurl that the user cannot access is rejected.
     *
     * @return void
     */
    public function test_returnurl_fallback_respects_access_check(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        unset($_SERVER['HTTP_REFERER']);
        $resolved = \local_mycoursesfilter_resolve_source_course_id(
            0,
            false,
            '/course/view.php?id=' . (int)$course->id
        );
        $this->assertSame(0, $resolved);
    }

    // ---------------------------------------------------------------------
    // Session fallback
    // ---------------------------------------------------------------------

    /**
     * $SESSION->lastcourseaccessed is used when no referer or returnurl is available.
     *
     * @return void
     */
    public function test_session_lastcourseaccessed_is_used(): void {
        global $SESSION;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        unset($_SERVER['HTTP_REFERER']);
        $SESSION->lastcourseaccessed = (int)$course->id;

        $resolved = \local_mycoursesfilter_resolve_source_course_id();
        $this->assertSame((int)$course->id, $resolved);

        unset($SESSION->lastcourseaccessed);
    }

    /**
     * $SESSION->currentcourseid is used as a legacy fallback.
     *
     * @return void
     */
    public function test_session_currentcourseid_is_used(): void {
        global $SESSION;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        unset($_SERVER['HTTP_REFERER']);
        if (isset($SESSION->lastcourseaccessed)) {
            unset($SESSION->lastcourseaccessed);
        }
        $SESSION->currentcourseid = (int)$course->id;

        $resolved = \local_mycoursesfilter_resolve_source_course_id();
        $this->assertSame((int)$course->id, $resolved);

        unset($SESSION->currentcourseid);
    }

    /**
     * Session helper returns 0 when nothing is set.
     *
     * @return void
     */
    public function test_session_helper_returns_zero_when_unset(): void {
        global $SESSION;

        $this->resetAfterTest();
        if (isset($SESSION->lastcourseaccessed)) {
            unset($SESSION->lastcourseaccessed);
        }
        if (isset($SESSION->currentcourseid)) {
            unset($SESSION->currentcourseid);
        }
        $this->assertSame(0, \local_mycoursesfilter_resolve_session_course_id());
    }

    // ---------------------------------------------------------------------
    // Block-instance resolution
    // ---------------------------------------------------------------------

    /**
     * A block instance attached to a course context resolves to that course.
     *
     * @return void
     */
    public function test_block_instance_on_course_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $blockinstanceid = $DB->insert_record('block_instances', (object)[
            'blockname' => 'html',
            'parentcontextid' => $context->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertSame(
            (int)$course->id,
            \local_mycoursesfilter_course_id_from_block_instance((int)$blockinstanceid)
        );
    }

    /**
     * A block instance attached to a module context resolves to the owning course.
     *
     * @return void
     */
    public function test_block_instance_on_module_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($module->cmid);

        $blockinstanceid = $DB->insert_record('block_instances', (object)[
            'blockname' => 'html',
            'parentcontextid' => $context->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'mod-page-view',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertSame(
            (int)$course->id,
            \local_mycoursesfilter_course_id_from_block_instance((int)$blockinstanceid)
        );
    }

    /**
     * Block instances on system or category contexts return 0 because no single
     * course is derivable.
     *
     * @return void
     */
    public function test_block_instance_on_system_context_returns_zero(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $context = \context_system::instance();
        $blockinstanceid = $DB->insert_record('block_instances', (object)[
            'blockname' => 'html',
            'parentcontextid' => $context->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'site-index',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertSame(
            0,
            \local_mycoursesfilter_course_id_from_block_instance((int)$blockinstanceid)
        );
    }

    /**
     * End-to-end: a /blocks/<name>/ referer carrying blockid resolves via the dispatcher.
     *
     * @return void
     */
    public function test_block_path_dispatcher_resolves_course(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $blockinstanceid = $DB->insert_record('block_instances', (object)[
            'blockname' => 'html',
            'parentcontextid' => $context->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/blocks/html/edit.php?blockid=' . (int)$blockinstanceid;
        $this->assertSame(
            (int)$course->id,
            \local_mycoursesfilter_resolve_source_course_id()
        );
    }
}
