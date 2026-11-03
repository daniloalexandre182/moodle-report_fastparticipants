# Changelog

All notable changes to `report_fastparticipants` are documented in this file.

## [1.0.0] - 2026-11-03 (version 2026110300)

### Added
- Course report page `report/fastparticipants/index.php?id=ID`: a fast replacement for the core participants page. It opens with
  only a filter form (no user query) and lists users after the search is submitted.
- Filters: name or identity field, role, group, enrolment status, enrolment method and last course access.
- Bulk actions, inline role and group editing, CSV/Excel export of the filtered list.
- Admin settings: replace the course "Participants" navigation item, maximum rows per page, maximum "select all" size.
- Capability `report/fastparticipants:view`, `report_viewed` event, privacy null provider.
- PHPUnit and Behat tests, GitHub Actions workflow using moodle-plugin-ci.
