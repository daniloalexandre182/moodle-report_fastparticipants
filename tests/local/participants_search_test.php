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
 * Unit tests for \report_fastparticipants\local\participants_search.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\local;

/**
 * Unit tests for \report_fastparticipants\local\participants_search.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\local\participants_search
 */
final class participants_search_test extends \advanced_testcase {
    /**
     * Builds a search for a course from URL-like parameters, normalised for the current user.
     *
     * @param \stdClass $course The course.
     * @param array $params Filter parameters.
     * @return participants_search
     */
    private function make_search(\stdClass $course, array $params = []): participants_search {
        $context = \context_course::instance($course->id);
        $filter = filter::from_array($params + ['run' => 1])->normalise($course, $context);
        return new participants_search($course, $context, $filter);
    }

    /**
     * Asserts that a search matches exactly the given users, both in count() and in the listed ids.
     *
     * @param \stdClass[] $expectedusers Expected users.
     * @param participants_search $search The search.
     */
    private function assert_matches(array $expectedusers, participants_search $search): void {
        $expected = array_map(static fn(\stdClass $user): int => (int) $user->id, $expectedusers);
        sort($expected);
        $actual = $search->get_page_userids([], 0, 1000);
        sort($actual);
        $this->assertSame($expected, $actual);
        $this->assertSame(count($expected), $search->count());
    }

