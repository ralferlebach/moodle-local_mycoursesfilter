<?php
defined('MOODLE_INTERNAL') || die();

function local_mycoursesfilter_before_standard_html_head() {
    global $PAGE;

    if ($PAGE->url->compare(new moodle_url('/my/courses.php'), URL_MATCH_BASE)) {
        $PAGE->requires->js_call_amd(
            'local_mycoursesfilter/initfilter',
            'init'
        );
    }
}
