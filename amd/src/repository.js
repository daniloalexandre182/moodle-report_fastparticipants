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
 * Web service calls for the fast participants report.
 *
 * @module     report_fastparticipants/repository
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';

/**
 * Filter keys accepted by the web service, grouped by type.
 *
 * @type {{text: string[], integer: string[]}}
 */
const FilterKeys = {
    text: ['searchfield', 'search'],
    integer: ['roleid', 'groupid', 'status', 'enrolid', 'accesssince'],
};

/**
 * Build the filter structure expected by the web service from the page filter parameters.
 *
 * Only whitelisted keys are kept (the page parameters may contain other keys, such as id, run or perpage).
 *
 * @param {Object} filter Filter parameters as returned by filter::to_url_params().
 * @returns {Object}
 */
const normaliseFilter = filter => {
    const result = {};
    if (!filter || typeof filter !== 'object' || Array.isArray(filter)) {
        return result;
    }

    FilterKeys.text.forEach(key => {
        if (filter[key] !== undefined && filter[key] !== null) {
            result[key] = String(filter[key]);
        }
    });

    FilterKeys.integer.forEach(key => {
        if (filter[key] === undefined || filter[key] === null || filter[key] === '') {
            return;
        }
        const value = parseInt(filter[key], 10);
        if (!isNaN(value)) {
            result[key] = value;
        }
    });

    return result;
};

/**
 * Get the ids of all users matching the given filter (up to the configured select all limit).
 *
 * @param {Number} courseid The course id.
 * @param {Object} filter Filter parameters as returned by filter::to_url_params().
 * @returns {Promise<{userids: Number[], total: Number, truncated: Boolean}>}
 */
export const getMatchingUserIds = (courseid, filter) => fetchMany([{
    methodname: 'report_fastparticipants_get_matching_userids',
    args: {
        courseid: parseInt(courseid, 10),
        filter: normaliseFilter(filter),
    },
}])[0];
