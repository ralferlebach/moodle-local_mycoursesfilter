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
 * Library functions for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Require that the current user has at least one of the given roles in any course context.
 *
 * @param array $roleshortnames Array of role shortnames, e.g. ['student'].
 * @return void
 * @throws moodle_exception If the user does not have any of the required roles.
 */
function local_mycoursesfilter_require_any_course_role(array $roleshortnames): void {
    global $DB, $USER;

    // Admins are always allowed (optional behaviour).
    if (is_siteadmin($USER)) {
        return;
    }

    if (empty($roleshortnames)) {
        return;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($roleshortnames, SQL_PARAMS_NAMED, 'r');

    $params = array_merge($inparams, [
        'userid' => $USER->id,
        'ctxlevel' => CONTEXT_COURSE,
    ]);

    $sql = "SELECT 1
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ra.userid = :userid
               AND ctx.contextlevel = :ctxlevel
               AND r.shortname $insql";

    $ok = $DB->record_exists_sql($sql, $params);
    if (!$ok) {
        throw new moodle_exception('nopermissions', 'error', '', 'role=student (course context)');
    }
}
