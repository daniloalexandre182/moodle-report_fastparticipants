# Changelog

All notable changes to `report_fastparticipants` are documented in this file.

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
