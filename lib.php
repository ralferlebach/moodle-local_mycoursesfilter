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
 * Plugin library for local_mycoursesfilter.
 *
 * @package    local_mycoursesfilter
 * @copyright  2026 Ralf Erlebach <moodle-dev@ralferlebach.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Returns the configured toolbar persistence mode.
 *
 * @return string
 */
function local_mycoursesfilter_get_toolbar_persistence_mode(): string {
    $mode = (string)get_config('local_mycoursesfilter', 'persisttoolbar');
    if (!in_array($mode, ['none', 'core'], true)) {
        $mode = 'none';
    }

    return $mode;
}

/**
 * Returns the core my/courses preference name for a toolbar control.
 *
 * @param string $name Toolbar control name.
 * @return string
 */
function local_mycoursesfilter_get_core_toolbar_preference_name(string $name): string {
    $mapping = [
        'filter' => 'block_myoverview_user_grouping_preference',
        'sort' => 'block_myoverview_user_sort_preference',
        'view' => 'block_myoverview_user_view_preference',
    ];

    return $mapping[$name] ?? '';
}

/**
 * Maps a plugin toolbar value to the equivalent core my/courses value.
 *
 * Returns an empty string when no safe mapping exists.
 *
 * @param string $name Toolbar control name.
 * @param string $value Plugin toolbar value.
 * @return string
 */
function local_mycoursesfilter_map_toolbar_value_to_core(string $name, string $value): string {
    $mapping = [
        'filter' => [
            'all' => 'all',
            'inprogress' => 'inprogress',
            'favourites' => 'favourites',
            'hidden' => 'hidden',
        ],
        'sort' => [
            'lastaccess' => 'lastaccessed',
            'coursename' => 'title',
            'shortname' => 'shortname',
        ],
        'view' => [
            'card' => 'card',
            'list' => 'list',
            'summary' => 'summary',
        ],
    ];

    return $mapping[$name][$value] ?? '';
}

/**
 * Maps a core my/courses preference value to the equivalent plugin value.
 *
 * Unsupported values fall back to the supplied default.
 *
 * @param string $name Toolbar control name.
 * @param string $value Core preference value.
 * @param string $default Default plugin value.
 * @return string
 */
function local_mycoursesfilter_map_toolbar_value_from_core(string $name, string $value, string $default): string {
    $mapping = [
        'filter' => [
            'all' => 'all',
            'allincludinghidden' => 'all',
            'inprogress' => 'inprogress',
            'favourites' => 'favourites',
            'hidden' => 'hidden',
        ],
        'sort' => [
            'lastaccessed' => 'lastaccess',
            'title' => 'coursename',
            'shortname' => 'shortname',
        ],
        'view' => [
            'card' => 'card',
            'list' => 'list',
            'summary' => 'summary',
        ],
    ];

    return $mapping[$name][$value] ?? $default;
}

/**
 * Resolves a toolbar preference.
 *
 * Request parameters override the current effective value. When toolbar persistence is
 * enabled, compatible values are stored via core my/courses preferences instead of
 * plugin-owned preferences.
 *
 * @param string $name The request parameter name.
 * @param string $default The default value.
 * @param string $paramtype The Moodle PARAM_* type constant (e.g. PARAM_ALPHA).
 * @param string[] $allowed Allowed values.
 * @return string
 */
function local_mycoursesfilter_resolve_toolbar_preference(
    string $name,
    string $default,
    string $paramtype,
    array $allowed
): string {
    global $USER;

    $sentinel = '__local_mycoursesfilter_missing__';
    $requestvalue = optional_param($name, $sentinel, $paramtype);
    $hasrequestvalue = ($requestvalue !== $sentinel);

    if ($hasrequestvalue) {
        $value = $requestvalue;
        if (!in_array($value, $allowed, true)) {
            return $default;
        }

        if (local_mycoursesfilter_get_toolbar_persistence_mode() === 'core') {
            $coreprefname = local_mycoursesfilter_get_core_toolbar_preference_name($name);
            $corevalue = local_mycoursesfilter_map_toolbar_value_to_core($name, $value);
            if ($coreprefname !== '' && $corevalue !== '') {
                set_user_preference($coreprefname, $corevalue, $USER->id);
            }
        }

        return $value;
    }

    if (local_mycoursesfilter_get_toolbar_persistence_mode() === 'core') {
        $coreprefname = local_mycoursesfilter_get_core_toolbar_preference_name($name);
        if ($coreprefname !== '') {
            $storedvalue = (string)get_user_preferences($coreprefname, '', $USER->id);
            $value = local_mycoursesfilter_map_toolbar_value_from_core($name, $storedvalue, $default);
            if (in_array($value, $allowed, true)) {
                return $value;
            }
        }
    }

    return $default;
}

/**
 * Returns the configured default category scope.
 *
 * @return string
 */
function local_mycoursesfilter_get_default_category_scope(): string {
    $scope = (string)get_config('local_mycoursesfilter', 'categoryscope');
    if (!in_array($scope, ['recursive', 'only'], true)) {
        $scope = 'recursive';
    }

    return $scope;
}

/**
 * Resolves the effective category scope for the current request.
 *
 * @param bool $only Whether recursion is explicitly disabled.
 * @param bool $recursive Whether recursion is explicitly enabled.
 * @return string
 */
function local_mycoursesfilter_resolve_category_scope(bool $only, bool $recursive): string {
    if ($only) {
        return 'only';
    }

    if ($recursive) {
        return 'recursive';
    }

    return local_mycoursesfilter_get_default_category_scope();
}

