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
 * Tests for the navigation integration.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\hook_callbacks
 * @covers     \report_fastparticipants\output\participants_action_bar
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Creates a course with a teacher, logs in the teacher and prepares $PAGE.
     *
     * @return \stdClass The course.
     */
    private function setup_course(): \stdClass {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $PAGE = new \moodle_page();
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_course($course);
        return $course;
    }

    /**
     * Returns the action of the participants node in a fresh secondary navigation.
     *
     * @return string
     */
    private function get_participants_action(): string {
        global $PAGE;
        $secondary = new \core\navigation\views\secondary($PAGE);
        $secondary->initialise();
        $node = $secondary->find('participants', null);
        $this->assertNotFalse($node);
        return $node->action()->out_as_local_url(false);
    }

    /**
     * With the setting off the node is untouched.
     */
    public function test_secondary_setting_off(): void {
        $this->resetAfterTest();
        $course = $this->setup_course();
        set_config('replaceparticipants', 0, 'report_fastparticipants');
        $this->assertStringStartsWith('/user/index.php?id=' . $course->id, $this->get_participants_action());
    }

    /**
     * With the setting on and the capability the node points to the plugin.
     */
    public function test_secondary_setting_on(): void {
        $this->resetAfterTest();
        $course = $this->setup_course();
        set_config('replaceparticipants', 1, 'report_fastparticipants');
        $this->assertStringStartsWith(
            '/report/fastparticipants/index.php?id=' . $course->id,
            $this->get_participants_action()
        );
    }

    /**
     * Without the capability the node is untouched.
     */
    public function test_secondary_setting_on_without_capability(): void {
        $this->resetAfterTest();
        $course = $this->setup_course();
        set_config('replaceparticipants', 1, 'report_fastparticipants');
        $role = get_archetype_roles('editingteacher');
        $role = reset($role);
        assign_capability(
            'report/fastparticipants:view',
            CAP_PROHIBIT,
            $role->id,
            \context_course::instance($course->id)->id,
            true
        );
        $this->assertStringStartsWith('/user/index.php?id=' . $course->id, $this->get_participants_action());
    }

    /**
     * Returns the URLs of the select options exported by the action bar, with the active one.
     *
     * @param \stdClass $course The course.
     * @return array [urls, active url]
     */
    private function get_bar_urls(\stdClass $course): array {
        global $PAGE, $OUTPUT;
        $PAGE->set_url('/report/fastparticipants/index.php', ['id' => $course->id, 'run' => 1, 'search' => 'x']);
        $PAGE->set_context(\context_course::instance($course->id));
        $bar = new output\participants_action_bar($course, $PAGE, null);
        $data = $bar->export_for_template($OUTPUT);
        $this->assertNotEmpty($data['navigation']);
        $urls = [];
        foreach ($data['navigation']->options as $option) {
            $items = !empty($option['isgroup']) ? $option['options'] : [$option];
            foreach ($items as $item) {
                if (isset($item['value'])) {
                    $urls[] = $item['value'];
                }
            }
        }
        $active = $data['navigation']->value;
        return [$urls, $active];
    }

    /**
     * With the setting off our page is an extra option and is the active one.
     */
    public function test_action_bar_setting_off(): void {
        $this->resetAfterTest();
        $course = $this->setup_course();
        set_config('replaceparticipants', 0, 'report_fastparticipants');
        [$urls, $active] = $this->get_bar_urls($course);
        $ours = (new \moodle_url('/report/fastparticipants/index.php', ['id' => $course->id]))->out();
        $core = (new \moodle_url('/user/index.php', ['id' => $course->id]))->out();
        $this->assertContains($ours, $urls);
        $this->assertContains($core, $urls);
        $this->assertSame($ours, $active);
    }

    /**
     * With the setting on the "Enrolled users" option points to our page.
     */
    public function test_action_bar_setting_on(): void {
        $this->resetAfterTest();
        $course = $this->setup_course();
        set_config('replaceparticipants', 1, 'report_fastparticipants');
        [$urls, $active] = $this->get_bar_urls($course);
        $ours = (new \moodle_url('/report/fastparticipants/index.php', ['id' => $course->id]))->out();
        $core = (new \moodle_url('/user/index.php', ['id' => $course->id]))->out();
        $this->assertContains($ours, $urls);
        $this->assertNotContains($core, $urls);
        $this->assertSame($ours, $active);
    }
}
