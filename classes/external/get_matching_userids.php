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
 * External function returning the ids of all users matching the report filter.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use report_fastparticipants\local\filter;
use report_fastparticipants\local\participants_search;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

/**
 * External function returning the ids of all users matching the report filter.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_matching_userids extends external_api {
    /**
     * Describes the parameters of execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'filter' => new external_single_structure([
                'searchfield' => new external_value(PARAM_ALPHANUMEXT, 'Field to search', VALUE_OPTIONAL),
                'search' => new external_value(PARAM_TEXT, 'Search text', VALUE_OPTIONAL),
                'roleid' => new external_value(PARAM_INT, 'Role id', VALUE_OPTIONAL),
                'groupid' => new external_value(PARAM_INT, 'Group id', VALUE_OPTIONAL),
                'status' => new external_value(PARAM_INT, 'Enrolment status', VALUE_OPTIONAL),
                'enrolid' => new external_value(PARAM_INT, 'Enrolment instance id', VALUE_OPTIONAL),
                'accesssince' => new external_value(PARAM_INT, 'Last access filter', VALUE_OPTIONAL),
            ], 'Report filter', VALUE_DEFAULT, []),
        ]);
    }

    /**
     * Returns the ids of the users matching the filter, up to the configured maximum.
     *
     * @param int $courseid Course id.
     * @param array $filter Filter values.
     * @return array With userids, total and truncated.
     */
    public static function execute(int $courseid, array $filter = []): array {
        global $DB;

        ['courseid' => $courseid, 'filter' => $filter] = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'filter' => $filter]
        );

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('report/fastparticipants:view', $context);
        if (!course_can_view_participants($context) || (int) $course->id === SITEID) {
            throw new \required_capability_exception($context, 'moodle/course:viewparticipants', 'nopermissions', '');
        }

        $f = filter::from_array($filter + ['run' => 1])->normalise($course, $context);
        if ($f->is_notingroup()) {
            return ['userids' => [], 'total' => 0, 'truncated' => false];
        }

        $max = filter::get_max_selectall();
        $search = new participants_search($course, $context, $f);
        $total = $search->count();
        if ($max <= 0) {
            // Select all is disabled.
            return ['userids' => [], 'total' => $total, 'truncated' => $total > 0];
        }
        $ids = $search->get_matching_userids($max);

        return ['userids' => $ids, 'total' => $total, 'truncated' => $total > count($ids)];
    }

    /**
     * Describes the return value of execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userids' => new external_multiple_structure(new external_value(PARAM_INT, 'User id')),
            'total' => new external_value(PARAM_INT, 'Total number of matching users'),
            'truncated' => new external_value(PARAM_BOOL, 'Whether userids was cut at the configured maximum'),
        ]);
    }
}