/**
 * Returns whether URL title overrides are enabled.
 *
 * @return bool
 */
function local_mycoursesfilter_is_title_override_enabled(): bool {
    return (int)get_config('local_mycoursesfilter', 'allowtitleoverride') !== 0;
}

/**
 * Resolves the effective page title.
 *
 * @param string $requestedtitle Optional title received from the URL.
 * @return string
 */
function local_mycoursesfilter_resolve_page_title(string $requestedtitle): string {
    $configuredtitle = trim((string)get_config('local_mycoursesfilter', 'defaulttitle'));

    if ($requestedtitle !== '' && local_mycoursesfilter_is_title_override_enabled()) {
        return format_string($requestedtitle, true, ['context' => context_system::instance()]);
    }

    if ($configuredtitle !== '') {
        return format_string($configuredtitle, true, ['context' => context_system::instance()]);
    }

    return get_string('pagetitle', 'local_mycoursesfilter');
}

/**
 * Returns the local referrer URL when it belongs to this Moodle site.
 *
 * @return string
 */
function local_mycoursesfilter_get_local_referer_url(): string {
    global $CFG;

    if (empty($_SERVER['HTTP_REFERER'])) {
        return '';
    }

    $refererparts = parse_url((string)$_SERVER['HTTP_REFERER']);
    $siteparts = parse_url($CFG->wwwroot);
    if ($refererparts === false || $siteparts === false) {
        return '';
    }

    if (!local_mycoursesfilter_is_same_site_url($refererparts, $siteparts)) {
        return '';
    }

    $localurl = local_mycoursesfilter_build_local_url_from_parts($refererparts);
    if ($localurl === '') {
        return '';
    }

    $cleanurl = clean_param($localurl, PARAM_LOCALURL);
    if ($cleanurl === '' || $cleanurl !== $localurl) {
        return '';
    }

    return $cleanurl;
}

/**
 * Checks whether two parsed URLs belong to the same site.
 *
 * @param array $candidateparts Parsed candidate URL parts.
 * @param array $siteparts Parsed site URL parts.
 * @return bool
 */
function local_mycoursesfilter_is_same_site_url(array $candidateparts, array $siteparts): bool {
    if (!empty($candidateparts['host']) && !empty($siteparts['host'])) {
        if (core_text::strtolower((string)$candidateparts['host']) !== core_text::strtolower((string)$siteparts['host'])) {
            return false;
        }
    }

    if (!empty($candidateparts['scheme']) && !empty($siteparts['scheme'])) {
        if (core_text::strtolower((string)$candidateparts['scheme']) !== core_text::strtolower((string)$siteparts['scheme'])) {
            return false;
        }
    }

    $candidateport = (int)($candidateparts['port'] ?? 0);
    $siteport = (int)($siteparts['port'] ?? 0);

    return ($candidateport === 0 || $siteport === 0 || $candidateport === $siteport);
}

/**
 * Builds a local URL string from parsed URL parts.
 *
 * @param array $urlparts Parsed URL parts.
 * @return string
 */
function local_mycoursesfilter_build_local_url_from_parts(array $urlparts): string {
    $localurl = (string)($urlparts['path'] ?? '');
    if (!empty($urlparts['query'])) {
        $localurl .= '?' . $urlparts['query'];
    }

    if (!empty($urlparts['fragment'])) {
        $localurl .= '#' . $urlparts['fragment'];
    }

    return $localurl;
}

/**
 * Returns the Moodle base path from $CFG->wwwroot.
 *
 * @return string
 */
function local_mycoursesfilter_get_site_path(): string {
    global $CFG;

    $sitepath = (string)(parse_url($CFG->wwwroot, PHP_URL_PATH) ?? '');
    if ($sitepath === '' || $sitepath === '/') {
        return '';
    }

    return rtrim($sitepath, '/');
}

/**
 * Removes the Moodle base path from a local URL path.
 *
 * @param string $path The local URL path.
 * @return string
 */
function local_mycoursesfilter_strip_site_path(string $path): string {
    $sitepath = local_mycoursesfilter_get_site_path();
    if ($sitepath === '' || strpos($path, $sitepath) !== 0) {
        return $path;
    }

    $strippedpath = substr($path, strlen($sitepath));
    if ($strippedpath === false || $strippedpath === '') {
        return '/';
    }

    return $strippedpath;
}

/**
 * Resolves the return URL parameter.
 *
 * The special value "this" maps to the current local referrer.
 *
 * @param string $rawreturnurl Raw URL parameter.
 * @return string
 */
function local_mycoursesfilter_resolve_return_url(string $rawreturnurl): string {
    $rawreturnurl = trim($rawreturnurl);
    if ($rawreturnurl === '') {
        return '';
    }

    if ($rawreturnurl === 'this') {
        return local_mycoursesfilter_get_local_referer_url();
    }

    return local_mycoursesfilter_normalise_explicit_local_url($rawreturnurl);
}

/**
 * Normalises an explicit local URL for this Moodle installation.
 *
 * @param string $rawurl Raw local URL.
 * @return string
 */
