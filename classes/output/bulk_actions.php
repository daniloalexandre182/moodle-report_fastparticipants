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
 * Bulk actions bar ("With selected users...") of the fast participants report.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\output;

use moodle_url;
use renderer_base;

/**
 * Bulk actions bar ("With selected users...") of the fast participants report.
 *
 * Renders the bulk action select, the "select all N matching users" button, the region where the JS adds the
 * users selected with that button and the note state help icon, as described by the DOM contract. The options
 * mirror the core participants page (user/index.php):
 *  - communication: send a message and add a note (handled in JS by the core bulk action modals);
 *  - download of the selected users with each enabled dataformat (core user/action_redir.php);
 *  - bulk operations of the enrolment plugins (this plugin's bulkchange.php, which keeps the return URL).
 *
 * Must be rendered inside the #report-fastparticipants-bulkform form.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_actions implements \renderable, \templatable {
    /** @var \stdClass The course. */
    protected \stdClass $course;

    /** @var \context_course The course context. */
    protected \context_course $context;

    /** @var int Total number of matching users. */
    protected int $total;

    /** @var int Number of users displayed on the current page. */
    protected int $displayed;

    /** @var int Maximum number of users that can be selected with "select all". */
    protected int $maxselectall;

    /**
     * Constructor.
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @param int $total Total number of matching users.
     * @param int $displayed Number of users displayed on the current page.
     * @param int $maxselectall Maximum number of users that can be selected with "select all".
     */
    public function __construct(\stdClass $course, \context_course $context, int $total, int $displayed, int $maxselectall) {
        $this->course = $course;
        $this->context = $context;
        $this->total = $total;
        $this->displayed = $displayed;
        $this->maxselectall = $maxselectall;
    }

    /**
     * Exports the data for the template.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $optgroups = array_values(array_filter([
            $this->get_communication_options(),
            $this->get_download_options(),
        ]));
        $optgroups = array_merge($optgroups, $this->get_enrolment_options());

        return [
            'total' => $this->total,
            'showselectall' => $this->total > $this->displayed && $this->maxselectall > 0 && $this->total <= $this->maxselectall,
            'optgroups' => $optgroups,
            'hasoptions' => !empty($optgroups),
            'statehelpicon' => $output->help_icon('publishstate', 'notes'),
        ];
    }

    /**
     * Returns the communication options (send a message, add a note).
     *
     * @return array|null Option group, null when empty.
     */
    protected function get_communication_options(): ?array {
        global $CFG;

        $options = [];
        if (
            !empty($CFG->messaging) &&
            has_all_capabilities(['moodle/site:sendmessage', 'moodle/course:bulkmessaging'], $this->context)
        ) {
            $options[] = ['value' => '#messageselect', 'label' => get_string('messageselectadd')];
        }
        if (!empty($CFG->enablenotes) && has_capability('moodle/notes:manage', $this->context)) {
            $options[] = ['value' => '#addgroupnote', 'label' => get_string('addnewnote', 'notes')];
        }
        if (empty($options)) {
            return null;
        }
        return ['label' => get_string('communication', 'communication'), 'options' => $options];
    }

    /**
     * Returns the download options (one per enabled dataformat), handled by core user/action_redir.php.
     *
     * @return array|null Option group, null when empty.
     */
    protected function get_download_options(): ?array {
        global $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        if (!course_can_view_participants($this->context)) {
            return null;
        }

        $endpoint = (new moodle_url('/user/action_redir.php'))->out(false);
        $options = [];
        foreach (\core_plugin_manager::instance()->get_plugins_of_type('dataformat') as $format) {
            if (!$format->is_enabled()) {
                continue;
            }
            $url = new moodle_url('bulkchange.php', ['operation' => 'download_participants', 'dataformat' => $format->name]);
            $options[] = [
                'value' => $url->out(false),
                'label' => get_string('dataformat', $format->component),
                'endpoint' => $endpoint,
            ];
        }
        if (empty($options)) {
            return null;
        }
        return ['label' => get_string('downloadas', 'table'), 'options' => $options];
    }

    /**
     * Returns the bulk operations of the enrolment plugins, handled by this plugin's bulkchange.php.
     *
     * As in core, there is one group per enrolment plugin (the operations apply to the plugin, not to an instance).
     *
     * @return array[] Option groups.
     */
    protected function get_enrolment_options(): array {
        global $CFG, $PAGE;

        if (!has_capability('moodle/course:enrolreview', $this->context)) {
            return [];
        }

        require_once($CFG->dirroot . '/enrol/locallib.php');
        $manager = new \course_enrolment_manager($PAGE, $this->course);
        $plugins = $manager->get_enrolment_plugins(false);
        $endpoint = (new moodle_url('/report/fastparticipants/bulkchange.php'))->out(false);

        $groups = [];
        foreach ($manager->get_enrolment_instances() as $instance) {
            if (!isset($plugins[$instance->enrol]) || isset($groups[$instance->enrol])) {
                continue;
            }
            $plugin = $plugins[$instance->enrol];
            $options = [];
            foreach ($plugin->get_bulk_operations($manager) as $key => $operation) {
                $url = new moodle_url('bulkchange.php', ['plugin' => $plugin->get_name(), 'operation' => $key]);
                $options[] = [
                    'value' => $url->out(false),
                    'label' => $operation->get_title(),
                    'endpoint' => $endpoint,
                ];
            }
            if (!empty($options)) {
                $groups[$instance->enrol] = [
                    'label' => get_string('pluginname', 'enrol_' . $plugin->get_name()),
                    'options' => $options,
                ];
            }
        }
        return array_values($groups);
    }
}
