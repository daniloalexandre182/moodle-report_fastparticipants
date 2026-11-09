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
 * User enrolment status field handling for the fast participants report.
 *
 * Port of core_user/status_field for a server rendered (non dynamic) table: after a change the page is reloaded
 * and the confirmation toast is shown once the page has loaded again.
 *
 * @module     report_fastparticipants/status_field
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from 'core_user/repository';
import * as Str from 'core/str';
import Fragment from 'core/fragment';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {add as notifyUser} from 'core/toast';
import SaveCancelModal from 'core/modal_save_cancel';
import CancelModal from 'core/modal_cancel';

/** @type {String} Session storage key of the toast message to show after reloading the page. */
const TOAST_STORAGE_KEY = 'report_fastparticipants/toast';

const Selectors = {
    editEnrolment: '[data-action="editenrolment"]',
    showDetails: '[data-action="showdetails"]',
    unenrol: '[data-action="unenrol"]',
    statusElement: '[data-status]',
    tableRoot: uniqueId => `[data-region="report_fastparticipants/table"][data-table-uniqueid="${uniqueId}"]`,
};

/**
 * Get the status container from the specified link.
 *
 * @param {HTMLElement} link
 * @returns {HTMLElement}
 */
const getStatusContainer = link => link.closest(Selectors.statusElement);

/**
 * Get user enrolment id from the specified link.
 *
 * @param {HTMLElement} link
 * @returns {Number}
 */
const getUserEnrolmentIdFromLink = link => link.getAttribute('rel');

/**
 * Store a message to be shown as a toast after the page is reloaded.
 *
 * @param {String} message
 */
const storeToast = message => {
    try {
        window.sessionStorage.setItem(TOAST_STORAGE_KEY, message);
    } catch (e) {
        // Session storage not available: the message is simply not shown.
    }
};

/**
 * Show (and forget) the toast message stored before the page was reloaded, if any.
 */
const showStoredToast = () => {
    let message = null;
    try {
        message = window.sessionStorage.getItem(TOAST_STORAGE_KEY);
        window.sessionStorage.removeItem(TOAST_STORAGE_KEY);
    } catch (e) {
        // Session storage not available.
        message = null;
    }

    if (message) {
        notifyUser(message);
    }
};

/**
 * Reload the page, showing the given message as a toast once reloaded.
 *
 * @param {Promise<String>} messagePromise
 * @returns {Promise}
 */
export const reloadWithToast = messagePromise => messagePromise
    .catch(() => null)
    .then(message => {
        if (message) {
            storeToast(message);
        }
        window.location.reload();

        return message;
    });

/**
 * Register all event listeners for the status fields.
 *
 * @param {String} uniqueId
 */
const registerEventListeners = uniqueId => {
    document.addEventListener('click', e => {
        const tableRoot = e.target.closest(Selectors.tableRoot(uniqueId));
        if (!tableRoot) {
            return;
        }

        const contextId = parseInt(tableRoot.dataset.contextId, 10);
        const getBodyFunction = (userEnrolmentId, formData) => getBody(contextId, userEnrolmentId, formData);

        const editLink = e.target.closest(Selectors.editEnrolment);
        if (editLink) {
            e.preventDefault();

            showEditDialogue(editLink, getBodyFunction);
        }

        const unenrolLink = e.target.closest(Selectors.unenrol);
        if (unenrolLink) {
            e.preventDefault();

            showUnenrolConfirmation(unenrolLink);
        }

        const showDetailsLink = e.target.closest(Selectors.showDetails);
        if (showDetailsLink) {
            e.preventDefault();

            showStatusDetails(showDetailsLink);
        }
    });
};

/**
 * Show the edit dialogue.
 *
 * @param {HTMLElement} link
 * @param {Function} getBody Function to get the body for the specified user enrolment
 */
const showEditDialogue = (link, getBody) => {
    const container = getStatusContainer(link);
    const userEnrolmentId = getUserEnrolmentIdFromLink(link);

    SaveCancelModal.create({
        large: true,
        title: Str.get_string('edituserenrolment', 'enrol', container.dataset.fullname),
        body: getBody(userEnrolmentId)
    })
    .then(modal => {
        // Handle save event.
        modal.getRoot().on(ModalEvents.save, e => {
            // Don't close the modal yet.
            e.preventDefault();

            // Submit form data.
            submitEditFormAjax(getBody, modal, userEnrolmentId, container.dataset);
        });

        // Handle hidden event.
        modal.getRoot().on(ModalEvents.hidden, () => {
            // Destroy when hidden.
            modal.destroy();
        });

        // Show the modal.
        modal.show();

        return modal;
    })
    .catch(Notification.exception);
};

