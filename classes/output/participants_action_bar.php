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

namespace report_fastparticipants\output;

use moodle_page;
use moodle_url;

/**
 * Tertiary navigation (action bar) of the fast participants page.
 *
 * Same as the core participants action bar, but the "Enrolled users" entry is replaced by this report
 * (when the replaceparticipants setting is on) or this report is added right after it (setting off).
 *
 * How index.php must render it (same as core_renderer::render_participants_tertiary_nav()):
 *
 *     $bar = new \report_fastparticipants\output\participants_action_bar($course, $PAGE, $renderedbuttons);
 *     echo $OUTPUT->render_from_template('core_course/participants_actionbar', $bar->export_for_template($OUTPUT));
 *
 * $renderedbuttons is optional extra HTML shown in line with the select (or null). The active entry is found
 * with URL_MATCH_BASE (path only), so the page URL may carry any filter params (run, search, ...).
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants_action_bar extends \core\output\participants_action_bar {
    /** @var int $courseid Id of the course (the parent keeps it private). */
    private $courseid;

    /**
     * Constructor.
     *
     * @param object $course The course that we are generating the nav for.
     * @param moodle_page $page The page object.
     * @param string|null $renderedcontent Additional rendered content to be displayed in line with the nav.
     */
    public function __construct(object $course, moodle_page $page, ?string $renderedcontent) {
        parent::__construct($course, $page, $renderedcontent);
        $this->courseid = (int) $course->id;
    }

    /**
     * Get the content for the url_select box, with this report replacing or following "Enrolled users".
     *
     * @return array
     */
    protected function get_content_for_select(): array {
        $content = parent::get_content_for_select();
        if (!$content) {
            return $content;
        }

        $context = \context_course::instance($this->courseid);
        if (!has_capability('report/fastparticipants:view', $context)) {
            return $content;
        }

        $coreurl = (new moodle_url('/user/index.php', ['id' => $this->courseid]))->out();
        $oururl = (new moodle_url('/report/fastparticipants/index.php', ['id' => $this->courseid]))->out();
        $replace = (bool) get_config('report_fastparticipants', 'replaceparticipants');

        foreach ($content as $index => $group) {
            foreach ($group as $heading => $items) {
                if (!is_array($items) || !array_key_exists($coreurl, $items)) {
                    continue;
                }
                $newitems = [];
                foreach ($items as $url => $label) {
                    if ($url === $coreurl && $replace) {
                        $newitems[$oururl] = $label;
                    } else {
                        $newitems[$url] = $label;
                    }
                    if ($url === $coreurl && !$replace) {
                        $newitems[$oururl] = get_string('pluginname', 'report_fastparticipants');
                    }
                }
                $content[$index][$heading] = $newitems;
                return $content;
            }
        }
        return $content;
    }
}
