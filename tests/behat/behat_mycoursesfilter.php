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
 * Behat steps for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @category   test
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mycoursesfilter extends behat_base {
    /**
     * Opens the local my courses filter page.
     *
     * @When /^I am on the local my courses filter page(?: with query "(?P<query_string>(?:[^"\\]|\\.)*)")?$/
     * @param string|null $query The optional course search query.
     * @return void
     */
    public function i_am_on_the_local_my_courses_filter_page(?string $query = ''): void {
        $path = '/local/mycoursesfilter/index.php';
        if ($query !== null && $query !== '') {
            $path .= '?q=' . rawurlencode($query);
        }

        $this->getSession()->visit($this->locate_path($path));
    }
}
