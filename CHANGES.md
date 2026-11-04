# Changelog

All notable changes to `report_fastparticipants` are documented in this file.

## [1.1.0] - 2026-11-04 (version 2026110400)

### Changed
- Moodle 5.2 support (branch `MOODLE_502_STABLE`, requires 5.2, PHP 8.3 – 8.4). The 4.5 version stays on
  `MOODLE_405_STABLE`.
- Row checkboxes are looked up with `data-toggle="target"` and the `checkboxToggled` event is read through
  `targets`/`checkedTargets`, as in `core/checkbox-toggleall` since Moodle 5.0. With the old `slave` names no
  user was ever selected and unchecking a row after "Select all" raised a JavaScript error.
- Bootstrap 5 classes: `visually-hidden`, `me-*` and `form-select` instead of `sr-only`, `mr-*` and `custom-select`.
- The full name column shows the "Suspended" badge of suspended accounts, as the core participants page does in 5.x.
- GitHub Actions matrix moved to `MOODLE_502_STABLE` (PHP 8.3/PostgreSQL 16 and PHP 8.4/MariaDB).

## [1.0.0] - 2026-11-03 (version 2026110301)

### Added
- Course report page `report/fastparticipants/index.php?id=ID`: a fast replacement for the core participants page. It opens with
  only a filter form (no user query) and lists users after the search is submitted.
- Filters: name or identity field, role, group, enrolment status, enrolment method and last course access.
- Lean SQL: semijoins instead of DISTINCT derived tables, a cheap COUNT, page ids first and a deferred join of the
  user fields; roles, groups and enrolments of the page rows are batch loaded with a constant number of queries.
- Bulk actions (send message, add note, download selected users, enrolment method bulk operations returning to
  the report) and "Select all N matching users" through the `report_fastparticipants_get_matching_userids` web service.
- Inline role and group editing, enrolment status details/edit/unenrol.
- Export of the whole filtered list in any dataformat (CSV, Excel, ODS, JSON, HTML, PDF), streamed in chunks.
- Admin settings: replace the course "Participants" navigation item, maximum rows per page, maximum "select all" size.
- English and Brazilian Portuguese language packs.
- Capability `report/fastparticipants:view`, `report_viewed` event, privacy null provider.
- PHPUnit and Behat tests, GitHub Actions workflow using moodle-plugin-ci.
