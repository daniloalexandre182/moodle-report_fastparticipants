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
 * Bulk operations of the enrolment plugins, started from the fast participants report.
 *
 * Faithful copy of the enrolment plugin branch of core user/action_redir.php. The only difference is that the
 * page URL (used as the action of the operation form) keeps the returnto parameter, so that after the operation
 * the user goes back to the report page they came from (core goes back to the participants page).
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

$formaction = required_param('formaction', PARAM_LOCALURL);
$id = required_param('id', PARAM_INT);
$defaultreturn = new moodle_url('/report/fastparticipants/index.php', ['id' => $id]);
$returnto = optional_param('returnto', $defaultreturn->out(false), PARAM_LOCALURL);

// Only the enrolment plugin bulk operations are handled here (the downloads go to core user/action_redir.php).
[$action] = explode('?', $formaction, 2);
if ($action !== 'bulkchange.php') {
    throw new \moodle_exception('unknownuseraction');
}

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
if ($course->id == SITEID) {
    throw new \moodle_exception('errorwithbulkoperation', 'enrol');
}
$context = context_course::instance($course->id);

$PAGE->set_url('/report/fastparticipants/bulkchange.php', [
    'formaction' => $formaction,
    'id' => $course->id,
    'returnto' => $returnto,
]);

require_login($course);
require_sesskey();
require_capability('report/fastparticipants:view', $context);
course_require_view_participants($context);

$PAGE->set_pagelayout('incourse');
$returnurl = new moodle_url($returnto);

// Get the enrolment plugin type and bulk operation from the url (as core).
$url = new moodle_url(required_param('formaction', PARAM_URL));
$plugin = clean_param((string) $url->param('plugin'), PARAM_PLUGIN);
$operationname = clean_param((string) $url->param('operation'), PARAM_ALPHANUMEXT);
if ($plugin === '' || $operationname === '') {
    throw new \moodle_exception('errorwithbulkoperation', 'enrol');
}

// Selected users: as core, from userid[], bulkuser[] (operation forms) or the user{ID} checkboxes.
$userids = optional_param_array('userid', [], PARAM_INT);
if (empty($userids)) {
    $userids = optional_param_array('bulkuser', [], PARAM_INT);
}
if (empty($userids) && ($post = data_submitted())) {
    foreach ($post as $k => $v) {
        if (preg_match('/^user(\d+)$/', $k, $m)) {
            $userids[] = (int) $m[1];
        }
    }
}
$userids = array_values(array_unique(array_filter(array_map('intval', $userids))));

$instances = enrol_get_instances($course->id, false);
$instance = false;
foreach ($instances as $oneinstance) {
    if ($oneinstance->enrol == $plugin) {
        $instance = $oneinstance;
        break;
    }
}
if (!$instance) {
    throw new \moodle_exception('errorwithbulkoperation', 'enrol');
}

$manager = new course_enrolment_manager($PAGE, $course, $instance->id);
$plugins = $manager->get_enrolment_plugins();

if (!isset($plugins[$plugin])) {
    throw new \moodle_exception('errorwithbulkoperation', 'enrol');
}

$plugin = $plugins[$plugin];

$operations = $plugin->get_bulk_operations($manager);

if (!isset($operations[$operationname])) {
    throw new \moodle_exception('errorwithbulkoperation', 'enrol');
}
$operation = $operations[$operationname];

if (empty($userids)) {
    redirect($returnurl, get_string('noselectedusers', 'bulkusers'));
}

$users = $manager->get_users_enrolments($userids);

$removed = array_diff($userids, array_keys($users));
if (!empty($removed)) {
    // This manager does not filter by enrolment method - so we can get the removed users details.
    $removedmanager = new course_enrolment_manager($PAGE, $course);
    $removedusers = $removedmanager->get_users_enrolments($removed);

    foreach ($removedusers as $removeduser) {
        $msg = get_string('userremovedfromselectiona', 'enrol', fullname($removeduser));
        \core\notification::warning($msg);
    }
}

// We may have users from any kind of enrolment, we need to filter for the enrolment plugin matching the bulk action.
$matchesplugin = function ($user) use ($plugin) {
    foreach ($user->enrolments as $enrolment) {
        if ($enrolment->enrolmentplugin->get_name() == $plugin->get_name()) {
            return true;
        }
    }
    return false;
};
$filteredusers = array_filter($users, $matchesplugin);

// If the bulk operation is deleting enrolments, we exclude in any case the current user as it was probably a mistake.
if ($operationname === 'deleteselectedusers' && array_key_exists($USER->id, $filteredusers)) {
    \core\notification::warning(get_string('userremovedfromselectiona', 'enrol', fullname($USER)));
    unset($filteredusers[$USER->id]);
}

if (empty($filteredusers)) {
    redirect($returnurl, get_string('noselectedusers', 'bulkusers'));
}

$users = $filteredusers;

// Get the form for the bulk operation (it posts back to this page, keeping returnto).
$mform = $operation->get_form($PAGE->url, ['users' => $users]);
// If the mform is false then attempt an immediate process. This may be an immediate action that
// doesn't require user input OR confirmation.
if ($mform === false) {
    if ($operation->process($manager, $users, new stdClass())) {
        redirect($returnurl);
    } else {
        throw new \moodle_exception('errorwithbulkoperation', 'enrol');
    }
}
// Check if the bulk operation has been cancelled.
if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($mform->is_submitted() && $mform->is_validated() && confirm_sesskey()) {
    if ($operation->process($manager, $users, $mform->get_data())) {
        redirect($returnurl);
    }
}

$pagetitle = get_string('bulkuseroperation', 'enrol');

$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
echo $OUTPUT->header();
echo $OUTPUT->heading($operation->get_title());
$mform->display();
echo $OUTPUT->footer();
