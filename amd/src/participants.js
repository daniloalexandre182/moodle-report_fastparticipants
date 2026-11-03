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
 * Bulk actions and "select all matching users" for the fast participants report.
 *
 * Port of the non-table parts of core_user/participants for a server rendered (non dynamic) table.
 *
 * @module     report_fastparticipants/participants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as PubSub from 'core/pubsub';
import * as Repository from './repository';
import CheckboxToggleAll from 'core/checkbox-toggleall';
import Config from 'core/config';
import CustomEvents from 'core/custom_interaction_events';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Pending from 'core/pending';
import jQuery from 'jquery';
import {getString} from 'core/str';
import {showAddNote, showSendMessage} from 'core_user/local/participants/bulkactions';
import 'core/inplace_editable';

/** @type {String} Toggle group of the user checkboxes. */
const TOGGLE_GROUP = 'report-fastparticipants-table';

const Selectors = {
    form: '#report-fastparticipants-bulkform',
    bulkActionSelect: '#formactionid',
    userCheckboxes: `input[data-togglegroup="${TOGGLE_GROUP}"][data-toggle="slave"]`,
    checkedUserCheckboxes: `input[data-togglegroup="${TOGGLE_GROUP}"][data-toggle="slave"]:checked`,
    selectAllButton: '#report-fastparticipants-selectall',
    selectedAllRegion: '[data-region="report_fastparticipants/selectedall"]',
    stateHelpIcon: '[data-region="state-help-icon"]',
};

/**
 * Extract the user id from a user checkbox/hidden input (named user{ID}).
 *
 * @param {HTMLInputElement} input
 * @returns {Number} The user id, or 0 if the name is not valid.
 */
const getUserIdFromInput = input => {
    const matches = /^user(\d+)$/.exec(input.getAttribute('name') || '');
    return matches ? parseInt(matches[1], 10) : 0;
};

/**
 * Initialise the participants page.
 *
 * Other keys passed by the PHP side (contextid, uniqueid) are accepted and ignored.
 *
 * @param {Object} params
 * @param {Number} params.courseid The course id.
 * @param {Object} params.noteStateNames Note state names (from note_get_state_names()).
 * @param {Object} params.filter Current filter parameters (from filter::to_url_params()).
 * @param {Number} params.maxselectall Maximum number of users selectable with "select all".
 */