    /**
     * Returns the id of a role from its shortname.
     *
     * @param string $shortname Role shortname.
     * @return int
     */
    private function get_roleid(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * Returns the enrolment instance of a plugin in a course, creating it when needed.
     *
     * @param \stdClass $course The course.
     * @param string $enrol Enrol plugin name.
     * @param int $status Instance status (ENROL_INSTANCE_*).
     * @return \stdClass
     */
    private function get_instance(\stdClass $course, string $enrol, int $status = ENROL_INSTANCE_ENABLED): \stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => $enrol]);
        if (!$instance) {
            $id = enrol_get_plugin($enrol)->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }
        if ((int) $instance->status !== $status) {
            $DB->set_field('enrol', 'status', $status, ['id' => $instance->id]);
            $instance->status = $status;
        }
        return $instance;
    }

    /**
     * Enrolled users are listed once, while deleted users, the guest and non-enrolled users are not.
     */
    public function test_basic_listing(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();

        $twice = $generator->create_user();
        $generator->enrol_user($twice->id, $course->id, 'student');
        $selfinstance = $this->get_instance($course, 'self');
        enrol_get_plugin('self')->enrol_user($selfinstance, $twice->id, $this->get_roleid('student'));

        $single = $generator->create_user();
        $generator->enrol_user($single->id, $course->id, 'student');

        $deleted = $generator->create_user();
        $generator->enrol_user($deleted->id, $course->id, 'student');
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $manual = $this->get_instance($course, 'manual');
        $DB->insert_record('user_enrolments', (object) [
            'enrolid' => $manual->id,
            'userid' => $CFG->siteguest,
            'status' => ENROL_USER_ACTIVE,
            'timestart' => 0,
            'timeend' => 0,
            'modifierid' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $other = $generator->create_user();
        $generator->enrol_user($other->id, $othercourse->id, 'student');
        $generator->create_user();

        $this->assert_matches([$twice, $single], $this->make_search($course));
    }

    /**
     * Searching by full name is a case insensitive "contains", and LIKE wildcards are escaped.
     */
    public function test_search_fullname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $alice = $generator->create_user(['firstname' => 'Alice', 'lastname' => 'Smithson']);
        $bob = $generator->create_user(['firstname' => 'Bob', 'lastname' => 'Jones']);
        $generator->enrol_user($alice->id, $course->id, 'student');
        $generator->enrol_user($bob->id, $course->id, 'student');

        $this->assert_matches([$alice], $this->make_search($course, ['search' => 'smith']));
        $this->assert_matches([$alice], $this->make_search($course, ['search' => 'ALICE SMI']));
        $this->assert_matches([$bob], $this->make_search($course, ['searchfield' => 'fullname', 'search' => 'b jon']));
        $this->assert_matches([], $this->make_search($course, ['search' => '%']));
        $this->assert_matches([], $this->make_search($course, ['search' => 'A_ice']));
        $this->assert_matches([$alice, $bob], $this->make_search($course, ['search' => '   ']));
    }

    /**
     * Searching by email and username only looks at the chosen field.
     */
    public function test_search_email_and_username(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('showuseridentity', 'email,username');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $u1 = $generator->create_user(['email' => 'first@example.com', 'username' => 'zeta']);
        $u2 = $generator->create_user(['email' => 'second@example.org', 'username' => 'first']);
        $generator->enrol_user($u1->id, $course->id, 'student');
        $generator->enrol_user($u2->id, $course->id, 'student');

        $this->assert_matches([$u1], $this->make_search($course, ['searchfield' => 'email', 'search' => 'FIRST@']));
        $this->assert_matches([$u2], $this->make_search($course, ['searchfield' => 'email', 'search' => '.org']));
        $this->assert_matches([$u2], $this->make_search($course, ['searchfield' => 'username', 'search' => 'first']));
        $this->assert_matches([$u1], $this->make_search($course, ['searchfield' => 'username', 'search' => 'zet']));
    }

    /**
     * Searching by a custom profile field shown as identity field, and loading it with the users.
     */
    public function test_search_profile_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'dept', 'name' => 'Department']);
        set_config('showuseridentity', 'email,profile_field_dept');

        $course = $generator->create_course();
        $physics = $generator->create_user(['profile_field_dept' => 'Physics']);
        $chemistry = $generator->create_user(['profile_field_dept' => 'Chemistry']);
        $none = $generator->create_user();
        foreach ([$physics, $chemistry, $none] as $user) {
            $generator->enrol_user($user->id, $course->id, 'student');
        }

        $this->assert_matches([$physics], $this->make_search($course, ['searchfield' => 'profile_field_dept', 'search' => 'phys']));
        $this->assert_matches(
            [$physics, $chemistry],
            $this->make_search($course, ['searchfield' => 'profile_field_dept', 'search' => 'S'])
        );

        $users = $this->make_search($course)->get_users([$chemistry->id, $physics->id, $none->id]);
        $this->assertSame([(int) $chemistry->id, (int) $physics->id, (int) $none->id], array_keys($users));
        $this->assertSame('Chemistry', $users[$chemistry->id]->profile_field_dept);
        $this->assertSame('Physics', $users[$physics->id]->profile_field_dept);
        $this->assertSame($physics->email, $users[$physics->id]->email);
    }

    /**
     * Role filter: a role (including assignments in parent contexts) and "no role".
     */
    public function test_role_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);

        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $norole = $generator->create_user();
        $catrole = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($norole->id, $course->id, 0);
        $generator->enrol_user($catrole->id, $course->id, 0);
        $generator->role_assign('editingteacher', $catrole->id, \context_coursecat::instance($category->id)->id);

        $this->assert_matches([$student], $this->make_search($course, ['roleid' => $this->get_roleid('student')]));
        $this->assert_matches([$teacher], $this->make_search($course, ['roleid' => $this->get_roleid('teacher')]));
        $this->assert_matches([$catrole], $this->make_search($course, ['roleid' => $this->get_roleid('editingteacher')]));
        $this->assert_matches([$norole], $this->make_search($course, ['roleid' => filter::ROLE_NONE]));
        $this->assert_matches([$student, $teacher, $norole, $catrole], $this->make_search($course, ['roleid' => 0]));
    }

    /**
     * Group filter: a group and "no group".
     */
    public function test_group_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $g1 = $generator->create_group(['courseid' => $course->id]);
        $g2 = $generator->create_group(['courseid' => $course->id]);

        $u1 = $generator->create_user();
        $u2 = $generator->create_user();
        $u12 = $generator->create_user();
        $nogroup = $generator->create_user();
        foreach ([$u1, $u2, $u12, $nogroup] as $user) {
            $generator->enrol_user($user->id, $course->id, 'student');
        }
        $generator->create_group_member(['groupid' => $g1->id, 'userid' => $u1->id]);
        $generator->create_group_member(['groupid' => $g2->id, 'userid' => $u2->id]);
        $generator->create_group_member(['groupid' => $g1->id, 'userid' => $u12->id]);
        $generator->create_group_member(['groupid' => $g2->id, 'userid' => $u12->id]);

        $this->assert_matches([$u1, $u12], $this->make_search($course, ['groupid' => $g1->id]));
        $this->assert_matches([$u2, $u12], $this->make_search($course, ['groupid' => $g2->id]));
        $this->assert_matches([$nogroup], $this->make_search($course, ['groupid' => USERSWITHOUTGROUP]));
    }

    /**
     * Status filter: active, suspended and not current (disabled instance, future start, past end).
     */
    public function test_status_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $studentroleid = $this->get_roleid('student');
        $selfinstance = $this->get_instance($course, 'self', ENROL_INSTANCE_DISABLED);

        $active = $generator->create_user();
        $generator->enrol_user($active->id, $course->id, 'student');
        $suspended = $generator->create_user();
        $generator->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $disabled = $generator->create_user();
        enrol_get_plugin('self')->enrol_user($selfinstance, $disabled->id, $studentroleid);
        $future = $generator->create_user();
        $generator->enrol_user($future->id, $course->id, 'student', 'manual', time() + DAYSECS);
        $past = $generator->create_user();
        $generator->enrol_user($past->id, $course->id, 'student', 'manual', time() - 3 * DAYSECS, time() - DAYSECS);

        $this->assert_matches([$active], $this->make_search($course, ['status' => filter::STATUS_ACTIVE]));
        $this->assert_matches([$suspended], $this->make_search($course, ['status' => filter::STATUS_SUSPENDED]));
        $this->assert_matches([$disabled, $future, $past], $this->make_search($course, ['status' => filter::STATUS_NOTCURRENT]));
        $this->assert_matches(
            [$active, $suspended, $disabled, $future, $past],
            $this->make_search($course, ['status' => filter::STATUS_ANY])
        );
    }

    /**
     * Enrolment method filter, alone and combined with the status of the same enrolment.
     */
    public function test_enrol_method_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $studentroleid = $this->get_roleid('student');
        $manual = $this->get_instance($course, 'manual');
        $self = $this->get_instance($course, 'self');
        $selfplugin = enrol_get_plugin('self');

        $manualonly = $generator->create_user();
        $generator->enrol_user($manualonly->id, $course->id, 'student');
        $selfonly = $generator->create_user();
        $selfplugin->enrol_user($self, $selfonly->id, $studentroleid);
        $both = $generator->create_user();
        $generator->enrol_user($both->id, $course->id, 'student');
        $selfplugin->enrol_user($self, $both->id, $studentroleid, 0, 0, ENROL_USER_SUSPENDED);

        $this->assert_matches([$manualonly, $both], $this->make_search($course, ['enrolid' => $manual->id]));
        $this->assert_matches([$selfonly, $both], $this->make_search($course, ['enrolid' => $self->id]));
        $this->assert_matches(
            [$both],
            $this->make_search($course, ['enrolid' => $self->id, 'status' => filter::STATUS_SUSPENDED])
        );
        $this->assert_matches(
            [],
            $this->make_search($course, ['enrolid' => $manual->id, 'status' => filter::STATUS_SUSPENDED])
        );
        $this->assert_matches([$both], $this->make_search($course, ['status' => filter::STATUS_SUSPENDED]));
    }

    /**
     * Last access filter: not accessed for N days and never accessed.
     */
    public function test_accesssince_filter(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $old = $generator->create_user();
        $recent = $generator->create_user();
        $never = $generator->create_user();
        foreach ([$old, $recent, $never] as $user) {
            $generator->enrol_user($user->id, $course->id, 'student');
        }
        $DB->insert_record(
            'user_lastaccess',
            ['userid' => $old->id, 'courseid' => $course->id, 'timeaccess' => time() - 10 * DAYSECS]
        );
        $DB->insert_record(
            'user_lastaccess',
            ['userid' => $recent->id, 'courseid' => $course->id, 'timeaccess' => time() - HOURSECS]
        );

        $this->assert_matches([$old, $never], $this->make_search($course, ['accesssince' => 5]));
        $this->assert_matches([$never], $this->make_search($course, ['accesssince' => 30]));
        $this->assert_matches([$never], $this->make_search($course, ['accesssince' => filter::ACCESS_NEVER]));
        $this->assert_matches([$old, $recent, $never], $this->make_search($course, ['accesssince' => 0]));

        $users = $this->make_search($course)->get_users([$old->id, $never->id]);
        $this->assertGreaterThan(0, $users[$old->id]->lastaccess);
        $this->assertSame(0, $users[$never->id]->lastaccess);
    }

    /**
     * Paging returns every user exactly once and sorting follows the whitelisted columns.
     */
    public function test_paging_and_sorting(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $byindex = [];
        for ($i = 0; $i < 25; $i++) {
            $user = $generator->create_user([
                'firstname' => sprintf('F%02d', 24 - $i),
                'lastname' => sprintf('L%02d', $i),
            ]);
            $generator->enrol_user($user->id, $course->id, 'student');
            if ($i % 2 === 0) {
                $DB->insert_record(
                    'user_lastaccess',
                    ['userid' => $user->id, 'courseid' => $course->id, 'timeaccess' => time() - $i * HOURSECS]
                );
            }
            $byindex[$i] = (int) $user->id;
        }

        $search = $this->make_search($course);
        $this->assertSame(25, $search->count());

        // Pages do not overlap and cover everything, in lastname order by default.
        $page1 = $search->get_page_userids([], 0, 10);
        $page2 = $search->get_page_userids([], 10, 10);
        $page3 = $search->get_page_userids([], 20, 10);
        $this->assertCount(10, $page1);
        $this->assertCount(10, $page2);
        $this->assertCount(5, $page3);
        $this->assertSame(array_values($byindex), array_merge($page1, $page2, $page3));

        $this->assertSame(array_values($byindex), $search->get_page_userids(['lastname' => SORT_ASC], 0, 100));
        $this->assertSame(array_reverse(array_values($byindex)), $search->get_page_userids(['lastname' => SORT_DESC], 0, 100));
        $this->assertSame(array_reverse(array_values($byindex)), $search->get_page_userids(['firstname' => SORT_ASC], 0, 100));
        // The default full name format starts with the first name.
        $this->assertSame(array_reverse(array_values($byindex)), $search->get_page_userids(['fullname' => SORT_ASC], 0, 100));
        // Unknown columns are ignored.
        $unknown = ['password' => SORT_DESC, 'roles' => SORT_ASC];
        $this->assertSame(array_values($byindex), $search->get_page_userids($unknown, 0, 100));

        // Last access descending: most recent first (even indexes ascending), then the never accessed ones by id.
        $expected = [];
        for ($i = 0; $i < 25; $i += 2) {
            $expected[] = $byindex[$i];
        }
        for ($i = 1; $i < 25; $i += 2) {
            $expected[] = $byindex[$i];
        }
        $this->assertSame($expected, $search->get_page_userids(['lastaccess' => SORT_DESC], 0, 100));
        $this->assertSame(
            array_reverse(array_slice($expected, 0, 13)),
            array_slice($search->get_page_userids(['lastaccess' => SORT_ASC], 0, 100), 12)
        );

        // Exports and "select all".
        $rs = $search->get_userids_recordset([]);
        $ids = [];
        foreach ($rs as $record) {
            $ids[] = (int) $record->id;
        }
        $rs->close();
        $this->assertSame(array_values($byindex), $ids);
        $this->assertSame(array_slice(array_values($byindex), 0, 3), $search->get_matching_userids(3));

        $sortable = participants_search::get_sortable_columns(\context_course::instance($course->id));
        $this->assertContains('lastaccess', $sortable);
        $this->assertContains('email', $sortable);
        $this->assertNotContains('roles', $sortable);
    }

    /**
     * Sorting by last access without the last access filter still works (the join is added only when needed).
     */
    public function test_sort_by_lastaccess_without_accesssince_filter(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $old = $generator->create_user();
        $recent = $generator->create_user();
        $never = $generator->create_user();
        foreach ([$old, $recent, $never] as $user) {
            $generator->enrol_user($user->id, $course->id, 'student');
        }
        $DB->insert_record(
            'user_lastaccess',
            ['userid' => $old->id, 'courseid' => $course->id, 'timeaccess' => time() - 10 * DAYSECS]
        );
        $DB->insert_record(
            'user_lastaccess',
            ['userid' => $recent->id, 'courseid' => $course->id, 'timeaccess' => time() - HOURSECS]
        );

        $search = $this->make_search($course);
        $this->assertSame(3, $search->count());
        $this->assertSame(
            [(int) $recent->id, (int) $old->id, (int) $never->id],
            $search->get_page_userids(['lastaccess' => SORT_DESC], 0, 10)
        );
        $this->assertSame(
            [(int) $never->id, (int) $old->id, (int) $recent->id],
            $search->get_page_userids(['lastaccess' => SORT_ASC], 0, 10)
        );
        $rs = $search->get_userids_recordset(['lastaccess' => SORT_DESC]);
        $ids = [];
        foreach ($rs as $record) {
            $ids[] = (int) $record->id;
        }
        $rs->close();
        $this->assertSame([(int) $recent->id, (int) $old->id, (int) $never->id], $ids);
    }

    /**
     * Separate groups: a non-editing teacher only sees members of their groups; without a group, nobody.
     */
    public function test_separate_groups(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $g1 = $generator->create_group(['courseid' => $course->id]);
        $g2 = $generator->create_group(['courseid' => $course->id]);

        $teacher = $generator->create_user();
        $lonelyteacher = $generator->create_user();
        $s1 = $generator->create_user();
        $s2 = $generator->create_user();
        $s3 = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($lonelyteacher->id, $course->id, 'teacher');
        foreach ([$s1, $s2, $s3] as $student) {
            $generator->enrol_user($student->id, $course->id, 'student');
        }
        $generator->create_group_member(['groupid' => $g1->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $g1->id, 'userid' => $s1->id]);
        $generator->create_group_member(['groupid' => $g2->id, 'userid' => $s2->id]);

        $this->setUser($teacher);
        $this->assert_matches([$teacher, $s1], $this->make_search($course));
        $this->assert_matches([$teacher, $s1], $this->make_search($course, ['groupid' => $g2->id]));
        $this->assert_matches([$teacher, $s1], $this->make_search($course, ['groupid' => USERSWITHOUTGROUP]));
        $this->assert_matches([$teacher, $s1], $this->make_search($course, ['groupid' => $g1->id]));

        $this->setUser($lonelyteacher);
        $search = $this->make_search($course);
        $this->assertSame(0, $search->count());
        $this->assertSame([], $search->get_page_userids([], 0, 100));
        $rs = $search->get_userids_recordset([]);
        $this->assertFalse($rs->valid());
        $rs->close();

        // Users with accessallgroups are not restricted.
        $this->setAdminUser();
        $this->assert_matches([$teacher, $lonelyteacher, $s1, $s2, $s3], $this->make_search($course));
        $this->assert_matches([$s2], $this->make_search($course, ['groupid' => $g2->id]));
    }

    /**
     * Only active enrolments are listed without enrolreview or without viewsuspendedusers.
     */
    public function test_forced_active(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);

        $teacher = $generator->create_user();
        $editingteacher = $generator->create_user();
        $active = $generator->create_user();
        $suspended = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($editingteacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($active->id, $course->id, 'student');
        $generator->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        // Editing teachers have enrolreview and viewsuspendedusers.
        $this->setUser($editingteacher);
        $this->assert_matches([$teacher, $editingteacher, $active, $suspended], $this->make_search($course));

        // Non-editing teachers do not have enrolreview: status filter ignored, active forced.
        $this->setUser($teacher);
        $this->assert_matches([$teacher, $editingteacher, $active], $this->make_search($course));
        $this->assert_matches(
            [$teacher, $editingteacher, $active],
            $this->make_search($course, ['status' => filter::STATUS_SUSPENDED])
        );

        // Editing teachers without viewsuspendedusers.
        assign_capability(
            'moodle/course:viewsuspendedusers',
            CAP_PROHIBIT,
            $this->get_roleid('editingteacher'),
            $context->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($editingteacher);
        $this->assert_matches([$teacher, $editingteacher, $active], $this->make_search($course));
        $this->assert_matches(
            [$teacher, $editingteacher, $active],
            $this->make_search($course, ['status' => filter::STATUS_SUSPENDED])
        );
    }

    /**
     * A filter that was not normalised by the caller is normalised by the constructor.
     */
    public function test_constructor_normalises_filter(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $suspended = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($teacher);
        $context = \context_course::instance($course->id);
        $search = new participants_search($course, $context, filter::from_array(['status' => filter::STATUS_SUSPENDED]));
        $this->assert_matches([$teacher], $search);
    }
}
