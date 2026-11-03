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
 * Unit tests for \report_fastparticipants\local\exporter.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\local;

/**
 * Unit tests for \report_fastparticipants\local\exporter.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\local\exporter
 */
final class exporter_test extends \advanced_testcase {
    /**
     * Creates a course with a teacher, three students in a group and a user who is not enrolled.
     *
     * @return array [course, context, teacher, students, roleid of editingteacher]
     */
    private function create_course_with_users(): array {
        global $DB, $PAGE;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['shortname' => 'PHY101']);
        $context = \context_course::instance($course->id);
        $PAGE->set_url('/report/fastparticipants/index.php', ['id' => $course->id]);
        $PAGE->set_context($context);

        $teacher = $generator->create_and_enrol($course, 'editingteacher', [
            'firstname' => 'Tina',
            'lastname' => 'Teacher',
            'email' => 'teacher@example.com',
        ]);
        $group = $generator->create_group(['courseid' => $course->id, 'name' => 'Lab A']);
        $students = [];
        foreach (['ana', 'bruno', 'carla'] as $name) {
            $student = $generator->create_and_enrol($course, 'student', [
                'firstname' => ucfirst($name),
                'lastname' => 'Student',
                'email' => $name . '@example.com',
            ]);
            $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
            $students[] = $student;
        }
        // A user that is not enrolled must not be exported.
        $generator->create_user();

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        return [$course, $context, $teacher, $students, $roleid];
    }

    /**
     * Builds an exporter for the current user with the default filter.
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @return exporter
     */
    private function get_exporter(\stdClass $course, \context_course $context): exporter {
        $filter = filter::from_array(['run' => 1])->normalise($course, $context);
        $search = new participants_search($course, $context, $filter);
        return new exporter($course, $context, $search, ['lastname' => SORT_ASC, 'firstname' => SORT_ASC]);
    }

    /**
     * Every enrolled user is exported once, with every column, in the requested order.
     */
    public function test_rows_and_columns(): void {
        $this->resetAfterTest();
        set_config('showuseridentity', 'email');
        set_config('hiddenuserfields', '');

        [$course, $context, $teacher] = $this->create_course_with_users();
        $this->setUser($teacher);

        $exporter = $this->get_exporter($course, $context);
        $columns = $exporter->get_columns();
        $this->assertSame(['fullname', 'email', 'roles', 'groups', 'lastaccess', 'status'], array_keys($columns));

        $rows = iterator_to_array($exporter->get_rows(), false);
        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertSame(array_keys($columns), array_keys($row));
        }

        // Sorted by last name then first name: the three students first, then the teacher.
        $this->assertSame('Ana Student', $rows[0]['fullname']);
        $this->assertSame('ana@example.com', $rows[0]['email']);
        $this->assertSame('Student', $rows[0]['roles']);
        $this->assertSame('Lab A', $rows[0]['groups']);
        $this->assertSame(get_string('never'), $rows[0]['lastaccess']);
        $this->assertStringContainsString(get_string('participationactive', 'enrol'), $rows[0]['status']);
        $this->assertSame('Carla Student', $rows[2]['fullname']);
        $this->assertSame('teacher@example.com', $rows[3]['email']);
        $this->assertSame('', $rows[3]['groups']);

        $this->assertStringContainsString('PHY101', $exporter->get_filename());
    }

    /**
     * Hidden user fields, identity fields and the enrolment status follow the permissions of the current user.
     */
    public function test_columns_respect_permissions(): void {
        $this->resetAfterTest();
        set_config('showuseridentity', 'email');
        set_config('hiddenuserfields', 'groups,lastaccess');

        [$course, $context, $teacher, , $roleid] = $this->create_course_with_users();
        assign_capability('moodle/course:viewhiddenuserfields', CAP_PROHIBIT, $roleid, $context->id, true);
        assign_capability('moodle/course:enrolreview', CAP_PROHIBIT, $roleid, $context->id, true);
        assign_capability('moodle/site:viewuseridentity', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->setUser($teacher);

        $exporter = $this->get_exporter($course, $context);
        $columns = $exporter->get_columns();
        $this->assertSame(['fullname', 'roles'], array_keys($columns));

        $rows = iterator_to_array($exporter->get_rows(), false);
        $this->assertCount(4, $rows);
        $this->assertSame(['fullname', 'roles'], array_keys($rows[0]));
    }

    /**
     * Only enabled dataformats are accepted.
     */
    public function test_is_valid_dataformat(): void {
        $this->resetAfterTest();

        $this->assertTrue(exporter::is_valid_dataformat('csv'));
        $this->assertFalse(exporter::is_valid_dataformat(''));
        $this->assertFalse(exporter::is_valid_dataformat('doesnotexist'));
        $this->assertContains('csv', exporter::get_enabled_dataformats());
    }
}
