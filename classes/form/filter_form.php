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
 * Filter form of the fast participants report.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Filter form (search, role, group, enrolment and last access filters) of the fast participants report.
 *
 * The form is submitted with GET so that the resulting URL can be bookmarked, paged and sorted.
 * The values are read back in index.php through {@see \report_fastparticipants\local\filter::from_request()}
 * and pushed into the form with set_data().
 *
 * Custom data (every option list comes from the permission aware builders of
 * {@see \report_fastparticipants\local\filter}; an empty list means the element is not added):
 *  - courseid (int) the course id;
 *  - searchfieldoptions (array) search field => label;
 *  - roleoptions (array) role id => label;
 *  - groupoptions (array) group id => label;
 *  - statusoptions (array) status => label;
 *  - enroloptions (array) enrolment instance id => label;
 *  - accesssinceoptions (array) value => label;
 *  - perpageoptions (array) rows per page => label.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];

        $mform->addElement('header', 'filterheader', get_string('filter'));
        $mform->setExpanded('filterheader', true);

        $mform->addElement('hidden', 'id', (int) ($customdata['courseid'] ?? 0));
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'run', 1);
        $mform->setType('run', PARAM_BOOL);

        // Text search: the searched field and the text, in one row.
        $searchfield = $mform->createElement(
            'select',
            'searchfield',
            get_string('searchfield', 'report_fastparticipants'),
            $customdata['searchfieldoptions'] ?? []
        );
        $searchfield->setHiddenLabel(true);
        $search = $mform->createElement('text', 'search', get_string('searchtext', 'report_fastparticipants'), [
            'size' => 30,
            'placeholder' => get_string('searchtext', 'report_fastparticipants'),
        ]);
        $search->setHiddenLabel(true);
        $mform->addGroup([$searchfield, $search], 'searchgroup', get_string('search'), ' ', false);
        $mform->setType('searchfield', PARAM_ALPHANUMEXT);
        $mform->setType('search', PARAM_TEXT);
        $mform->addHelpButton('searchgroup', 'searchfield', 'report_fastparticipants');

        // Filters: every select of the row has its own visible label.
        $filters = [];
        $filtertypes = [];
        $selects = [
            'roleid' => [get_string('role'), $customdata['roleoptions'] ?? []],
            'groupid' => [get_string('group'), $customdata['groupoptions'] ?? []],
            'status' => [get_string('participationstatus', 'enrol'), $customdata['statusoptions'] ?? []],
            'enrolid' => [get_string('enrolmentmethod', 'enrol'), $customdata['enroloptions'] ?? []],
            'accesssince' => [get_string('lastcourseaccess'), $customdata['accesssinceoptions'] ?? []],
        ];
        foreach ($selects as $name => [$label, $options]) {
            if (empty($options)) {
                continue;
            }
            $filters[] = $mform->createElement('select', $name, $label, $options);
            $filtertypes[] = $name;
        }
        if (!empty($filters)) {
            $mform->addGroup($filters, 'filtergroup', get_string('filters'), ' ', false);
            foreach ($filtertypes as $name) {
                $mform->setType($name, PARAM_INT);
            }
        }

        $mform->addElement('select', 'perpage', get_string('perpage'), $customdata['perpageoptions'] ?? []);
        $mform->setType('perpage', PARAM_INT);

        $mform->addElement('submit', 'submitbutton', get_string('search'));

        $mform->disable_form_change_checker();
    }

    /**
     * Removes the automatic sesskey and submission marker fields.
     *
     * This is a read-only GET form: keeping them would leak the sesskey into the URL (browser history,
     * server logs, shared links) and would make moodleform demand a sesskey when a bookmarked URL is opened.
     *
     * @return void
     */
    protected function after_definition() {
        parent::after_definition();
        $mform = $this->_form;
        foreach (['sesskey', '_qf__' . $this->_formname] as $elementname) {
            if ($mform->elementExists($elementname)) {
                $mform->removeElement($elementname);
            }
        }
    }
}
