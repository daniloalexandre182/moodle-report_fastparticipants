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
 * Edition of the enrolment end date ("Enrolment ends" column) of the fast participants report.
 *
 * Opens the report_fastparticipants\form\enrolment_timeend_form modal form and, once saved, reloads the page
 * showing a confirmation toast (as the status field).
 *
 * @module     report_fastparticipants/timeend_field
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';
import {reloadWithToast} from 'report_fastparticipants/status_field';

const Selectors = {
    editLink: '[data-action="report_fastparticipants/edittimeend"]',
};

/** @type {Boolean} Whether the listener is already registered. */
let registered = false;

/**
 * Open the end date modal form for the user enrolment of the given link.
 *
 * @param {HTMLElement} link
 */
const showForm = link => {
    const fullname = link.dataset.fullname;
    const modalForm = new ModalForm({
        formClass: 'report_fastparticipants\\form\\enrolment_timeend_form',
        args: {ueid: link.dataset.ueid},
        modalConfig: {
            title: getString('edituserenrolment', 'core_enrol', fullname),
        },
        returnFocus: link,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => {
        reloadWithToast(getString('enrolmentupdatedforuser', 'core_enrol', {fullname}));
    });

    modalForm.show();
};

/**
 * Initialise the end date edit links.
 */
export const init = () => {
    if (registered) {
        return;
    }
    registered = true;

    document.addEventListener('click', e => {
        const link = e.target.closest(Selectors.editLink);
        if (!link) {
            return;
        }
        e.preventDefault();
        showForm(link);
    });
};
