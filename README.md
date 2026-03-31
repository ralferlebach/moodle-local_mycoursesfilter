# local_mycoursesfilter

**local_mycoursesfilter** is a Moodle plugin that provides a dedicated page displaying only the courses in which the currently logged-in user is enrolled. It offers filtering, sorting, and view customization options.

## Features

- Filter courses by status, favorites, or hidden courses.
- Search courses by name.
- Sort courses by name, last access, or enrollment date.
- Switch between card, list, or summary views.
- Optional "Back" button to return to the referring page.

## Installation

1. Copy the plugin to `local/mycoursesfilter`.
2. Go to **Site Administration → Notifications** to complete the installation.
3. Configure settings as needed.
4. Add links to `/local/mycoursesfilter/index.php` where required.

## URL Parameters

| Parameter      | Description                                                                 |
|----------------|-----------------------------------------------------------------------------|
| `coursename`   | Search for courses by name (partial match).                                |
| `filter`       | Filter courses (`all`, `notstarted`, `inprogress`, `completed`, `favourites`, `hidden`). |
| `sort`         | Sort courses (`lastaccess`, `coursename`, `shortname`, `lastenrolled`).    |
| `view`         | Display mode (`card`, `list`, `summary`).                                  |
| `returnurl`    | Optional return URL (e.g., `this` for the referring page).                 |

## Examples

- Filter by course name:  
  `/local/mycoursesfilter/index.php?coursename=biology`

- Show in-progress courses with a custom title:  
  `/local/mycoursesfilter/index.php?filter=inprogress&title=Current%20Courses`

- Return to the referring page:  
  `/local/mycoursesfilter/index.php?returnurl=this`

## Security

- URL parameters are validated to ensure safe usage.
- Only local return URLs are allowed.

## Copyright and Issue Tracker

- **Copyright**: © 2026 Ralf Erlebach.  
- **Issue Tracker**: [GitHub Repository](https://github.com/ralferlebach/moodle-local_mycoursesfilter/issues).
