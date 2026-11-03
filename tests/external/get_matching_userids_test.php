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
 * Unit tests for \report_fastparticipants\external\get_matching_userids.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\external;

use core_external\external_api;

/**
 * Unit tests for \report_fastparticipants\external\get_matching_userids.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\external\get_matching_userids
 */
final class get_matching_userids_test extends \advanced_testcase {
    /**
     * Runs the function and cleans the return value.
     *
     * @param int $courseid Course id.
     * @param array $filter Filter.
     * @return array
     */
    private function call(int $courseid, array $filter = []): array {
        $result = get_matching_userids::execute($courseid, $filter);
        return external_api::clean_returnvalue(get_matching_userids::execute_returns(), $result);
    }

    /**
     * Creates a course with a teacher and students.
     *
     * @param int $students Number of students.
     * @return array [course, teacher, students]
     */
    private function setup_course(int $students = 3): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $users = [];
        for ($i = 0; $i < $students; $i++) {
            $users[] = $this->getDataGenerator()->create_and_enrol($course, 'student', [
                'firstname' => 'Student' . $i,
                'lastname' => 'Zed',
            ]);
        }
        return [$course, $teacher, $users];
    }

    /**
     * A teacher gets all enrolled users.
     */
    public function test_teacher_gets_all_users(): void {
        $this->resetAfterTest();
        [$course, $teacher, $students] = $this->setup_course();
        $this->setUser($teacher);

        $result = $this->call($course->id);
        $expected = array_map(static fn($u) => (int) $u->id, $students);
        $expected[] = (int) $teacher->id;
        sort($expected);
        $ids = $result['userids'];
        sort($ids);
        $this->assertSame($expected, $ids);
        $this->assertSame(4, $result['total']);
        $this->assertFalse($result['truncated']);
    }

    /**
     * The search filter is applied.
     */
    public function test_search_filter(): void {
        $this->resetAfterTest();
        [$course, $teacher, $students] = $this->setup_course();
        $this->setUser($teacher);

        $result = $this->call($course->id, ['searchfield' => 'fullname', 'search' => 'Student1']);
        $this->assertSame([(int) $students[1]->id], $result['userids']);
        $this->assertSame(1, $result['total']);
    }

    /**
     * The result is truncated at the configured maximum.
     */
    public function test_truncated(): void {
        $this->resetAfterTest();
        [$course, $teacher] = $this->setup_course();
        $this->setUser($teacher);
        set_config('maxselectall', 2, 'report_fastparticipants');

        $result = $this->call($course->id);
        $this->assertCount(2, $result['userids']);
        $this->assertSame(4, $result['total']);
        $this->assertTrue($result['truncated']);
    }

    /**
     * A cap of 0 disables "select all": no ids are returned and the result is truncated.
     */
    public function test_disabled_when_max_is_zero(): void {
        $this->resetAfterTest();
        [$course, $teacher] = $this->setup_course();
        $this->setUser($teacher);
        set_config('maxselectall', 0, 'report_fastparticipants');

        $result = $this->call($course->id);
        $this->assertSame([], $result['userids']);
        $this->assertSame(4, $result['total']);
        $this->assertTrue($result['truncated']);
    }

    /**
     * A student without the capability is rejected.
     */
    public function test_student_denied(): void {
        $this->resetAfterTest();
        [$course, , $students] = $this->setup_course();
        $this->setUser($students[0]);

        $this->expectException(\required_capability_exception::class);
        get_matching_userids::execute($course->id, []);
    }

    /**
     * In separate groups mode a non-editing teacher only gets users of their own groups.
     */
    public function test_separate_groups(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $mine = $gen->create_and_enrol($course, 'student');
        $other = $gen->create_and_enrol($course, 'student');
        $group1 = $gen->create_group(['courseid' => $course->id]);
        $group2 = $gen->create_group(['courseid' => $course->id]);
        $gen->create_group_member(['groupid' => $group1->id, 'userid' => $teacher->id]);
        $gen->create_group_member(['groupid' => $group1->id, 'userid' => $mine->id]);
        $gen->create_group_member(['groupid' => $group2->id, 'userid' => $other->id]);
        $this->setUser($teacher);

        $result = $this->call($course->id);
        $ids = $result['userids'];
        sort($ids);
        $expected = [(int) $teacher->id, (int) $mine->id];
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertSame(2, $result['total']);
    }
}
