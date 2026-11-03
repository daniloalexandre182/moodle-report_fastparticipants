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
 * Fast participants report: a lighter course participants page that lists users only after a search.
 *
 * Without the run parameter only the filter form is displayed and no user is queried at all.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\report_helper;
use report_fastparticipants\form\filter_form;
use report_fastparticipants\local\exporter;
use report_fastparticipants\local\filter;
use report_fastparticipants\local\participants_search;
use report_fastparticipants\local\row_loader;
use report_fastparticipants\output\bulk_actions;
use report_fastparticipants\output\participants_action_bar;
use report_fastparticipants\table\participants_table;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/notes/lib.php');

$courseid = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

// The site participants are not a course list: use the core page.
if ($courseid == SITEID) {
    redirect(new moodle_url('/user/index.php', ['id' => SITEID]));
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

$PAGE->set_url(new moodle_url('/report/fastparticipants/index.php', ['id' => $course->id]));

require_login($course);
require_capability('report/fastparticipants:view', $context);
course_require_view_participants($context);

// Permission checked filter.
$filter = filter::from_request()->normalise($course, $context);

// Base URL of the table (filter only) and page URL (filter, sort and page), also used as the bulk actions return URL.
$baseurl = new moodle_url('/report/fastparticipants/index.php', ['id' => $course->id] + $filter->to_url_params());
$pageurl = new moodle_url($baseurl);
$tsort = optional_param('tsort', '', PARAM_ALPHANUMEXT);
if ($tsort !== '') {
    $pageurl->params(['tsort' => $tsort, 'tdir' => optional_param('tdir', SORT_ASC, PARAM_INT)]);
}
$page = optional_param('page', 0, PARAM_INT);
if ($page > 0) {
    $pageurl->param('page', $page);
}
$PAGE->set_url($pageurl);

// With the replaceparticipants setting the report is the course "Participants" page, otherwise a course report.
$replace = (bool) get_config('report_fastparticipants', 'replaceparticipants');
$pluginname = get_string('pluginname', 'report_fastparticipants');
$PAGE->set_pagelayout($replace ? 'incourse' : 'report');
$PAGE->set_title(format_string($course->shortname, true, ['context' => $context]) . ': ' .
    ($replace ? get_string('participants') : $pluginname));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
if ($replace) {
    $PAGE->set_secondary_active_tab('participants');
}

// Filter form (GET).
$filterform = new filter_form(new moodle_url('/report/fastparticipants/index.php'), [
    'courseid' => $course->id,
    'searchfieldoptions' => filter::get_searchfield_options($context),
    'roleoptions' => filter::get_role_options($context),
    'groupoptions' => filter::get_group_options($course, $context),
    'statusoptions' => filter::get_status_options($context),
    'enroloptions' => filter::get_enrol_options($course, $context),
    'accesssinceoptions' => filter::get_accesssince_options($context),
    'perpageoptions' => filter::get_perpage_options(),
], 'get', '', ['id' => 'report-fastparticipants-filter']);
$filterform->set_data([
    'id' => $course->id,
    'run' => 1,
    'searchfield' => $filter->get_searchfield(),
    'search' => $filter->get_search(),
    'roleid' => $filter->get_roleid(),
    'groupid' => $filter->get_groupid(),
    'status' => $filter->get_status(),
    'enrolid' => $filter->get_enrolid(),
    'accesssince' => $filter->get_accesssince(),
    'perpage' => $filter->get_perpage(),
]);

$search = null;
$table = null;
if ($filter->is_run() && !$filter->is_notingroup()) {
    $search = new participants_search($course, $context, $filter);
    $table = new participants_table($course, $context, $filter, $search, new row_loader($course, $context), $baseurl);

    // Export of the whole filtered list: streams the file and stops before any output.
    if ($download !== '') {
        \report_fastparticipants\event\report_viewed::create(['context' => $context])->trigger();
        $exporter = new exporter($course, $context, $search, $table->get_current_sort_columns());
        $exporter->download($download);
        exit;
    }
}

// Link to the manual enrolment page (the core quick enrolment button needs the core dynamic table).
$enrolbutton = '';
$manual = enrol_get_plugin('manual');
if ($manual && has_capability('enrol/manual:enrol', $context)) {
    foreach (enrol_get_instances($course->id, true) as $instance) {
        if ($instance->enrol !== 'manual') {
            continue;
        }
        $link = $manual->get_manual_enrol_link($instance);
        if ($link) {
            $button = new single_button($link, get_string('enrolusers', 'enrol_manual'), 'get', single_button::BUTTON_PRIMARY);
            $enrolbutton = $OUTPUT->render($button);
        }
        break;
    }
}

echo $OUTPUT->header();

if ($replace) {
    // The course "Participants" tertiary navigation, with this report instead of "Enrolled users".
    $actionbar = new participants_action_bar($course, $PAGE, $enrolbutton !== '' ? $enrolbutton : null);
    echo $OUTPUT->render_from_template('core_course/participants_actionbar', $actionbar->export_for_template($OUTPUT));
} else {
    report_helper::print_report_selector($pluginname, $enrolbutton);
}
$filterform->display();

if (!$filter->is_run()) {
    // Nothing has been searched yet: only show the filter, do not load any user.
    echo $OUTPUT->notification(get_string('submittoview', 'report_fastparticipants'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

\report_fastparticipants\event\report_viewed::create(['context' => $context])->trigger();

if ($filter->is_notingroup()) {
    // Separate groups and the user is not in any group: nobody can be listed (as core).
    echo $OUTPUT->notification(get_string('notingroup'));
    echo $OUTPUT->footer();
    exit;
}

// Render the table first to know the total.
$tablehtml = $table->render($filter->get_perpage());
$total = $table->get_total();

echo html_writer::start_div('userlist report-fastparticipants-results');

echo html_writer::start_tag('form', [
    'id' => 'report-fastparticipants-bulkform',
    'method' => 'post',
    'action' => (new moodle_url('/user/action_redir.php'))->out(false),
    'data-course-id' => $course->id,
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $course->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'returnto', 'value' => $PAGE->url->out(false)]);

echo html_writer::tag('p', get_string('countparticipantsfound', 'core_user', $total), [
    'data-region' => 'participant-count',
]);

echo $tablehtml;

$maxselectall = filter::get_max_selectall();
if ($total > 0) {
    $bulkactions = new bulk_actions($course, $context, $total, $table->get_displayed_count(), $maxselectall);
    echo $OUTPUT->render_from_template('report_fastparticipants/bulk_actions', $bulkactions->export_for_template($OUTPUT));
}

echo html_writer::end_tag('form');

if ($total > 0) {
    // Download of the whole filtered list (outside the bulk form, forms can not be nested).
    echo $OUTPUT->download_dataformat_selector(
        get_string('downloadas', 'table'),
        new moodle_url('/report/fastparticipants/index.php'),
        'download',
        ['id' => $course->id] + $filter->to_url_params()
    );

    $PAGE->requires->js_call_amd('report_fastparticipants/participants', 'init', [[
        'courseid' => (int) $course->id,
        'contextid' => (int) $context->id,
        'uniqueid' => $table->uniqueid,
        'noteStateNames' => note_get_state_names(),
        'filter' => $filter->to_url_params(),
        'maxselectall' => $maxselectall,
    ]]);
}

// Outside the total check so a toast stored after unenrolling the last matching user is shown (and cleared) now.
if (has_capability('moodle/course:enrolreview', $context)) {
    $PAGE->requires->js_call_amd('report_fastparticipants/status_field', 'init', [['uniqueid' => $table->uniqueid]]);
}

echo html_writer::end_div();

echo $OUTPUT->footer();
