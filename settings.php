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
 * Plugin settings for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mycoursesfilter', get_string('pluginname', 'local_mycoursesfilter'));

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configselect(
            'local_mycoursesfilter/persisttoolbar',
            get_string('persisttoolbar', 'local_mycoursesfilter'),
            get_string('persisttoolbar_desc', 'local_mycoursesfilter'),
            'none',
            [
                'none' => get_string('persisttoolbar_none', 'local_mycoursesfilter'),
                'core' => get_string('persisttoolbar_core', 'local_mycoursesfilter'),
            ]
        ));

        $settings->add(new admin_setting_configselect(
            'local_mycoursesfilter/categoryscope',
            get_string('categoryscope', 'local_mycoursesfilter'),
            get_string('categoryscope_desc', 'local_mycoursesfilter'),
            'recursive',
            [
                'recursive' => get_string('categoryscope_recursive', 'local_mycoursesfilter'),
                'only' => get_string('categoryscope_only', 'local_mycoursesfilter'),
            ]
        ));

        $settings->add(new admin_setting_configtext(
            'local_mycoursesfilter/defaulttitle',
            get_string('defaulttitle', 'local_mycoursesfilter'),
            get_string('defaulttitle_desc', 'local_mycoursesfilter'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_mycoursesfilter/allowtitleoverride',
            get_string('allowtitleoverride', 'local_mycoursesfilter'),
            get_string('allowtitleoverride_desc', 'local_mycoursesfilter'),
            1
        ));
    }

    $ADMIN->add('localplugins', $settings);
}