function local_mycoursesfilter_normalise_explicit_local_url(string $rawurl): string {
    if (preg_match('/[\x00-\x1F\x7F]/', $rawurl) || strpos($rawurl, '\\') !== false || strpos($rawurl, '//') === 0) {
        return '';
    }

    $cleanurl = clean_param($rawurl, PARAM_LOCALURL);
    if ($cleanurl === '' || $cleanurl !== $rawurl || strpos($cleanurl, '/') !== 0) {
        return '';
    }

    $sitepath = local_mycoursesfilter_get_site_path();
    if ($sitepath !== '' && strpos($cleanurl, $sitepath . '/') !== 0 && $cleanurl !== $sitepath) {
        return $sitepath . $cleanurl;
    }

    return $cleanurl;
}

/**
 * Resolves the source course id for contextual category shortcuts.
 *
 * Supports several referer URL shapes and always enforces an access check
 * against the current user before returning a course id:
 *
 *  - Explicit `courseid` URL parameter on the filter page itself.
 *  - `/course/view.php?id=COURSEID`.
 *  - `/course/section.php?id=SECTIONID` (course resolved via section record).
 *  - `/mod/<modname>/...` with a `cmid` parameter, or with an `id` parameter
 *    that is first tried as a course module id and then as an instance id of
 *    the activity module (`mdl_<modname>.id`).
 *  - Any same-site URL exposing a numeric `courseid` query parameter.
 *  - `/user/profile.php?course=COURSEID`.
 *
 * A resolved course id is returned only when the current user can access the
 * matching course via {@see can_access_course()}.
 *
 * @param int $explicitcourseid Optional explicit course id from the URL.
 * @return int
 */
function local_mycoursesfilter_resolve_source_course_id(int $explicitcourseid = 0): int {
    if ($explicitcourseid > 0) {
        return local_mycoursesfilter_user_can_access_source_course($explicitcourseid)
            ? $explicitcourseid
            : 0;
    }

    $refererurl = local_mycoursesfilter_get_local_referer_url();
    if ($refererurl === '') {
        return 0;
    }

    $refererparts = parse_url($refererurl);
    if ($refererparts === false || empty($refererparts['query'])) {
        return 0;
    }

    $refererpath = local_mycoursesfilter_strip_site_path((string)($refererparts['path'] ?? ''));
    parse_str((string)$refererparts['query'], $params);

    $resolvedcourseid = local_mycoursesfilter_resolve_course_id_from_referer_path($refererpath, $params);
    if ($resolvedcourseid <= 0) {
        return 0;
    }

    if (!local_mycoursesfilter_user_can_access_source_course($resolvedcourseid)) {
        return 0;
    }

    return $resolvedcourseid;
}

/**
 * Resolves a course id from a parsed referer path and query parameters.
 *
 * Split out from {@see local_mycoursesfilter_resolve_source_course_id()} to
 * keep the dispatcher testable without manipulating superglobals.
 *
 * @param string $path Local referer path (site path already stripped).
 * @param array $params Parsed query parameters from the referer URL.
 * @return int Course id, or 0 when no mapping applies.
 */
function local_mycoursesfilter_resolve_course_id_from_referer_path(string $path, array $params): int {
    global $DB;

    // Generic courseid parameter: many Moodle pages expose the course via this name.
    if (!empty($params['courseid']) && ctype_digit((string)$params['courseid'])) {
        return (int)$params['courseid'];
    }

    // Classic entry point: /course/view.php?id=COURSEID.
    if ($path === '/course/view.php' && !empty($params['id']) && ctype_digit((string)$params['id'])) {
        return (int)$params['id'];
    }

    // Moodle 4.4+ standalone section page: /course/section.php?id=SECTIONID.
    if ($path === '/course/section.php' && !empty($params['id']) && ctype_digit((string)$params['id'])) {
        $sectionid = (int)$params['id'];
        $courseid = (int)$DB->get_field('course_sections', 'course', ['id' => $sectionid]);
        return $courseid > 0 ? $courseid : 0;
    }

    // Activity modules: /mod/<modname>/... scripts.
    if (preg_match('#^/mod/([a-z][a-z0-9_]*)/#', $path, $matches)) {
        $modname = $matches[1];

        // Explicit cmid parameter always wins when present.
        if (!empty($params['cmid']) && ctype_digit((string)$params['cmid'])) {
            $courseid = local_mycoursesfilter_course_id_from_cmid((int)$params['cmid']);
            if ($courseid > 0) {
                return $courseid;
            }
        }

        // Generic id parameter: try as cmid first, then fall back to instance id.
        if (!empty($params['id']) && ctype_digit((string)$params['id'])) {
            $idvalue = (int)$params['id'];
            $courseid = local_mycoursesfilter_course_id_from_cmid($idvalue);
            if ($courseid > 0) {
                return $courseid;
            }

            $courseid = local_mycoursesfilter_course_id_from_mod_instance($modname, $idvalue);
            if ($courseid > 0) {
                return $courseid;
            }
        }
    }

    // /user/profile.php?course=COURSEID.
    if ($path === '/user/profile.php' && !empty($params['course']) && ctype_digit((string)$params['course'])) {
        return (int)$params['course'];
    }

    return 0;
}

/**
 * Resolves the course id for a course module id.
 *
 * @param int $cmid Course module id.
 * @return int Course id, or 0 when the cmid does not exist.
 */
function local_mycoursesfilter_course_id_from_cmid(int $cmid): int {
    global $DB;

    if ($cmid <= 0) {
        return 0;
    }

    return (int)$DB->get_field('course_modules', 'course', ['id' => $cmid]);
}

/**
 * Resolves the course id for an activity module instance id.
 *
 * @param string $modname Activity module short name (e.g. `forum`).
 * @param int $instanceid Instance id in the module table.
 * @return int Course id, or 0 when the instance does not exist.
 */
