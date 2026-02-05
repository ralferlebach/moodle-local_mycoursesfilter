<?php
defined('MOODLE_INTERNAL') || die();



/**
 * Require that the current user has at least one of the given roles in any COURSE context.
 *
 * @param string[] $roleshortnames e.g. ['student']
 */
function local_mycoursesfilter_require_any_course_role(array $roleshortnames): void {
    global $DB, $USER;

    if (is_siteadmin($USER)) {
        return; // Admins always allowed (optional).
    }

    if (empty($roleshortnames)) {
        return;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($roleshortnames, SQL_PARAMS_NAMED, 'r');

    $params = array_merge($inparams, [
        'userid' => $USER->id,
        'ctxlevel' => CONTEXT_COURSE,
    ]);

    $sql = "
        SELECT 1
          FROM {role_assignments} ra
          JOIN {context} ctx ON ctx.id = ra.contextid
          JOIN {role} r ON r.id = ra.roleid
         WHERE ra.userid = :userid
           AND ctx.contextlevel = :ctxlevel
           AND r.shortname $insql
         LIMIT 1
    ";

    $ok = $DB->record_exists_sql($sql, $params);
    if (!$ok) {
        print_error('nopermissions', 'error', '', 'role=student (course context)');
    }
}