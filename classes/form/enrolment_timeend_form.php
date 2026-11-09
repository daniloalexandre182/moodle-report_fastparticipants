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

namespace report_fastparticipants\form;

use core\context\course as context_course;
use core_form\dynamic_form;
use moodle_url;

/**
 * Modal form that edits only the end date ("Enrolment ends") of one user enrolment.
 *
 * The access rules are the ones of the core "Edit enrolment" action: the enrolment plugin must allow managing
 * user enrolments and the user needs enrol/<plugin>:manage, besides moodle/course:enrolreview and the report
 * capability. The change is saved through enrol_plugin::update_user_enrol(), so the user_enrolment_updated
 * event is triggered as in core.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_timeend_form extends dynamic_form {
    /** @var \stdClass|null The user enrolment being edited. */
    private ?\stdClass $ue = null;

    /** @var \stdClass|null The enrolment instance of the user enrolment. */
    private ?\stdClass $instance = null;

    /** @var \enrol_plugin|null The enrolment plugin of the instance. */
    private ?\enrol_plugin $plugin = null;

    /**
     * Loads (once) the user enrolment, its instance and its plugin from the ueid argument.
     *
     * @return void
     */
    private function load_user_enrolment(): void {
        global $DB;

        if ($this->ue !== null) {
            return;
        }
        $ueid = $this->optional_param('ueid', 0, PARAM_INT);
        $this->ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->instance = $DB->get_record('enrol', ['id' => $this->ue->enrolid], '*', MUST_EXIST);
        $this->plugin = enrol_get_plugin($this->instance->enrol) ?: null;
    }

    /**
     * Whether the current user can edit the end date of enrolments of the given instance (as core "Edit enrolment").
     *
     * @param \stdClass $instance Enrolment instance.
     * @param \enrol_plugin|null $plugin Its enrolment plugin.
     * @param \context $context Course context.
     * @return bool
     */
    public static function can_edit(\stdClass $instance, ?\enrol_plugin $plugin, \context $context): bool {
        if (!$plugin || !$plugin->allow_manage($instance)) {
            return false;
        }
        $capability = "enrol/{$instance->enrol}:manage";
        return get_capability_info($capability)
            && has_capability($capability, $context)
            && has_capability('moodle/course:enrolreview', $context)
            && has_capability('report/fastparticipants:view', $context);
    }

    /**
     * Returns the course context of the user enrolment.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $this->load_user_enrolment();
        return context_course::instance($this->instance->courseid);
    }

    /**
     * Checks that the current user can edit this user enrolment.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        $this->load_user_enrolment();
        $context = $this->get_context_for_dynamic_submission();
        require_capability('report/fastparticipants:view', $context);
        require_capability('moodle/course:enrolreview', $context);
        if (!self::can_edit($this->instance, $this->plugin, $context)) {
            throw new \required_capability_exception($context, "enrol/{$this->instance->enrol}:manage", 'nopermissions', '');
        }
    }

    /**
     * Defines the form: the enrolment method (read only) and the optional end date.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'ueid');
        $mform->setType('ueid', PARAM_INT);

        $this->load_user_enrolment();
        $instancename = $this->plugin ? $this->plugin->get_instance_name($this->instance) : $this->instance->enrol;
        $mform->addElement('static', 'enrolmentinstance', get_string('enrolmentmethod', 'enrol'), s($instancename));

        if (!empty($this->ue->timestart)) {
            $mform->addElement('static', 'timestartinfo', get_string('enroltimestart', 'enrol'), userdate($this->ue->timestart));
        }

        $mform->addElement('date_time_selector', 'timeend', get_string('enroltimeend', 'enrol'), ['optional' => true]);
        $mform->addHelpButton('timeend', 'edittimeend', 'report_fastparticipants');
    }

    /**
     * Validates that the end date is after the start date, as the core enrolment form.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $this->load_user_enrolment();
        if (!empty($data['timeend']) && !empty($this->ue->timestart) && $data['timeend'] <= $this->ue->timestart) {
            $errors['timeend'] = get_string('enroltimeendinvalid', 'enrol');
        }
        return $errors;
    }

    /**
     * Saves the new end date (0 removes it).
     *
     * @return array ['result' => true]
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $this->load_user_enrolment();
        $this->plugin->update_user_enrol($this->instance, $this->ue->userid, null, null, (int) ($data->timeend ?? 0));
        return ['result' => true];
    }

    /**
     * Loads the current end date into the form.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $this->load_user_enrolment();
        $this->set_data([
            'ueid' => $this->ue->id,
            'timeend' => (int) $this->ue->timeend,
        ]);
    }

    /**
     * Returns the URL of the report page of the course.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $this->load_user_enrolment();
        return new moodle_url('/report/fastparticipants/index.php', ['id' => $this->instance->courseid]);
    }
}
