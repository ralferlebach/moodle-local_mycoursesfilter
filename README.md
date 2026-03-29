# local_mycoursesfilter

`local_mycoursesfilter` is a Moodle local plugin that provides a filtered view of the current user's enrolled courses.

The plugin exposes a dedicated page at `/local/mycoursesfilter/index.php` and allows course cards to be filtered and sorted through URL parameters. It is designed for dashboard shortcuts, external portals, deep links, and other integrations where a pre-filtered “My courses” view is useful.

## Features

- Shows only courses in which the current user is enrolled.
- Supports filtering by:
  - free-text query (`fullname` and `shortname`)
  - course category
  - course tag
  - course custom field shortname and value
  - derived learning status (`any`, `notstarted`, `inprogress`, `completed`)
- Supports sorting by:
  - last access
  - course full name
  - course short name
  - latest enrolment time
- Preserves a safe local return URL so the user can navigate back to the calling page.
- Renders results using Moodle’s core course card renderer.

## Requirements

- Moodle 4.0 or later.
- PHP version compatible with the target Moodle release.

The current `version.php` declares:

- Component: `local_mycoursesfilter`
- Requires: `2022041900` (Moodle 4.0)

## Installation

1. Copy the plugin into the Moodle `local/mycoursesfilter` directory.
2. Visit `Site administration > Notifications` to complete the installation.
3. Optionally add links to the plugin page from custom navigation, dashboard blocks, external portals, or theme links.

## Usage

Base URL:

```text
/local/mycoursesfilter/index.php
```

### Supported URL parameters

| Parameter | Type | Description |
|---|---|---|
| `q` | text | Case-insensitive text match against course fullname and shortname. |
| `tag` | text/integer | Course tag name or numeric tag id. |
| `catid` | integer | Moodle course category id. Use `0` or omit for all categories. |
| `field` | text | Course custom field shortname. |
| `value` | text | Expected custom field value. If omitted while `field` is present, any non-empty value matches. |
| `status` | text | One of `any`, `notstarted`, `inprogress`, `completed`. |
| `sort` | text | One of `lastaccess`, `alpha`, `shortname`, `lastenrolled`. |
| `dir` | text | One of `asc`, `desc`. |
| `returnurl` | local URL | Optional local URL for the back button. |

### Example URLs

Filter by course name:

```text
/local/mycoursesfilter/index.php?q=biology
```

Filter by category and sort alphabetically:

```text
/local/mycoursesfilter/index.php?catid=3&sort=alpha&dir=asc
```

Filter by tag:

```text
/local/mycoursesfilter/index.php?tag=mandatory
```

Filter by custom field:

```text
/local/mycoursesfilter/index.php?field=deliverymode&value=online
```

Filter by progress status:

```text
/local/mycoursesfilter/index.php?status=inprogress
```

## Access model

The page requires an authenticated user.

In the current implementation, access is additionally restricted to users who hold at least one accepted course role. The shipped configuration checks for the Moodle role shortname `student`.

Site administrators bypass this role restriction.

If you need broader access, adjust the call to `local_mycoursesfilter_require_any_course_role()` in `index.php`.

## Status filtering logic

The plugin derives the status from Moodle course completion metadata and fallback activity signals:

- `completed`: completion is enabled and the course is marked complete.
- `notstarted`: no start/completion signal is present; otherwise falls back to “no recorded access”.
- `inprogress`: started but not completed; otherwise falls back to “has recorded access but not completed”.
- `any`: no status filtering.

Because Moodle sites vary in their completion configuration, status matching is best understood as a practical UI filter based on available completion and access data.

## Testing

This plugin now includes automated tests:

- PHPUnit tests for the main filtering helper functions.
- Behat acceptance tests for the course filtering page behaviour.

Typical local execution examples:

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit local/mycoursesfilter/tests/lib_test.php
```

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --config /path/to/behat/behat.yml local/mycoursesfilter/tests/behat/filter_courses.feature
```

## Development notes

- The plugin follows Moodle coding style and file boilerplate conventions.
- The plugin relies on core renderers and core APIs for enrolments, course completion, tags, and custom fields.
- The plugin stores no plugin-specific configuration or additional database tables.

## Limitations

- The page is implemented as a standalone endpoint and is not yet linked into Moodle navigation automatically.
- The role gate is hard-coded in `index.php` rather than being configurable in plugin settings.
- The plugin currently reads user/course metadata but does not yet include a Privacy API provider declaration.

## Roadmap ideas

- Add plugin settings for accepted roles and default sort order.
- Add navigation integration for dashboard or user menu entry points.
- Add dedicated capabilities instead of role-shortname checks.
- Add privacy metadata provider implementation.
- Add more acceptance coverage for tags, categories, and custom fields.

## Support

For production use, document your local conventions for:

- allowed roles
- expected custom field shortnames
- entry points that generate the filter URLs
- Moodle versions supported in your deployment

## License

GNU GPL v3 or later.