/**
 * Show and handle the unenrolment confirmation dialogue.
 *
 * @param {HTMLElement} link
 */
const showUnenrolConfirmation = link => {
    const container = getStatusContainer(link);
    const userEnrolmentId = getUserEnrolmentIdFromLink(link);

    SaveCancelModal.create()
    .then(modal => {
        // Handle confirm event.
        modal.getRoot().on(ModalEvents.save, e => {
            // Don't close the modal yet.
            e.preventDefault();

            // Submit data.
            submitUnenrolFormAjax(
                modal,
                {
                    ueid: userEnrolmentId,
                },
                container.dataset
            );
        });

        // Handle hidden event.
        modal.getRoot().on(ModalEvents.hidden, () => {
            // Destroy when hidden.
            modal.destroy();
        });

        // Display the delete confirmation modal.
        modal.show();

        const stringData = [
            {
                key: 'unenrol',
                component: 'enrol',
            },
            {
                key: 'unenrolconfirm',
                component: 'enrol',
                param: {
                    user: container.dataset.fullname,
                    course: container.dataset.coursename,
                    enrolinstancename: container.dataset.enrolinstancename,
                }
            }
        ];

        return Promise.all([Str.get_strings(stringData), modal]);
    })
    .then(([strings, modal]) => {
        modal.setTitle(strings[0]);
        modal.setSaveButtonText(strings[0]);
        modal.setBody(strings[1]);

        return modal;
    })
    .catch(Notification.exception);
};

/**
 * Show the user details dialogue.
 *
 * @param {HTMLElement} link
 */
const showStatusDetails = link => {
    const container = getStatusContainer(link);

    const context = {
        editenrollink: '',
        statusclass: container.querySelector('span.badge').getAttribute('class'),
        ...container.dataset,
    };

    // Find the edit enrolment link.
    const editEnrolLink = container.querySelector(Selectors.editEnrolment);
    if (editEnrolLink) {
        // If there's an edit enrolment link for this user, clone it into the context for the modal.
        context.editenrollink = editEnrolLink.outerHTML;
    }

    CancelModal.create({
        large: true,
        title: Str.get_string('enroldetails', 'enrol'),
        body: Templates.render('core_user/status_details', context),
    })
    .then(modal => {
        if (editEnrolLink) {
            modal.getRoot().on('click', Selectors.editEnrolment, e => {
                e.preventDefault();
                modal.hide();

                // Trigger click event for the edit enrolment link to show the edit enrolment modal.
                editEnrolLink.click();
            });
        }

        modal.show();

        // Handle hidden event.
        modal.getRoot().on(ModalEvents.hidden, () => modal.destroy());

        return modal;
    })
    .catch(Notification.exception);
};

/**
 * Submit the edit dialogue.
 *
 * @param {Function} getBody
 * @param {Object} modal
 * @param {Number} userEnrolmentId
 * @param {Object} userData
 */
const submitEditFormAjax = (getBody, modal, userEnrolmentId, userData) => {
    const form = modal.getRoot().find('form');

    Repository.submitUserEnrolmentForm(form.serialize())
    .then(data => {
        if (!data.result) {
            throw data.result;
        }

        // Dismiss the modal.
        modal.hide();
        modal.destroy();

        return data;
    })
    .then(() => reloadWithToast(Str.get_string('enrolmentupdatedforuser', 'core_enrol', userData)))
    .catch(() => {
        modal.setBody(getBody(userEnrolmentId, JSON.stringify(form.serialize())));

        return modal;
    });
};

/**
 * Submit the unenrolment form.
 *
 * @param {Object} modal
 * @param {Object} args
 * @param {Object} userData
 */
const submitUnenrolFormAjax = (modal, args, userData) => {
    Repository.unenrolUser(args.ueid)
    .then(data => {
        if (!data.result) {
            // Display an alert containing the error message.
            Notification.alert(data.errors[0].key, data.errors[0].message);

            return null;
        }

        // Dismiss the modal.
        modal.hide();
        modal.destroy();

        return reloadWithToast(Str.get_string('unenrolleduser', 'core_enrol', userData));
    })
    .catch(Notification.exception);
};

/**
 * Get the body fragment.
 *
 * @param {Number} contextId
 * @param {Number} ueid The user enrolment id
 * @param {Object} formdata
 * @returns {Promise}
 */
const getBody = (contextId, ueid, formdata = null) => Fragment.loadFragment(
    'enrol',
    'user_enrolment_form',
    contextId,
    {
        ueid,
        formdata,
    }
);

/**
 * Initialise the status field handler.
 *
 * @param {object} param
 * @param {String} param.uniqueid The unique id of the participants table.
 */
export const init = ({uniqueid}) => {
    showStoredToast();
    registerEventListeners(uniqueid);
};
