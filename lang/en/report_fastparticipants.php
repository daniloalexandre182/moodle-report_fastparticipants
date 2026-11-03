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
 * English language strings for report_fastparticipants.
 *
 * @package    report_fastparticipants
 * @category   string
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesssince_never'] = 'Never accessed the course';
$string['anyenrolmethod'] = 'Any enrolment method';
$string['anylastaccess'] = 'Any time';
$string['anyrole'] = 'Any role';
$string['anystatus'] = 'Any status';
$string['enrolmentstatustext'] = '{$a->instance}: {$a->status}';
$string['eventreportviewed'] = 'Fast participants report viewed';
$string['fastparticipants:view'] = 'View fast participants report';
$string['maxperpage'] = 'Maximum rows per page';
$string['maxperpage_desc'] = 'Hard upper limit for the number of participants shown on a single page of the report.';
$string['maxselectall'] = 'Maximum users for "select all"';
$string['maxselectall_desc'] = 'Maximum number of matching users that can be selected at once with "Select all N users" for bulk actions.';
$string['notaccessedfor'] = 'Not accessed for {$a}';
$string['pluginname'] = 'Fast participants';
$string['privacy:metadata'] = 'The Fast participants report plugin does not store any personal data.';
$string['replaceparticipants'] = 'Replace the Participants page';
$string['replaceparticipants_desc'] = 'If enabled, the "Participants" item of the course navigation points to this report instead of the standard participants page.';
$string['searchfield'] = 'Search in';
$string['searchfield_help'] = 'Choose the field to search in and type part of its value. The search is not case sensitive and matches any part of the value. Leave the text empty to list every participant matching the other filters.';
$string['searchtext'] = 'Text to search';
$string['selectallmatching'] = 'Select all {$a} matching users';
$string['selectalltruncated'] = 'Only the first {$a->count} of {$a->total} matching users were selected (the limit is {$a->max}).';
$string['selectedallcount'] = 'All {$a} matching users selected (clear selection)';
$string['submittoview'] = 'Use the filters and click Search to list the participants. Leave the text empty to list everyone.';
