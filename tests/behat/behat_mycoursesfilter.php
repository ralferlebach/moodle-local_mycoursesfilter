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

use Behat\Gherkin\Node\TableNode;

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
     * @When /^I am on the local my courses filter page(?: with query "([^"]*)")?$/
     * @param string|null $query The optional course search query.
     * @return void
     */
    public function i_am_on_the_local_my_courses_filter_page(?string $query = ''): void {
        $params = [];
        if ($query !== null && $query !== '') {
            $params['q'] = $query;
        }

        $this->visit_filter_page($params);
    }

    /**
     * Opens the local my courses filter page with a return URL.
     *
     * @When /^I am on the local my courses filter page with return URL "([^"]*)"$/
     * @param string $returnurl The return URL.
     * @return void
     */
    public function i_am_on_the_local_my_courses_filter_page_with_return_url(string $returnurl): void {
        $this->visit_filter_page(['returnurl' => $returnurl]);
    }

    /**
     * Opens the local my courses filter page with query and return URL.
     *
     * @When /^I am on the local my courses filter page with query "([^"]*)" and return URL "([^"]*)"$/
     * @param string $query The course search query.
     * @param string $returnurl The return URL.
     * @return void
     */
    public function i_am_on_the_local_my_courses_filter_page_with_query_and_return_url(
        string $query,
        string $returnurl
    ): void {
        $this->visit_filter_page([
            'q' => $query,
            'returnurl' => $returnurl,
        ]);
    }



    /** @When /^I am on the local my courses filter page with the following parameters:$/ */
    /**
     * Opens the local my courses filter page with arbitrary parameters.
     *
     * @param TableNode $table The parameter table.
     * @return void
     */
    public function i_am_on_the_local_my_courses_filter_page_with_the_following_parameters(TableNode $table): void {
        $params = [];
        foreach ($table->getHash() as $row) {
            if (!isset($row['name']) || !isset($row['value'])) {
                throw new coding_exception('The parameter table must contain name and value columns.');
            }
            $params[$row['name']] = $row['value'];
        }

        $this->visit_filter_page($params);
    }

    /**
     * Visits the filter page with the supplied parameters.
     *
     * @param array<string, string> $params The URL parameters.
     * @return void
     */
    protected function visit_filter_page(array $params): void {
        $url = new moodle_url('/local/mycoursesfilter/index.php', $params);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }
}
