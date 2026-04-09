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
| `catid`        | Restrict to one or more course categories. Accepts a single ID, a comma-separated list, or the contextual tokens `this`, `parent`, and `children` (e.g. `catid=3,7,12`, `catid=this,children`, `catid=parent`). The contextual tokens require a source course context, which is taken from the `courseid` parameter or, if absent, derived from the HTTP referer when it points to `/course/view.php?id=…` on the same site. |
| `customfield`  | Filter by a course custom field in the form `shortname:value` (e.g. `customfield=department:science`). |
| `courseid`     | Use the given course as the source context (e.g. to derive the category scope). Integer course ID. |
| `only`         | When set to `1`, limits results to the resolved category only and ignores enrolments outside it. |
| `recursive`    | When set to `1`, includes courses from child categories of the resolved category. |
| `filter`       | Filter courses by status: `all`, `notstarted`, `inprogress`, `completed`, `favourites`, `hidden`. |
| `sort`         | Sort field: `lastaccess`, `coursename`, `shortname`, `lastenrolled`.        |
| `sortorder`    | Sort direction: `asc` or `desc`. Defaults to a sensible value per `sort` field. |
| `view`         | Display mode: `card`, `list`, `summary`.                                    |
| `title`        | Override the page title and heading (requires the title override to be enabled in plugin settings). |
| `returnurl`    | Optional return URL. Accepts `this` for the referring page or a local Moodle URL. |

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
