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
 * Unit tests for \report_fastparticipants\local\row_loader.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\local;

/**
 * Unit tests for \report_fastparticipants\local\row_loader.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\local\row_loader
 */
final class row_loader_test extends \advanced_testcase {
    /**
     * Returns the role record of a role shortname.
     *
     * @param string $shortname Role shortname.
     * @return \stdClass
     */
    private function get_role(string $shortname): \stdClass {
        global $DB;
        return $DB->get_record('role', ['shortname' => $shortname], '*', MUST_EXIST);
    }

    /**
     * Sets the page URL, needed by the enrolment actions of the status fields.
     *
     * @param \stdClass $course The course.
     */
    private function set_page(\stdClass $course): void {
        global $PAGE;
        $PAGE->set_url('/report/fastparticipants/index.php', ['id' => $course->id]);
        $PAGE->set_context(\context_course::instance($course->id));
    }

    /**
     * Roles, groups and enrolment statuses are loaded and exposed like the core participants page.
     */
    public function test_load_data(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Physics 101']);
        $context = \context_course::instance($course->id);
        $this->set_page($course);
        $output = $PAGE->get_renderer('core');

        $alpha = $generator->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $beta = $generator->create_group(['courseid' => $course->id, 'name' => 'Beta']);

        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$self) {
            $selfid = enrol_get_plugin('self')->add_instance($course);
            $self = $DB->get_record('enrol', ['id' => $selfid], '*', MUST_EXIST);
        }
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $self->id]);
        $self->status = ENROL_INSTANCE_DISABLED;

        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $other = $generator->create_user();
        $outsider = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'teacher', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->enrol_user($other->id, $course->id, 'student', 'manual', time() + DAYSECS);
        enrol_get_plugin('self')->enrol_user($self, $other->id, $this->get_role('student')->id);
        $generator->create_group_member(['groupid' => $beta->id, 'userid' => $student->id]);
        $generator->create_group_member(['groupid' => $alpha->id, 'userid' => $student->id]);
        $generator->create_group_member(['groupid' => $alpha->id, 'userid' => $teacher->id]);

        $loader = new row_loader($course, $context);
        $loader->load([$student->id, $teacher->id, $other->id, $outsider->id]);

        // Roles.
        $studentname = role_get_name($this->get_role('student'), $context, ROLENAME_BOTH);
        $teachername = role_get_name($this->get_role('teacher'), $context, ROLENAME_BOTH);
        $this->assertSame([$studentname], $loader->get_role_names($student->id));
        $this->assertSame([$teachername], $loader->get_role_names($teacher->id));
        $this->assertSame([], $loader->get_role_names($outsider->id));
        $roles = $loader->get_user_roles_editable($student)->export_for_template($output);
        $this->assertSame([(int) $this->get_role('student')->id], array_map('intval', json_decode($roles['value'])));
        $this->assertSame($studentname, $roles['displayvalue']);

        // Groups, ordered by name.
        $this->assertSame(['Alpha', 'Beta'], $loader->get_group_names($student->id));
        $this->assertSame(['Alpha'], $loader->get_group_names($teacher->id));
        $this->assertSame([], $loader->get_group_names($other->id));
        $groups = $loader->get_user_groups_editable($student)->export_for_template($output);
        $this->assertSame([(int) $alpha->id, (int) $beta->id], json_decode($groups['value']));
        $this->assertSame('Alpha, Beta', $groups['displayvalue']);
        $groups = $loader->get_user_groups_editable($other)->export_for_template($output);
        $this->assertSame([], json_decode($groups['value']));

        // Enrolment statuses.
        $manualname = enrol_get_plugin('manual')->get_instance_name($manual);
        $selfname = enrol_get_plugin('self')->get_instance_name($self);
        $active = get_string('participationactive', 'enrol');
        $suspended = get_string('participationsuspended', 'enrol');
        $notcurrent = get_string('participationnotcurrent', 'enrol');

        $texts = $loader->get_status_texts($student->id);
        $this->assertCount(1, $texts);
        $this->assertStringContainsString($manualname, $texts[0]);
        $this->assertStringContainsString($active, $texts[0]);
        $this->assertStringContainsString($suspended, $loader->get_status_texts($teacher->id)[0]);
        $othertexts = $loader->get_status_texts($other->id);
        $this->assertCount(2, $othertexts);
        foreach ($othertexts as $text) {
            $this->assertStringContainsString($notcurrent, $text);
        }
        $this->assertSame([], $loader->get_status_texts($outsider->id));

        $fields = $loader->get_status_fields($student);
        $this->assertCount(1, $fields);
        $data = $fields[0]->export_for_template($output);
        $this->assertTrue($data->active);
        $this->assertFalse($data->suspended);
        $this->assertSame($manualname, $data->enrolinstancename);
        $this->assertSame('Physics 101', $data->coursename);
        $this->assertSame(htmlspecialchars(fullname($student, true), ENT_QUOTES, 'utf-8'), $data->fullname);
        $this->assertNotEmpty($data->enrolactions);
        $this->assertNotEmpty($data->timeenrolled);

        $data = $loader->get_status_fields($teacher)[0]->export_for_template($output);
        $this->assertTrue($data->suspended);
        $this->assertFalse($data->active);

        $names = [];
        foreach ($loader->get_status_fields($other) as $field) {
            $data = $field->export_for_template($output);
            $this->assertTrue($data->notcurrent);
            $names[] = $data->enrolinstancename;
        }
        sort($names);
        $expectednames = [$manualname, $selfname];
        sort($expectednames);
        $this->assertSame($expectednames, $names);

        // A new load replaces the previous data.
        $loader->load([$outsider->id]);
        $this->assertSame([], $loader->get_role_names($student->id));
        $this->assertSame([], $loader->get_group_names($student->id));
        $this->assertSame([], $loader->get_status_texts($student->id));
        $loader->load([]);
        $this->assertSame([], $loader->get_status_fields($student));
    }

    /**
     * Users without enrolreview get no enrolment status, and profile roles only.
     */
    public function test_load_without_enrolreview(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $this->set_page($course);

        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($student->id, $course->id, 'student');

        $this->setUser($teacher);
        $loader = new row_loader($course, $context);
        $loader->load([$student->id, $teacher->id]);
        $this->assertSame([], $loader->get_status_fields($student));
        $this->assertSame([], $loader->get_status_texts($student->id));
        $this->assertSame(
            [role_get_name($this->get_role('student'), $context, ROLENAME_ALIAS)],
            $loader->get_role_names($student->id)
        );
    }

    /**
     * The number of queries run by load() does not depend on the number of users.
     */
    public function test_load_query_count_is_constant(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $this->set_page($course);
        $group1 = $generator->create_group(['courseid' => $course->id]);
        $group2 = $generator->create_group(['courseid' => $course->id]);

        $userids = [];
        for ($i = 0; $i < 45; $i++) {
            $user = $generator->create_user();
            $generator->enrol_user($user->id, $course->id, $i % 5 ? 'student' : 'teacher');
            $generator->create_group_member(['groupid' => $i % 2 ? $group1->id : $group2->id, 'userid' => $user->id]);
            $userids[] = (int) $user->id;
        }

        $loader = new row_loader($course, $context);
        // Warm up the course wide data and caches with other users.
        $loader->load(array_slice($userids, 35, 10));

        $before = $DB->perf_get_reads();
        $loader->load(array_slice($userids, 0, 5));
        $reads5 = $DB->perf_get_reads() - $before;
        $this->assertCount(1, $loader->get_status_texts($userids[0]));

        $before = $DB->perf_get_reads();
        $loader->load(array_slice($userids, 5, 30));
        $reads30 = $DB->perf_get_reads() - $before;
        $this->assertCount(1, $loader->get_status_texts($userids[34]));
        $this->assertCount(1, $loader->get_group_names($userids[34]));
        $this->assertCount(1, $loader->get_role_names($userids[34]));

        // The cost per page must not depend on the number of users (core enrolment manager queries included).
        $this->assertSame($reads5, $reads30);
        $this->assertLessThanOrEqual(10, $reads30);
    }

    /**
     * As in core 5.3, enrolments that are not current explain why: not yet started, expired or disabled method.
     */
    public function test_status_info(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $this->set_page($course);
        $output = $PAGE->get_renderer('core');

        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$self) {
            $selfid = enrol_get_plugin('self')->add_instance($course);
            $self = $DB->get_record('enrol', ['id' => $selfid], '*', MUST_EXIST);
        }
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $self->id]);
        $self->status = ENROL_INSTANCE_DISABLED;

        $active = $generator->create_user();
        $future = $generator->create_user();
        $expired = $generator->create_user();
        $disabled = $generator->create_user();
        $generator->enrol_user($active->id, $course->id, 'student', 'manual', time() - DAYSECS);
        $generator->enrol_user($future->id, $course->id, 'student', 'manual', time() + DAYSECS);
        $generator->enrol_user($expired->id, $course->id, 'student', 'manual', time() - WEEKSECS, time() - DAYSECS);
        enrol_get_plugin('self')->enrol_user($self, $disabled->id, $this->get_role('student')->id, time() - DAYSECS);

        $loader = new row_loader($course, $context);
        $loader->load([$active->id, $future->id, $expired->id, $disabled->id]);

        $data = $loader->get_status_fields($active)[0]->export_for_template($output);
        $this->assertTrue($data->active);
        $this->assertNull($data->statusinfo);

        $data = $loader->get_status_fields($future)[0]->export_for_template($output);
        $this->assertTrue($data->notcurrent);
        $this->assertSame(get_string('futureenrolment', 'enrol'), $data->statusinfo);

        $data = $loader->get_status_fields($expired)[0]->export_for_template($output);
        $this->assertTrue($data->notcurrent);
        $this->assertSame(get_string('enrolmentexpired', 'enrol'), $data->statusinfo);

        $data = $loader->get_status_fields($disabled)[0]->export_for_template($output);
        $this->assertTrue($data->notcurrent);
        $selfname = enrol_get_plugin('self')->get_instance_name($self);
        $this->assertSame(get_string('plugindisabled', 'enrol', $selfname), $data->statusinfo);
    }
}
