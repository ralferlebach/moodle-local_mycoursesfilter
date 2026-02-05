<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$query = optional_param('query', '', PARAM_TEXT);

// Weiterleitungs-URL bauen
$url = new moodle_url('/my/courses.php', [
    'local_mycoursesfilter' => 1,
    'query' => $query
]);

redirect($url);
