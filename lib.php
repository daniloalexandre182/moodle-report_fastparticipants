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
 * This file contains functions used by the fast participants report.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This function extends the navigation with the report items.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object for the report.
 * @param context $context The context of the course.
 * @return void
 */
function report_fastparticipants_extend_navigation_course($navigation, $course, $context) {
    global $CFG;

    if ($course->id == SITEID) {
        return;
    }

    require_once($CFG->dirroot . '/course/lib.php');

    if (has_capability('report/fastparticipants:view', $context) && course_can_view_participants($context)) {
        $url = new moodle_url('/report/fastparticipants/index.php', ['id' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'report_fastparticipants'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/users', '')
        );
    }
}

/**
 * Return a list of page types.
 *
 * @param string $pagetype Current page type.
 * @param stdClass $parentcontext Block's parent context.
 * @param stdClass $currentcontext Current context of block.
 * @return array
 */
function report_fastparticipants_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = [
        '*' => get_string('page-x', 'pagetype'),
        'report-*' => get_string('page-report-x', 'pagetype'),
        'report-fastparticipants-*' => get_string('pluginname', 'report_fastparticipants'),
        'report-fastparticipants-index' => get_string('pluginname', 'report_fastparticipants'),
    ];
    return $array;
}