function local_mycoursesfilter_course_id_from_mod_instance(string $modname, int $instanceid): int {
    global $DB;

    if ($instanceid <= 0) {
        return 0;
    }

    if (!preg_match('/^[a-z][a-z0-9_]*$/', $modname)) {
        return 0;
    }

    try {
        if (!$DB->get_manager()->table_exists($modname)) {
            return 0;
        }
    } catch (\Throwable $e) {
        return 0;
    }

    return (int)$DB->get_field($modname, 'course', ['id' => $instanceid]);
}

/**
 * Checks whether the current user may use the given course as a source context.
 *
 * Uses {@see can_access_course()} which mirrors core's own visibility rules
 * (including guest access, hidden courses, and role overrides).
 *
 * @param int $courseid Candidate course id.
 * @return bool
 */
function local_mycoursesfilter_user_can_access_source_course(int $courseid): bool {
    if ($courseid <= 0) {
        return false;
    }

    try {
        $course = get_course($courseid);
    } catch (\dml_missing_record_exception $e) {
        return false;
    } catch (\Throwable $e) {
        return false;
    }

    return can_access_course($course);
}

/**
 * Parses a raw category selector string.
 *
 * The input may contain numeric ids and the keywords this, parent, and children.
 *
 * @param string $rawcatid Raw catid parameter.
 * @return string[]
 */
function local_mycoursesfilter_parse_category_tokens(string $rawcatid): array {
    $rawcatid = trim($rawcatid);
    if ($rawcatid === '') {
        return [];
    }

    if (!preg_match('/^(?:\s*(?:\d+|this|parent|children)\s*)(?:,\s*(?:\d+|this|parent|children)\s*)*$/i', $rawcatid)) {
        return [];
    }

    $tokens = [];
    foreach (explode(',', $rawcatid) as $token) {
        $token = trim(core_text::strtolower($token));
        if ($token === '') {
            continue;
        }

        if (ctype_digit($token)) {
            $token = (string)((int)$token);
        }

        if (!in_array($token, $tokens, true)) {
            $tokens[] = $token;
        }
    }

    return $tokens;
}

/**
 * Returns the category id for a course.
 *
 * @param int $courseid Course id.
 * @return int
 */
function local_mycoursesfilter_get_course_category_id(int $courseid): int {
    global $DB;

    if ($courseid <= 0) {
        return 0;
    }

    return (int)$DB->get_field('course', 'category', ['id' => $courseid]) ?: 0;
}

/**
 * Returns the parent category id.
 *
 * @param int $categoryid Category id.
 * @return int
 */
function local_mycoursesfilter_get_category_parent_id(int $categoryid): int {
    global $DB;

    if ($categoryid <= 0) {
        return 0;
    }

    return (int)$DB->get_field('course_categories', 'parent', ['id' => $categoryid]) ?: 0;
}

/**
 * Returns the direct child categories of a category.
 *
 * @param int $categoryid Category id.
 * @return int[]
 */
function local_mycoursesfilter_get_immediate_child_category_ids(int $categoryid): array {
    global $DB;

    if ($categoryid <= 0) {
        return [];
    }

    $childids = $DB->get_fieldset_select('course_categories', 'id', 'parent = :parent', ['parent' => $categoryid]);
    return array_map('intval', $childids);
}

/**
 * Recursively expands category ids to include all descendant categories.
 *
 * @param int[] $categoryids Base category ids.
 * @return int[]
 */
function local_mycoursesfilter_expand_descendant_category_ids(array $categoryids): array {
    $seen = [];
    $queue = [];

    foreach ($categoryids as $categoryid) {
        $categoryid = (int)$categoryid;
        if ($categoryid <= 0 || isset($seen[$categoryid])) {
            continue;
        }

        $seen[$categoryid] = $categoryid;
        $queue[] = $categoryid;
    }

    while ($queue !== []) {
        $current = array_shift($queue);
        foreach (local_mycoursesfilter_get_immediate_child_category_ids((int)$current) as $childid) {
            if ($childid <= 0 || isset($seen[$childid])) {
                continue;
            }

            $seen[$childid] = $childid;
            $queue[] = $childid;
        }
    }

    return array_values($seen);
}

/**
 * Resolves the effective category ids for the current request.
 *
 * @param string $rawcatid Raw catid parameter.
 * @param string $scope Effective category scope.
 * @param int $sourcecourseid Optional source course id.
 * @return int[]
 */
function local_mycoursesfilter_resolve_category_ids(
    string $rawcatid,
    string $scope,
    int $sourcecourseid = 0
): array {
    $tokens = local_mycoursesfilter_parse_category_tokens($rawcatid);
    if ($tokens === []) {
        return [];
    }

    $sourcecategoryid = local_mycoursesfilter_get_course_category_id($sourcecourseid);
    $categoryids = [];

    foreach ($tokens as $token) {
        foreach (local_mycoursesfilter_resolve_category_token($token, $sourcecategoryid) as $categoryid) {
            $categoryids[] = $categoryid;
        }
    }

    $categoryids = local_mycoursesfilter_filter_existing_category_ids($categoryids);

    return local_mycoursesfilter_normalise_category_id_list($categoryids, $scope === 'recursive');
}

/**
 * Resolves one category token into category ids.
 *
 * @param string $token Parsed category token.
 * @param int $sourcecategoryid Source course category id.
 * @return int[]
 */
