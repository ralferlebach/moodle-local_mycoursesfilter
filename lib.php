<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

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

function local_mycoursesfilter_get_meta_for_courses(array $courseids): array {
    global $DB, $USER;

    if (empty($courseids)) {
        return [];
    }

    $meta = [];
    foreach ($courseids as $cid) {
    $meta[$cid] = [
    'lastaccess'        => 0,
    'lastenrolled'      => 0,
    'completionenabled' => false,
    'iscompleted'       => false,
    'timecompleted'     => 0,
    'timestarted'       => 0,
    'timeenrolled'      => 0,
];
    }

    // 1) Last access: user_lastaccess
    list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
    $params['userid'] = $USER->id;

    $sql = "SELECT courseid, timeaccess
              FROM {user_lastaccess}
             WHERE userid = :userid AND courseid $insql";
    foreach ($DB->get_records_sql($sql, $params) as $r) {
        $meta[(int)$r->courseid]['lastaccess'] = (int)$r->timeaccess;
    }

    // 2) Last enrolled: user_enrolments (max timecreated per course)
    $sql = "SELECT e.courseid, MAX(ue.timecreated) AS t
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
             WHERE ue.userid = :userid AND e.courseid $insql
          GROUP BY e.courseid";
    foreach ($DB->get_records_sql($sql, $params) as $r) {
        $meta[(int)$r->courseid]['lastenrolled'] = (int)$r->t;
    }

    // 3) Course completion (falls aktiviert)
    foreach ($courseids as $cid) {
        $course = get_course($cid);
        $cinfo = new completion_info($course);
        $meta[$cid]['completionenabled'] = $cinfo->is_enabled();
        if (!$meta[$cid]['completionenabled']) {
            continue;
        }
        if ($cinfo->is_enabled()) {
            $meta[$cid]['iscompleted'] = $cinfo->is_course_complete($USER->id);
        }
    }

    return $meta;
}

function local_mycoursesfilter_match_status(stdClass $course, ?array $meta, string $status): bool {
    if ($status === 'any') {
        return true;
    }

    $meta = $meta ?? [];

    $completionenabled = !empty($meta['completionenabled']);
    $timecompleted     = (int)($meta['timecompleted'] ?? 0);
    $timestarted       = (int)($meta['timestarted'] ?? 0);
    $lastaccess        = (int)($meta['lastaccess'] ?? 0);
    $iscompleted       = !empty($meta['iscompleted']);

    // Hilfsableitungen
    $hascompletionrecord = ($timecompleted > 0) || ($timestarted > 0) || ((int)($meta['timeenrolled'] ?? 0) > 0);

    // -------- completed --------
    if ($status === 'completed') {
        // Wenn Completion aktiv und Record vorhanden: eindeutig
        if ($completionenabled && $hascompletionrecord) {
            return $timecompleted > 0;
        }
        // Wenn Completion aktiv, aber kein Record: fallback bool (falls gesetzt)
        if ($completionenabled) {
            return $iscompleted; // kann true sein via is_course_complete fallback
        }
        // Completion nicht aktiv: keine verlässliche Completed-Definition
        return false;
    }

    // -------- notstarted --------
    if ($status === 'notstarted') {
        // Wenn Completion aktiv und Record vorhanden: "nicht begonnen" = nicht gestartet und nicht abgeschlossen
        if ($completionenabled && $hascompletionrecord) {
            return ($timestarted === 0) && ($timecompleted === 0);
        }

        // Completion aktiv, aber kein Record (oder nicht aktiv): Proxy über Zugriff
        // "nicht begonnen" = nie zugegriffen und nicht completed (falls irgendwo erkannt)
        return ($lastaccess === 0) && !$iscompleted;
    }

    // -------- inprogress --------
    if ($status === 'inprogress') {
        if ($completionenabled && $hascompletionrecord) {
            return ($timestarted > 0) && ($timecompleted === 0);
        }

        // Proxy: Zugriff vorhanden, nicht completed
        return ($lastaccess > 0) && !$iscompleted;
    }

    return true;
}
