# MyCourses (Filtered)

**MyCourses (Filtered)** is a Moodle plugin that provides a dedicated page displaying only the courses in which the currently logged-in user is enrolled.

It offers filtering, sorting, and view customization options by url, so you may provide your students with a specifiv dashboard of their learning materials in a specific section in your Moodle instance.

## Features

- Filter and sort courses to whoch the user is enrolled in by url request.
- Optional "Back" button to return to the referring page.
- Additional Search and Sort function for the user as well as Switching between card, list, or summary view.

## Installation

1. Copy the plugin to `local/mycoursesfilter`.
2. Go to **Site Administration → Notifications** to complete the installation.
3. Configure settings as needed.
4. Add links to `/local/mycoursesfilter/index.php` where required.

## URL Parameters

| Parameter      | Description                                                                 |
|----------------|-----------------------------------------------------------------------------|
| `coursename`   | Search for courses by full name or short name (partial match).              |
| `tag`          | Filter courses by a single tag name (exact match).                          |
| `catid`        | Restrict to one or more course categories. Accepts a single ID, a comma-separated list, or the contextual tokens `this`, `parent`, and `children` (e.g. `catid=3,7,12`, `catid=this,children`, `catid=parent`). Contextual tokens require an implicit source course context — see the dedicated section below for how that is resolved. |
| `customfield`  | Filter by a course custom field in the form `shortname:value` (e.g. `customfield=department:science`). |
| `courseid`     | Use the given course as the source context (e.g. to derive the category scope). Accepts an integer course ID or the literal `last`. When set to `last`, the source context is resolved exclusively from the current user's most recently accessed enrolment (via `enrol_get_my_courses()`), bypassing referer and session hints. |
| `only`         | When set to `1`, limits results to the resolved category only and ignores enrolments outside it. |
| `recursive`    | When set to `1`, includes courses from child categories of the resolved category. |
| `filter`       | Filter courses by status: `all`, `notstarted`, `inprogress`, `completed`, `favourites`, `hidden`. |
| `sort`         | Sort field: `lastaccess`, `coursename`, `shortname`, `lastenrolled`.        |
| `sortorder`    | Sort direction: `asc` or `desc`. Defaults to a sensible value per `sort` field. |
| `view`         | Display mode: `card`, `list`, `summary`.                                    |
| `title`        | Override the page title and heading (requires the title override to be enabled in plugin settings). |
| `returnurl`    | Optional return URL. Accepts `this` for the referring page or a local Moodle URL. The return URL is also used as a secondary source for implicit source-course resolution when the HTTP referer is unavailable. |

### Implicit source-course resolution for `catid=this|parent|children`

The contextual `catid` tokens need a source course to resolve against. The plugin tries these sources in order and stops at the first one that yields an accessible course:

1. Explicit `courseid=<id>` URL parameter.
2. HTTP referer, dispatched on: `/course/view.php?id=…`, `/course/section.php?id=…`, `/mod/<modname>/…` (carrying `cmid` or `id` — the `id` parameter is tried as a course module id first and then as an activity instance id), `/blocks/<blockname>/…` (carrying `blockid`, `bi`, or `instanceid` — resolved via `block_instances.parentcontextid`), `/user/profile.php?course=…`, or any same-site URL exposing a numeric `courseid` query parameter.
3. The plugin's own `returnurl` parameter, dispatched through the same rules.
4. Session hints (`$SESSION->lastcourseaccessed`, `$SESSION->currentcourseid`).

If a `/mod/<modname>/…?id=N` URL yields two different courses (once via the cmid lookup and once via the activity instance lookup), the plugin treats this as ambiguous and falls back to the user's most recently accessed enrolment rather than guessing.

Opt-in: `courseid=last` forces the last-accessed-enrolment heuristic and skips all other sources. This is useful for links from external pages where the referer is unreliable.

## Examples

- Filter by course name:  
  `/local/mycoursesfilter/index.php?coursename=biology`

- Show in-progress courses with a custom title:  
  `/local/mycoursesfilter/index.php?filter=inprogress&title=Current%20Courses`

- Return to the referring page:  
  `/local/mycoursesfilter/index.php?returnurl=this`

## Security

- URL parameters are validated to ensure safe usage, only local return URLs are allowed.

## Copyright and Issue Tracker

- **Copyright**: © 2026 Ralf Erlebach.  
- **Issue Tracker**: [GitHub Repository](https://github.com/ralferlebach/moodle-local_mycoursesfilter/issues).