function local_mycoursesfilter_resolve_category_token(string $token, int $sourcecategoryid): array {
    if (ctype_digit($token)) {
        return [(int)$token];
    }

    if ($sourcecategoryid <= 0) {
        return [];
    }

    if ($token === 'this') {
        return [$sourcecategoryid];
    }

    if ($token === 'parent') {
        $parentid = local_mycoursesfilter_get_category_parent_id($sourcecategoryid);
        return $parentid > 0 ? [$parentid] : [];
    }

    if ($token === 'children') {
        return array_values(array_filter(
            local_mycoursesfilter_get_immediate_child_category_ids($sourcecategoryid),
            static function (int $childid) use ($sourcecategoryid): bool {
                return $childid !== $sourcecategoryid;
            }
        ));
    }

    return [];
}

/**
 * Filters a category id list down to existing categories.
 *
 * @param int[] $categoryids Candidate category ids.
 * @return int[]
 */
function local_mycoursesfilter_filter_existing_category_ids(array $categoryids): array {
    global $DB;

    $normalised = [];
    foreach ($categoryids as $categoryid) {
        $categoryid = (int)$categoryid;
        if ($categoryid > 0) {
            $normalised[$categoryid] = $categoryid;
        }
    }

    if ($normalised === []) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal(array_values($normalised), SQL_PARAMS_NAMED, 'cat');
    $existingids = $DB->get_fieldset_select('course_categories', 'id', "id $insql", $params);

    return array_map('intval', $existingids);
}

/**
 * Normalises a list of category ids and optionally expands descendants.
 *
 * @param int[] $categoryids Category ids.
 * @param bool $recursive Whether descendants should be included.
 * @return int[]
 */
function local_mycoursesfilter_normalise_category_id_list(array $categoryids, bool $recursive): array {
    $normalised = [];
    foreach ($categoryids as $categoryid) {
        $categoryid = (int)$categoryid;
        if ($categoryid > 0) {
            $normalised[$categoryid] = $categoryid;
        }
    }

    $categoryids = array_values($normalised);
    if ($recursive) {
        $categoryids = local_mycoursesfilter_expand_descendant_category_ids($categoryids);
    }

    sort($categoryids);
    return $categoryids;
}

/**
 * Converts category ids into a stable request parameter.
 *
 * @param int[] $categoryids Category ids.
 * @return string
 */
function local_mycoursesfilter_normalise_category_ids_param(array $categoryids): string {
    $categoryids = local_mycoursesfilter_normalise_category_id_list($categoryids, false);
    if ($categoryids === []) {
        return '';
    }

    return implode(',', $categoryids);
}

/**
 * Returns the available filter labels.
 *
 * @return array<string, string>
 */
function local_mycoursesfilter_get_filter_labels(): array {
    return [
        'all' => get_string('filter_all', 'local_mycoursesfilter'),
        'notstarted' => get_string('filter_notstarted', 'local_mycoursesfilter'),
        'inprogress' => get_string('filter_inprogress', 'local_mycoursesfilter'),
        'completed' => get_string('filter_completed', 'local_mycoursesfilter'),
        'favourites' => get_string('filter_favourites', 'local_mycoursesfilter'),
        'hidden' => get_string('filter_hidden', 'local_mycoursesfilter'),
    ];
}

/**
 * Ensures that the current user has at least one of the supplied course roles.
 *
 * @param string[] $roleshortnames The role shortnames to accept.
 * @return void
 * @throws moodle_exception If the current user does not match the required roles.
 */