export const init = ({
    courseid,
    noteStateNames = {},
    filter = {},
    maxselectall = 0,
}) => {
    const form = document.querySelector(Selectors.form);
    if (!form) {
        return;
    }

    const bulkActionSelect = form.querySelector(Selectors.bulkActionSelect);
    const selectAllButton = form.querySelector(Selectors.selectAllButton);
    const selectAllOriginalLabel = selectAllButton ? selectAllButton.textContent.trim() : '';

    /** @type {Number[]} Ids returned by the "select all matching users" web service. */
    let selectedAllIds = [];

    /** @type {Boolean} Whether checkbox changes are being made by this module (ignore them). */
    let updatingCheckboxes = false;

    /**
     * Get the ids of all the selected users (checked on the page + selected with "select all").
     *
     * @returns {Number[]}
     */
    const getSelectedUserIds = () => {
        const ids = new Set();
        form.querySelectorAll(Selectors.checkedUserCheckboxes).forEach(checkbox => {
            const userid = getUserIdFromInput(checkbox);
            if (userid) {
                ids.add(userid);
            }
        });
        selectedAllIds.forEach(userid => ids.add(userid));

        return Array.from(ids);
    };

    /**
     * Get the region holding the hidden inputs of the users selected with "select all".
     *
     * @returns {HTMLElement|null}
     */
    const getSelectedAllRegion = () => form.querySelector(Selectors.selectedAllRegion);

    /**
     * Check or uncheck every user checkbox of the page through core/checkbox-toggleall.
     *
     * @param {Boolean} state
     */
    const setPageCheckboxesState = state => {
        updatingCheckboxes = true;
        try {
            CheckboxToggleAll.setGroupState(form, TOGGLE_GROUP, state);
        } finally {
            updatingCheckboxes = false;
        }
    };

    /**
     * Reset the bulk action select.
     *
     * @param {HTMLSelectElement} select
     */
    const resetBulkAction = select => {
        select.value = '';
    };

    /**
     * Forget the "select all" selection.
     *
     * @param {Boolean} uncheckAll Whether to also uncheck every checkbox on the page.
     */
    const clearSelectAll = uncheckAll => {
        selectedAllIds = [];

        const region = getSelectedAllRegion();
        if (region) {
            region.innerHTML = '';
        }

        if (selectAllButton) {
            selectAllButton.textContent = selectAllOriginalLabel;
            delete selectAllButton.dataset.selected;
        }

        if (uncheckAll) {
            setPageCheckboxesState(false);
        }
    };

    /**
     * Apply a "select all" selection: check all checkboxes of the page and add hidden inputs for the other users.
     *
     * @param {Number[]} userids
     */
    const applySelectAll = userids => {
        setPageCheckboxesState(true);

        const pageIds = new Set();
        form.querySelectorAll(Selectors.userCheckboxes).forEach(checkbox => pageIds.add(getUserIdFromInput(checkbox)));

        const region = getSelectedAllRegion();
        if (region) {
            region.innerHTML = '';
            const fragment = document.createDocumentFragment();
            userids.forEach(userid => {
                if (pageIds.has(userid)) {
                    return;
                }
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `user${userid}`;
                input.value = '1';
                fragment.appendChild(input);
            });
            region.appendChild(fragment);
        }

        selectedAllIds = userids;
        selectAllButton.dataset.selected = '1';

        // The bulk actions apply to the whole selection, even if no checkbox is visible.
        if (bulkActionSelect) {
            bulkActionSelect.disabled = false;
        }
    };

    /**
     * Handle a click on the "select all matching users" button.
     */
    const handleSelectAllClick = () => {
        if (selectedAllIds.length) {
            clearSelectAll(true);
            return;
        }

        const pendingPromise = new Pending('report_fastparticipants/participants:selectAll');
        selectAllButton.disabled = true;

        Repository.getMatchingUserIds(courseid, filter)
        .then(result => {
            const userids = (result.userids || []).map(userid => parseInt(userid, 10)).filter(userid => userid > 0);
            if (!userids.length) {
                return [null, null];
            }

            applySelectAll(userids);

            const strings = [getString('selectedallcount', 'report_fastparticipants', userids.length)];
            if (result.truncated) {
                strings.push(getString('selectalltruncated', 'report_fastparticipants', {
                    count: userids.length,
                    total: result.total,
                    max: maxselectall || userids.length,
                }));
            }

            return Promise.all(strings);
        })
        .then(([label, truncatedMessage]) => {
            selectAllButton.disabled = false;
            if (label) {
                selectAllButton.textContent = label;
            }
            pendingPromise.resolve();

            if (truncatedMessage) {
                return Notification.addNotification({
                    message: truncatedMessage,
                    type: 'warning',
                });
            }

            return null;
        })
        .catch(error => {
            selectAllButton.disabled = false;
            pendingPromise.resolve();
            Notification.exception(error);
        });
    };

    /**
     * Handle the selection of a bulk action.
     *
     * @param {Event} e
     */
    const handleBulkActionChange = e => {
        const select = e.target.closest('select');
        const action = select.value;
        if (action === '') {
            return;
        }

        const pendingPromise = new Pending('report_fastparticipants/participants:bulkActionSelect');
        const ids = getSelectedUserIds();

        if (action.indexOf('#') !== -1) {
            e.preventDefault();

            let bulkAction = null;
            if (ids.length && action === '#messageselect') {
                bulkAction = showSendMessage(ids);
            } else if (ids.length && action === '#addgroupnote') {
                bulkAction = showAddNote(
                    courseid,
                    ids,
                    noteStateNames,
                    form.querySelector(Selectors.stateHelpIcon) || document.querySelector(Selectors.stateHelpIcon)
                );
            }

            if (bulkAction) {
                const pendingBulkAction = new Pending('report_fastparticipants/participants:bulkActionSelected');
                bulkAction
                .then(modal => {
                    if (modal) {
                        modal.getRoot().on(ModalEvents.hidden, () => {
                            // Focus on the action select when the dialog is closed.
                            select.focus();
                        });
                    }

                    pendingBulkAction.resolve();
                    return modal;
                })
                .catch(error => {
                    pendingBulkAction.resolve();
                    Notification.exception(error);
                });
            }
        } else if (ids.length) {
            const option = select.options[select.selectedIndex];
            const endpoint = (option && option.dataset.endpoint) || `${Config.wwwroot}/user/action_redir.php`;
            form.setAttribute('action', endpoint);
            form.submit();
        }

        resetBulkAction(select);
        pendingPromise.resolve();
    };

    /**
     * Register the event listeners.
     */
    const registerEventListeners = () => {
        // Make sure the checkbox toggle all listeners are registered.
        CheckboxToggleAll.init();

        if (bulkActionSelect) {
            // Like core, use the accessible change event so keyboard navigation in the select does not trigger an action.
            CustomEvents.define(bulkActionSelect, [CustomEvents.events.accessibleChange]);
            jQuery(bulkActionSelect).on(CustomEvents.events.accessibleChange, handleBulkActionChange);
        }

        if (selectAllButton) {
            form.addEventListener('click', e => {
                if (e.target.closest(Selectors.selectAllButton)) {
                    e.preventDefault();
                    handleSelectAllClick();
                }
            });
        }

        // If the user unchecks any row while "select all" is active, drop the "select all" selection.
        PubSub.subscribe(CheckboxToggleAll.events.checkboxToggled, data => {
            if (updatingCheckboxes || !selectedAllIds.length || data.toggleGroupName !== TOGGLE_GROUP) {
                return;
            }
            if (data.checkedSlaves.length !== data.slaves.length) {
                clearSelectAll(false);
            }
        });
    };

    registerEventListeners();
};
