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

namespace report_fastparticipants;

/**
 * Hook callbacks of report_fastparticipants.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Points the course secondary navigation "Participants" node to this report when the admin enabled it.
     *
     * Only config and capability checks are performed, so it is cheap on every course page.
     *
     * @param \core\hook\navigation\secondary_extend $hook The hook.
     * @return void
     */
    public static function secondary_extend(\core\hook\navigation\secondary_extend $hook): void {
        global $CFG, $PAGE;

        if (!get_config('report_fastparticipants', 'replaceparticipants')) {
            return;
        }

        $course = $PAGE->course;
        if (empty($course->id) || $course->id == SITEID) {
            return;
        }
        $context = $PAGE->context;
        if (!in_array($context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE])) {
            return;
        }

        require_once($CFG->dirroot . '/course/lib.php');
        $coursecontext = \context_course::instance($course->id);
        if (
            !has_capability('report/fastparticipants:view', $coursecontext) ||
            !course_can_view_participants($coursecontext)
        ) {
            return;
        }

        $node = $hook->get_secondaryview()->find('participants', null);
        if ($node) {
            $node->action = new \moodle_url('/report/fastparticipants/index.php', ['id' => $course->id]);
        }
    }
}