function local_mycoursesfilter_require_any_course_role(array $roleshortnames): void {
    global $DB, $USER;

    if (is_siteadmin($USER)) {
        return;
    }

    if (empty($roleshortnames)) {
        return;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($roleshortnames, SQL_PARAMS_NAMED, 'role');
    $params = $inparams + [
        'userid' => $USER->id,
        'contextlevel' => CONTEXT_COURSE,
    ];

    $sql = "SELECT 1
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ra.userid = :userid
               AND ctx.contextlevel = :contextlevel
               AND r.shortname {$insql}";

    if (!$DB->record_exists_sql($sql, $params)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_mycoursesfilter'));
    }
}

/**
 * Fetches metadata required for filtering and sorting the selected courses.
 *
 * @param int[] $courseids Course ids.
 * @return array<int, array<string, int|bool>>
 */
function local_mycoursesfilter_get_meta_for_courses(array $courseids): array {
    global $DB, $USER;

    if (empty($courseids)) {
        return [];
    }

    $meta = [];
    foreach ($courseids as $courseid) {
        $meta[$courseid] = [
            'lastaccess' => 0,
            'lastenrolled' => 0,
            'completionenabled' => false,
            'iscompleted' => false,
            'timecompleted' => 0,
            'timestarted' => 0,
            'timeenrolled' => 0,
            'ishidden' => false,
            'isfavourite' => false,
        ];
    }

    [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
    $params['userid'] = $USER->id;

    $sql = "SELECT courseid, timeaccess
              FROM {user_lastaccess}
             WHERE userid = :userid
               AND courseid {$insql}";
    foreach ($DB->get_records_sql($sql, $params) as $record) {
        $meta[(int)$record->courseid]['lastaccess'] = (int)$record->timeaccess;
    }

    $sql = "SELECT e.courseid, MAX(ue.timecreated) AS lastenrolled
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
             WHERE ue.userid = :userid
               AND e.courseid {$insql}
          GROUP BY e.courseid";
    foreach ($DB->get_records_sql($sql, $params) as $record) {
        $meta[(int)$record->courseid]['lastenrolled'] = (int)$record->lastenrolled;
        $meta[(int)$record->courseid]['timeenrolled'] = (int)$record->lastenrolled;
    }

    $sql = "SELECT c.id, c.enablecompletion, cc.timecompleted, cc.timestarted
              FROM {course} c
         LEFT JOIN {course_completions} cc
                ON cc.course = c.id
               AND cc.userid = :userid
             WHERE c.id {$insql}";
    foreach ($DB->get_records_sql($sql, $params) as $record) {
        $courseid = (int)$record->id;
        $meta[$courseid]['completionenabled'] = !empty($record->enablecompletion);
        $meta[$courseid]['timecompleted'] = (int)($record->timecompleted ?? 0);
        $meta[$courseid]['timestarted'] = (int)($record->timestarted ?? 0);
        $meta[$courseid]['iscompleted'] = (int)($record->timecompleted ?? 0) > 0;
    }

    foreach ($courseids as $courseid) {
        $meta[$courseid]['ishidden'] = (int)get_user_preferences('block_myoverview_hidden_course_' . $courseid, 0, $USER) === 1;
    }

    $usercontext = context_user::instance($USER->id);
    $favouriteservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
    [$favsql, $favparams] = $favouriteservice->get_join_sql_by_type('core_course', 'courses', 'f', 'c.id');
    $favouritesql = "SELECT c.id, f.id AS favouriteid
                       FROM {course} c
                  {$favsql}
                      WHERE c.id {$insql}
                        AND f.id IS NOT NULL";
    foreach ($DB->get_records_sql($favouritesql, $params + $favparams) as $record) {
        $meta[(int)$record->id]['isfavourite'] = true;
    }

    return $meta;
}

/**
 * Checks whether the course matches the selected name query.
 *
 * @param stdClass $course The course record.
 * @param string $query The user query.
 * @return bool
 */
function local_mycoursesfilter_match_name(stdClass $course, string $query): bool {
    if ($query === '') {
        return true;
    }

    $needle = core_text::strtolower(trim($query));
    $fullname = core_text::strtolower((string)($course->fullname ?? ''));
    $shortname = core_text::strtolower((string)($course->shortname ?? ''));

    return (core_text::strpos($fullname, $needle) !== false) || (core_text::strpos($shortname, $needle) !== false);
}

/**
 * Checks whether the course matches one of the selected categories.
 *
 * @param stdClass $course The course record.
 * @param int[] $categoryids The selected category ids.
 * @return bool
 */
function local_mycoursesfilter_match_categories(stdClass $course, array $categoryids): bool {
    if ($categoryids === []) {
        return true;
    }

    return in_array((int)($course->category ?? 0), $categoryids, true);
}

/**
 * Checks whether the course matches the selected tag.
 *
 * The tag parameter can either be a numeric tag id or a tag name.
 *
 * @param int $courseid The course id.
 * @param string $tag The tag id or tag name.
 * @return bool
 */
function local_mycoursesfilter_match_tag(int $courseid, string $tag): bool {
    if ($tag === '') {
        return true;
    }

    if (ctype_digit($tag)) {
        foreach (core_tag_tag::get_item_tags('core', 'course', $courseid) as $coursetag) {
            if ((int)$coursetag->id === (int)$tag) {
                return true;
            }
        }

        return false;
    }

    return core_tag_tag::is_item_tagged_with('core', 'course', $courseid, $tag);
}

/**
 * Parses the combined customfield parameter.
 *
 * Supported forms are "shortname" and "shortname:value fragment".
 *
 * @param string $rawcustomfield Raw customfield parameter.
 * @return array{shortname: string, value: string}
 */
function local_mycoursesfilter_parse_customfield_param(string $rawcustomfield): array {
    $rawcustomfield = trim($rawcustomfield);
    if ($rawcustomfield === '') {
        return ['shortname' => '', 'value' => ''];
    }

    $parts = explode(':', $rawcustomfield, 2);
    $shortname = clean_param(trim($parts[0] ?? ''), PARAM_ALPHANUMEXT);
    if ($shortname === '') {
        return ['shortname' => '', 'value' => ''];
    }

    $value = '';
    if (array_key_exists(1, $parts)) {
        $value = clean_param(trim((string)$parts[1]), PARAM_TEXT);
    }

    return ['shortname' => $shortname, 'value' => $value];
}

/**
 * Builds a stable customfield request parameter.
 *
 * @param string $shortname The custom field shortname.
 * @param string $value The expected value fragment.
 * @return string
 */
function local_mycoursesfilter_build_customfield_param(string $shortname, string $value): string {
    $shortname = clean_param(trim($shortname), PARAM_ALPHANUMEXT);
    $value = clean_param(trim($value), PARAM_TEXT);

    if ($shortname === '') {
        return '';
    }

    if ($value === '') {
        return $shortname;
    }

    return $shortname . ':' . $value;
}

/**
 * Checks whether the course matches the selected custom field value.
 *
 * If the expected value is empty, any non-empty custom field value is accepted.
 * Otherwise the comparison uses a case-insensitive partial match.
 *
 * @param int $courseid The course id.
 * @param string $fieldshortname The custom field shortname.
 * @param string $expectedvalue The expected value fragment.
 * @return bool
 */
function local_mycoursesfilter_match_customfield(int $courseid, string $fieldshortname, string $expectedvalue): bool {
    if ($fieldshortname === '') {
        return true;
    }

    $handler = core_customfield\handler::get_handler('core_course', 'course');
    $fielddata = $handler->get_instance_data($courseid, true);

    foreach ($fielddata as $data) {
        $field = $data->get_field();
        if ($field->get('shortname') !== $fieldshortname) {
            continue;
        }

        $actualvalue = $data->export_value();
        if (is_array($actualvalue)) {
            $actualvalue = implode(', ', array_map('strval', $actualvalue));
        } else if (is_bool($actualvalue)) {
            $actualvalue = $actualvalue ? '1' : '0';
        } else if ($actualvalue === null) {
            $actualvalue = '';
        } else {
            $actualvalue = (string)$actualvalue;
        }

        $actualvalue = trim($actualvalue);
        if ($expectedvalue === '') {
            return $actualvalue !== '';
        }

        return core_text::strpos(
            core_text::strtolower($actualvalue),
            core_text::strtolower(trim($expectedvalue))
        ) !== false;
    }

    return false;
}

/**
 * Checks whether the course matches the selected filter bucket.
 *
 * @param array|null $meta The metadata for the course.
 * @param string $filter The selected filter.
 * @return bool
 */
function local_mycoursesfilter_match_filter(?array $meta, string $filter): bool {
    $meta = $meta ?? [];
    $ishidden = !empty($meta['ishidden']);
    $isfavourite = !empty($meta['isfavourite']);

    if ($filter === 'hidden') {
        return $ishidden;
    }

    if ($ishidden) {
        return false;
    }

    if ($filter === 'favourites') {
        return $isfavourite;
    }

    if ($filter === 'all') {
        return true;
    }

    return local_mycoursesfilter_match_status($meta, $filter);
}

/**
 * Checks whether the metadata matches the selected progress status.
 *
 * @param array|null $meta The metadata for the course.
 * @param string $status The selected status.
 * @return bool
 */
function local_mycoursesfilter_match_status(?array $meta, string $status): bool {
    if ($status === 'all') {
        return true;
    }

    $meta = $meta ?? [];

    if ($status === 'completed') {
        return local_mycoursesfilter_match_completed_status($meta);
    }

    if ($status === 'notstarted') {
        return local_mycoursesfilter_match_notstarted_status($meta);
    }

    if ($status === 'inprogress') {
        return local_mycoursesfilter_match_inprogress_status($meta);
    }

    return true;
}

/**
 * Returns whether completion metadata contains explicit completion tracking.
 *
 * @param array $meta Course metadata.
 * @return bool
 */
function local_mycoursesfilter_has_completion_record(array $meta): bool {
    return ((int)($meta['timecompleted'] ?? 0) > 0)
        || ((int)($meta['timestarted'] ?? 0) > 0)
        || ((int)($meta['timeenrolled'] ?? 0) > 0);
}

/**
 * Matches the completed status.
 *
 * @param array $meta Course metadata.
 * @return bool
 */
function local_mycoursesfilter_match_completed_status(array $meta): bool {
    $completionenabled = !empty($meta['completionenabled']);
    $hascompletionrecord = local_mycoursesfilter_has_completion_record($meta);

    if ($completionenabled && $hascompletionrecord) {
        return (int)($meta['timecompleted'] ?? 0) > 0;
    }

    if ($completionenabled) {
        return !empty($meta['iscompleted']);
    }

    return false;
}

/**
 * Matches the not started status.
 *
 * @param array $meta Course metadata.
 * @return bool
 */
function local_mycoursesfilter_match_notstarted_status(array $meta): bool {
    $completionenabled = !empty($meta['completionenabled']);
    $hascompletionrecord = local_mycoursesfilter_has_completion_record($meta);

    if ($completionenabled && $hascompletionrecord) {
        return ((int)($meta['timestarted'] ?? 0) === 0) && ((int)($meta['timecompleted'] ?? 0) === 0);
    }

    return ((int)($meta['lastaccess'] ?? 0) === 0) && empty($meta['iscompleted']);
}

/**
 * Matches the in-progress status.
 *
 * @param array $meta Course metadata.
 * @return bool
 */
function local_mycoursesfilter_match_inprogress_status(array $meta): bool {
    $completionenabled = !empty($meta['completionenabled']);
    $hascompletionrecord = local_mycoursesfilter_has_completion_record($meta);

    if ($completionenabled && $hascompletionrecord) {
        return ((int)($meta['timestarted'] ?? 0) > 0) && ((int)($meta['timecompleted'] ?? 0) === 0);
    }

    return ((int)($meta['lastaccess'] ?? 0) > 0) && empty($meta['iscompleted']);
}

/**
 * Builds dropdown item definitions for the toolbar.
 *
 * @param array $options Menu options keyed by parameter value.
 * @param string $paramname Parameter name to write.
 * @param string $currentvalue Current selected value.
 * @param array $baseparams The base URL parameters.
 * @return array<int, array<string, bool|string>>
 */
function local_mycoursesfilter_build_dropdown_items(
    array $options,
    string $paramname,
    string $currentvalue,
    array $baseparams
): array {
    $items = [];

    foreach ($options as $value => $label) {
        $params = $baseparams;
        $params[$paramname] = $value;
        $items[] = [
            'label' => $label,
            'url' => (new moodle_url('/local/mycoursesfilter/index.php', array_filter($params, static function ($item): bool {
                return $item !== '' && $item !== 0;
            })))->out(false),
            'active' => $value === $currentvalue,
        ];
    }

    return $items;
}

/**
 * Returns the currently active toolbar label.
 *
 * @param array $options Available option labels.
 * @param string $currentvalue The selected value.
 * @param string $default Default value.
 * @return string
 */
function local_mycoursesfilter_get_active_toolbar_label(array $options, string $currentvalue, string $default): string {
    if (!empty($options[$currentvalue])) {
        return $options[$currentvalue];
    }

    return $options[$default] ?? reset($options) ?: '';
}

/**
 * Builds hidden input definitions for the toolbar form.
 *
 * @param array $params Additional parameters to preserve.
 * @return array
 */
function local_mycoursesfilter_build_hidden_inputs(array $params): array {
    $inputs = [];

    foreach ($params as $name => $value) {
        if ($value === '' || $value === 0) {
            continue;
        }

        $inputs[] = [
            'name' => $name,
            'value' => (string)$value,
        ];
    }

    return $inputs;
}

/**
 * Builds the reset URL while preserving advanced integration parameters.
 *
 * @param array $params Parameters to preserve.
 * @return moodle_url
 */
function local_mycoursesfilter_build_reset_url(array $params): moodle_url {
    $cleanparams = [];

    foreach ($params as $name => $value) {
        if ($value === '' || $value === 0) {
            continue;
        }

        $cleanparams[$name] = $value;
    }

    return new moodle_url('/local/mycoursesfilter/index.php', $cleanparams);
}

/**
 * Returns the sort selector labels.
 *
 * @return array<string, string>
 */
function local_mycoursesfilter_get_sort_labels(): array {
    return [
        'lastaccess' => get_string('sort_lastaccess', 'local_mycoursesfilter'),
        'coursename' => get_string('sort_coursename', 'local_mycoursesfilter'),
        'shortname' => get_string('sort_shortname', 'local_mycoursesfilter'),
        'lastenrolled' => get_string('sort_lastenrolled', 'local_mycoursesfilter'),
    ];
}

/**
 * Returns the default sort order for the selected sort mode.
 *
 * @param string $sort The selected sort mode.
 * @return string
 */
function local_mycoursesfilter_get_default_sortorder(string $sort): string {
    if (in_array($sort, ['coursename', 'shortname'], true)) {
        return 'asc';
    }

    return 'desc';
}

/**
 * Returns the view selector labels.
 *
 * @return array<string, string>
 */
function local_mycoursesfilter_get_view_labels(): array {
    return [
        'card' => get_string('view_card', 'local_mycoursesfilter'),
        'list' => get_string('view_list', 'local_mycoursesfilter'),
        'summary' => get_string('view_summary', 'local_mycoursesfilter'),
    ];
}

/**
 * Exports the filtered courses as template context data.
 *
 * @param array $courses The filtered courses.
 * @param array $meta Metadata keyed by course id.
 * @return array
 */
function local_mycoursesfilter_export_course_cards_context(array $courses, array $meta = []): array {
    global $DB;

    if (empty($courses)) {
        return ['courses' => []];
    }

    $categoryids = [];
    foreach ($courses as $course) {
        $categoryid = (int)($course->category ?? 0);
        if ($categoryid > 0) {
            $categoryids[$categoryid] = $categoryid;
        }
    }

    $categorynames = [];
    if (!empty($categoryids)) {
        [$insql, $params] = $DB->get_in_or_equal(array_values($categoryids), SQL_PARAMS_NAMED, 'cat');
        $categorynames = $DB->get_records_select_menu('course_categories', "id {$insql}", $params, '', 'id, name');
    }

    $cards = [];
    foreach ($courses as $course) {
        $courseid = (int)$course->id;
        $coursecontext = context_course::instance($courseid);
        $categoryid = (int)($course->category ?? 0);
        $categoryname = '';

        if (!empty($categorynames[$categoryid])) {
            $categorycontext = context_coursecat::instance($categoryid);
            $categoryname = format_string((string)$categorynames[$categoryid], true, ['context' => $categorycontext]);
        }

        $summaryhtml = '';
        if (!empty($course->summary)) {
            $summaryhtml = format_text(
                $course->summary,
                $course->summaryformat ?? FORMAT_HTML,
                ['context' => $coursecontext, 'overflowdiv' => false]
            );
        }

        $cards[] = [
            'viewurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            'courseimage' => local_mycoursesfilter_get_course_image_url($course),
            'fullname' => format_string((string)$course->fullname, true, ['context' => $coursecontext]),
            'shortname' => format_string((string)($course->shortname ?? ''), true, ['context' => $coursecontext]),
            'showshortname' => !empty($course->shortname),
            'isfavourite' => !empty($meta[$courseid]['isfavourite']),
            'favouritelabel' => get_string('filter_favourites', 'local_mycoursesfilter'),
            'coursecategory' => $categoryname,
            'showcoursecategory' => $categoryname !== '',
            'summaryhtml' => $summaryhtml,
            'hassummary' => $summaryhtml !== '',
            'visible' => !empty($course->visible),
        ];
    }

    return ['courses' => $cards];
}

/**
 * Returns a course card image URL.
 *
 * The function prefers a course overview image and falls back to Moodle's
 * generated placeholder image when no overview image exists.
 *
 * @param stdClass $course The course record.
 * @return string
 */
function local_mycoursesfilter_get_course_image_url(stdClass $course): string {
    global $OUTPUT;

    $context = context_course::instance((int)$course->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder, filepath, filename', false);

    foreach ($files as $file) {
        if (!$file->is_valid_image()) {
            continue;
        }

        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    return $OUTPUT->get_generated_image_for_id((int)$course->id);
}
