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
 * Privacy provider for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @category   privacy
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mycoursesfilter\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_mycoursesfilter.
 */
class provider implements \core_privacy\local\metadata\provider, user_preference_provider {
    /** @var array<string, string> Preference metadata language string mapping. */
    private const PREFERENCES = [
        'local_mycoursesfilter_dir' => 'privacy:metadata:preference:dir',
        'local_mycoursesfilter_filter' => 'privacy:metadata:preference:filter',
        'local_mycoursesfilter_sort' => 'privacy:metadata:preference:sort',
        'local_mycoursesfilter_view' => 'privacy:metadata:preference:view',
    ];

    /**
     * Returns metadata about user preferences stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::PREFERENCES as $preference => $stringidentifier) {
            $collection->add_user_preference($preference, $stringidentifier);
        }

        return $collection;
    }

    /**
     * Exports the user preferences stored by this plugin.
     *
     * @param int $userid The user whose preferences are exported.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        foreach (self::PREFERENCES as $preference => $stringidentifier) {
            $value = get_user_preferences($preference, null, $userid);
            if ($value === null) {
                continue;
            }

            writer::export_user_preference(
                'local_mycoursesfilter',
                $preference,
                $value,
                get_string($stringidentifier, 'local_mycoursesfilter', self::describe_preference_value($preference, (string)$value))
            );
        }
    }

    /**
     * Returns a human-readable description of a preference value.
     *
     * @param string $preference The preference name.
     * @param string $value The stored preference value.
     * @return string
     */
    private static function describe_preference_value(string $preference, string $value): string {
        $descriptions = [
            'local_mycoursesfilter_dir' => [
                'asc' => get_string('privacy:preference:dir:asc', 'local_mycoursesfilter'),
                'desc' => get_string('privacy:preference:dir:desc', 'local_mycoursesfilter'),
            ],
            'local_mycoursesfilter_filter' => [
                'all' => get_string('filter_all', 'local_mycoursesfilter'),
                'completed' => get_string('filter_completed', 'local_mycoursesfilter'),
                'favourites' => get_string('filter_favourites', 'local_mycoursesfilter'),
                'hidden' => get_string('filter_hidden', 'local_mycoursesfilter'),
                'inprogress' => get_string('filter_inprogress', 'local_mycoursesfilter'),
                'notstarted' => get_string('filter_notstarted', 'local_mycoursesfilter'),
            ],
            'local_mycoursesfilter_sort' => [
                'alpha' => get_string('sort_alpha', 'local_mycoursesfilter'),
                'lastaccess' => get_string('sort_lastaccess', 'local_mycoursesfilter'),
                'lastenrolled' => get_string('sort_lastenrolled', 'local_mycoursesfilter'),
                'shortname' => get_string('sort_shortname', 'local_mycoursesfilter'),
            ],
            'local_mycoursesfilter_view' => [
                'card' => get_string('view_card', 'local_mycoursesfilter'),
                'list' => get_string('view_list', 'local_mycoursesfilter'),
                'summary' => get_string('view_summary', 'local_mycoursesfilter'),
            ],
        ];

        return $descriptions[$preference][$value] ?? $value;
    }
}
