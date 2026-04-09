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
 * PHPUnit tests closing URL parameter coverage gaps for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mycoursesfilter;

/**
 * PHPUnit tests covering the remaining URL parameter branches.
 *
 * Focuses on: filter (all/favourites/hidden), customfield matching, sort labels
 * and default sort order, view labels, filter labels, and return URL edge cases.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @coversNothing
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class params_coverage_test extends \advanced_testcase {
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

    /**
     * The "all" filter accepts any non-hidden course.
     *
     * @return void
     */
    public function test_match_filter_all_accepts_non_hidden_courses(): void {
        $this->assertTrue(\local_mycoursesfilter_match_filter(['ishidden' => false], 'all'));
        $this->assertTrue(\local_mycoursesfilter_match_filter(null, 'all'));
        $this->assertFalse(\local_mycoursesfilter_match_filter(['ishidden' => true], 'all'));
    }

    /**
     * The "favourites" filter only matches favourite non-hidden courses.
     *
     * @return void
     */
    public function test_match_filter_favourites_requires_favourite_flag(): void {
        $this->assertTrue(\local_mycoursesfilter_match_filter(
            ['ishidden' => false, 'isfavourite' => true],
            'favourites'
        ));
        $this->assertFalse(\local_mycoursesfilter_match_filter(
            ['ishidden' => false, 'isfavourite' => false],
            'favourites'
        ));
        $this->assertFalse(\local_mycoursesfilter_match_filter(
            ['ishidden' => true, 'isfavourite' => true],
            'favourites'
        ));
    }

    /**
     * The "hidden" filter matches hidden courses exclusively.
     *
     * @return void
     */
    public function test_match_filter_hidden_only_matches_hidden(): void {
        $this->assertTrue(\local_mycoursesfilter_match_filter(['ishidden' => true], 'hidden'));
        $this->assertFalse(\local_mycoursesfilter_match_filter(['ishidden' => false], 'hidden'));
        $this->assertFalse(\local_mycoursesfilter_match_filter(null, 'hidden'));
    }

    /**
     * Non-hidden filter buckets never return hidden courses.
     *
     * @return void
     */
    public function test_match_filter_hidden_courses_excluded_from_progress_buckets(): void {
        $meta = [
            'ishidden' => true,
            'completionenabled' => true,
            'timestarted' => 100,
            'timecompleted' => 0,
            'timeenrolled' => 50,
            'lastaccess' => 100,
            'iscompleted' => false,
        ];

        $this->assertFalse(\local_mycoursesfilter_match_filter($meta, 'inprogress'));
        $this->assertFalse(\local_mycoursesfilter_match_filter($meta, 'notstarted'));
        $this->assertFalse(\local_mycoursesfilter_match_filter($meta, 'completed'));
    }

    /**
     * Custom field matching supports exact, partial, empty-value, and mismatch cases.
     *
     * @return void
     */
    public function test_match_customfield_handles_value_variants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $cfgen = $gen->get_plugin_generator('core_customfield');

        $catdata = $cfgen->create_category([
            'component' => 'core_course',
            'area' => 'course',
            'name' => 'Department info',
        ]);
        $cfgen->create_field([
            'categoryid' => $catdata->get('id'),
            'shortname' => 'department',
            'name' => 'Department',
            'type' => 'text',
        ]);

        $course = $gen->create_course([
            'shortname' => 'CF-TEST',
            'customfield_department' => 'Molecular Biology',
        ]);

        // Empty value -> any non-empty value matches.
        $this->assertTrue(\local_mycoursesfilter_match_customfield($course->id, 'department', ''));

        // Partial, case-insensitive match.
        $this->assertTrue(\local_mycoursesfilter_match_customfield($course->id, 'department', 'molecular'));
        $this->assertTrue(\local_mycoursesfilter_match_customfield($course->id, 'department', 'BIOLOGY'));

        // Non-matching value.
        $this->assertFalse(\local_mycoursesfilter_match_customfield($course->id, 'department', 'chemistry'));

        // Unknown field shortname never matches.
        $this->assertFalse(\local_mycoursesfilter_match_customfield($course->id, 'nosuchfield', 'anything'));

        // Empty shortname short-circuits to true (no filter selected).
        $this->assertTrue(\local_mycoursesfilter_match_customfield($course->id, '', 'anything'));
    }

    /**
     * The default sort order is ascending for alphabetical sorts and descending otherwise.
     *
     * @return void
     */
    public function test_get_default_sortorder_covers_all_sort_modes(): void {
        $this->assertSame('asc', \local_mycoursesfilter_get_default_sortorder('coursename'));
        $this->assertSame('asc', \local_mycoursesfilter_get_default_sortorder('shortname'));
        $this->assertSame('desc', \local_mycoursesfilter_get_default_sortorder('lastaccess'));
        $this->assertSame('desc', \local_mycoursesfilter_get_default_sortorder('lastenrolled'));
        // Any unknown sort key falls back to descending.
        $this->assertSame('desc', \local_mycoursesfilter_get_default_sortorder('unknown'));
    }

    /**
     * Sort label helper exposes exactly the documented keys.
     *
     * @return void
     */
    public function test_get_sort_labels_exposes_all_sort_keys(): void {
        $labels = \local_mycoursesfilter_get_sort_labels();
        $this->assertSame(
            ['lastaccess', 'coursename', 'shortname', 'lastenrolled'],
            array_keys($labels)
        );
        foreach ($labels as $label) {
            $this->assertNotSame('', trim((string)$label));
        }
    }

    /**
     * View label helper exposes exactly the documented keys.
     *
     * @return void
     */
    public function test_get_view_labels_exposes_all_view_keys(): void {
        $labels = \local_mycoursesfilter_get_view_labels();
        $this->assertSame(['card', 'list', 'summary'], array_keys($labels));
        foreach ($labels as $label) {
            $this->assertNotSame('', trim((string)$label));
        }
    }

    /**
     * Filter label helper exposes exactly the documented keys.
     *
     * @return void
     */
    public function test_get_filter_labels_exposes_all_filter_keys(): void {
        $labels = \local_mycoursesfilter_get_filter_labels();
        $this->assertSame(
            ['all', 'notstarted', 'inprogress', 'completed', 'favourites', 'hidden'],
            array_keys($labels)
        );
        foreach ($labels as $label) {
            $this->assertNotSame('', trim((string)$label));
        }
    }

    /**
     * Return URL resolution rejects dangerous inputs and accepts empty strings.
     *
     * @return void
     */
    public function test_resolve_return_url_rejects_dangerous_inputs(): void {
        $this->resetAfterTest();

        // Empty input returns empty string.
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url(''));
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('   '));

        // Protocol-relative URL is rejected.
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('//evil.example/path'));

        // Absolute external URL is rejected.
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('https://evil.example/path'));

        // Backslash in input is rejected.
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('/my\\path'));

        // Control characters are rejected.
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url("/my\npath"));
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url("/my\tpath"));
    }

    /**
     * The "this" return URL token requires a same-site referer.
     *
     * @return void
     */
    public function test_resolve_return_url_this_requires_same_site_referer(): void {
        $this->resetAfterTest();

        // Without referer, "this" resolves to empty string.
        unset($_SERVER['HTTP_REFERER']);
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('this'));

        // Off-site referer is rejected.
        $_SERVER['HTTP_REFERER'] = 'https://evil.example/course/view.php?id=17';
        $this->assertSame('', \local_mycoursesfilter_resolve_return_url('this'));
    }

    /**
     * View values round-trip through the core preference mapping.
     *
     * @return void
     */
    public function test_view_values_round_trip_through_core_mapping(): void {
        foreach (['card', 'list', 'summary'] as $view) {
            $core = \local_mycoursesfilter_map_toolbar_value_to_core('view', $view);
            $this->assertSame($view, $core);
            $this->assertSame($view, \local_mycoursesfilter_map_toolbar_value_from_core('view', $core, 'card'));
        }
    }
}
