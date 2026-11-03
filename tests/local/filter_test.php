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
 * Unit tests for \report_fastparticipants\local\filter.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\local;

/**
 * Unit tests for \report_fastparticipants\local\filter.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\local\filter
 */
final class filter_test extends \advanced_testcase {
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
     * Prohibits a capability for a role in a context and resets the access caches.
     *
     * @param string $capability Capability name.
     * @param string $role Role shortname.
     * @param \context $context Context.
     */
    private function prohibit(string $capability, string $role, \context $context): void {
        assign_capability($capability, CAP_PROHIBIT, $this->get_roleid($role), $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Creates a course with a non-editing teacher and an editing teacher.
     *
     * @return array [course, context, teacher, editingteacher]
     */
    private function create_course_with_teachers(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $editingteacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($editingteacher->id, $course->id, 'editingteacher');
        return [$course, \context_course::instance($course->id), $teacher, $editingteacher];
    }

    /**
     * Defaults and URL parameters.
     */
    public function test_defaults_and_url_params(): void {
        $this->resetAfterTest();

        $filter = filter::from_array([]);
        $this->assertSame(filter::FIELD_FULLNAME, $filter->get_searchfield());
        $this->assertSame('', $filter->get_search());
        $this->assertSame(0, $filter->get_roleid());
        $this->assertSame(0, $filter->get_groupid());
        $this->assertSame(filter::STATUS_ANY, $filter->get_status());
        $this->assertSame(0, $filter->get_enrolid());
        $this->assertSame(0, $filter->get_accesssince());
        $this->assertSame(20, $filter->get_perpage());
        $this->assertFalse($filter->is_run());
        $this->assertFalse($filter->is_normalised());
        $this->assertSame([], $filter->to_url_params());

        $params = [
            'searchfield' => 'email',
            'search' => 'abc',
            'roleid' => 5,
            'groupid' => -1,
            'status' => 1,
            'enrolid' => 7,
            'accesssince' => 14,
            'perpage' => 100,
            'run' => 1,
        ];
        $filter = filter::from_array($params + ['unknown' => 'ignored']);
        $this->assertSame($params, $filter->to_url_params());
        $this->assertTrue($filter->is_run());

        // Values are cleaned with the URL parameter types.
        $filter = filter::from_array(['searchfield' => 'e<mail>', 'search' => '  <b>x</b>  ', 'roleid' => 'abc']);
        $this->assertSame('email', $filter->get_searchfield());
        $this->assertSame('x', $filter->get_search());
        $this->assertSame(0, $filter->get_roleid());
    }

    /**
     * Building the filter from the request.
     */
    public function test_from_request(): void {
        $this->resetAfterTest();

        $_GET = ['searchfield' => 'username', 'search' => ' joe ', 'roleid' => '-1', 'groupid' => '4',
            'status' => '2', 'enrolid' => '9', 'accesssince' => '-1', 'perpage' => '50', 'run' => '1'];
        $filter = filter::from_request();
        $_GET = [];

        $this->assertSame('username', $filter->get_searchfield());
        $this->assertSame('joe', $filter->get_search());
        $this->assertSame(filter::ROLE_NONE, $filter->get_roleid());
        $this->assertSame(4, $filter->get_groupid());
        $this->assertSame(filter::STATUS_NOTCURRENT, $filter->get_status());
        $this->assertSame(9, $filter->get_enrolid());
        $this->assertSame(filter::ACCESS_NEVER, $filter->get_accesssince());
        $this->assertSame(50, $filter->get_perpage());
        $this->assertTrue($filter->is_run());
    }

    /**
     * Identity search fields require viewuseridentity, showuseridentity and respect hidden user fields.
     */
    public function test_normalise_searchfield(): void {
        $this->resetAfterTest();
        set_config('showuseridentity', 'email,idnumber');
        [$course, $context, $teacher] = $this->create_course_with_teachers();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        // Students lack moodle/site:viewuseridentity.
        $this->setUser($student);
        $this->assertSame(['fullname'], array_keys(filter::get_searchfield_options($context)));
        $filter = filter::from_array(['searchfield' => 'email', 'search' => 'x'])->normalise($course, $context);
        $this->assertSame(filter::FIELD_FULLNAME, $filter->get_searchfield());
        $this->assertSame('x', $filter->get_search());
        $this->assertTrue($filter->is_normalised());

        // Teachers can search the configured identity fields only.
        $this->setUser($teacher);
        $this->assertSame(['fullname', 'email', 'idnumber'], array_keys(filter::get_searchfield_options($context)));
        $this->assertSame('email', filter::from_array(['searchfield' => 'email'])->normalise($course, $context)->get_searchfield());
        $this->assertSame(
            filter::FIELD_FULLNAME,
            filter::from_array(['searchfield' => 'username'])->normalise($course, $context)->get_searchfield()
        );
        $this->assertSame(
            filter::FIELD_FULLNAME,
            filter::from_array(['searchfield' => 'password'])->normalise($course, $context)->get_searchfield()
        );

        // Hidden user fields need moodle/course:viewhiddenuserfields.
        set_config('hiddenuserfields', 'email');
        $this->assertSame('email', filter::from_array(['searchfield' => 'email'])->normalise($course, $context)->get_searchfield());
        $this->prohibit('moodle/course:viewhiddenuserfields', 'teacher', $context);
        $this->setUser($teacher);
        $this->assertSame(['fullname', 'idnumber'], array_keys(filter::get_searchfield_options($context)));
        $this->assertSame(
            filter::FIELD_FULLNAME,
            filter::from_array(['searchfield' => 'email'])->normalise($course, $context)->get_searchfield()
        );
    }

    /**
     * Status and enrolment method require enrolreview; active is forced without viewsuspendedusers.
     */
    public function test_normalise_enrolment_filters(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $context, $teacher, $editingteacher] = $this->create_course_with_teachers();
        $manualid = (int) $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'manual']);
        $params = ['status' => filter::STATUS_SUSPENDED, 'enrolid' => $manualid];

        $this->setUser($teacher);
        $filter = filter::from_array($params)->normalise($course, $context);
        $this->assertSame(filter::STATUS_ANY, $filter->get_status());
        $this->assertSame(0, $filter->get_enrolid());
        $this->assertTrue($filter->is_forced_active());
        $this->assertSame([], filter::get_status_options($context));
        $this->assertSame([], filter::get_enrol_options($course, $context));

        $this->setUser($editingteacher);
        $filter = filter::from_array($params)->normalise($course, $context);
        $this->assertSame(filter::STATUS_SUSPENDED, $filter->get_status());
        $this->assertSame($manualid, $filter->get_enrolid());
        $this->assertFalse($filter->is_forced_active());
        $this->assertSame(
            [filter::STATUS_ANY, filter::STATUS_ACTIVE, filter::STATUS_SUSPENDED, filter::STATUS_NOTCURRENT],
            array_keys(filter::get_status_options($context))
        );
        $this->assertArrayHasKey($manualid, filter::get_enrol_options($course, $context));
        $this->assertArrayHasKey(0, filter::get_enrol_options($course, $context));

        // Invalid values are dropped.
        $filter = filter::from_array(['status' => 7, 'enrolid' => $manualid + 1000])->normalise($course, $context);
        $this->assertSame(filter::STATUS_ANY, $filter->get_status());
        $this->assertSame(0, $filter->get_enrolid());

        // Without viewsuspendedusers the status filter is ignored but the method filter is kept.
        $this->prohibit('moodle/course:viewsuspendedusers', 'editingteacher', $context);
        $this->setUser($editingteacher);
        $filter = filter::from_array($params)->normalise($course, $context);
        $this->assertSame(filter::STATUS_ANY, $filter->get_status());
        $this->assertSame($manualid, $filter->get_enrolid());
        $this->assertTrue($filter->is_forced_active());
        $this->assertSame([], filter::get_status_options($context));
    }

    /**
     * The last access filter is dropped when lastaccess is a hidden user field.
     */
    public function test_normalise_accesssince(): void {
        $this->resetAfterTest();
        [$course, $context, $teacher] = $this->create_course_with_teachers();

        $this->setUser($teacher);
        $this->assertSame(14, filter::from_array(['accesssince' => 14])->normalise($course, $context)->get_accesssince());
        $this->assertSame(
            filter::ACCESS_NEVER,
            filter::from_array(['accesssince' => -1])->normalise($course, $context)->get_accesssince()
        );
        $this->assertSame(0, filter::from_array(['accesssince' => -5])->normalise($course, $context)->get_accesssince());
        $options = filter::get_accesssince_options($context);
        $this->assertArrayHasKey(0, $options);
        $this->assertArrayHasKey(1, $options);
        $this->assertArrayHasKey(7, $options);
        $this->assertArrayHasKey(365, $options);
        $this->assertArrayHasKey(filter::ACCESS_NEVER, $options);

        set_config('hiddenuserfields', 'lastaccess');
        $this->prohibit('moodle/course:viewhiddenuserfields', 'teacher', $context);
        $this->setUser($teacher);
        $this->assertSame(0, filter::from_array(['accesssince' => 14])->normalise($course, $context)->get_accesssince());
        $this->assertSame([], filter::get_accesssince_options($context));

        // Users allowed to see hidden fields keep the filter.
        $this->setAdminUser();
        $this->assertSame(14, filter::from_array(['accesssince' => 14])->normalise($course, $context)->get_accesssince());
    }

    /**
     * Roles must be offered to the user; "no role" is always offered.
     */
    public function test_normalise_role(): void {
        $this->resetAfterTest();
        [$course, $context, $teacher] = $this->create_course_with_teachers();
        $this->setAdminUser();

        $options = filter::get_role_options($context);
        $this->assertArrayHasKey(0, $options);
        $this->assertArrayHasKey(filter::ROLE_NONE, $options);
        $this->assertArrayHasKey($this->get_roleid('student'), $options);

        $studentid = $this->get_roleid('student');
        $this->assertSame($studentid, filter::from_array(['roleid' => $studentid])->normalise($course, $context)->get_roleid());
        $this->assertSame(
            filter::ROLE_NONE,
            filter::from_array(['roleid' => filter::ROLE_NONE])->normalise($course, $context)->get_roleid()
        );
        $this->assertSame(0, filter::from_array(['roleid' => 999999])->normalise($course, $context)->get_roleid());

        $this->setUser($teacher);
        $this->assertSame(0, filter::from_array(['roleid' => 999999])->normalise($course, $context)->get_roleid());
    }

    /**
     * Group options and separate groups restrictions.
     */
    public function test_normalise_groups(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Course without groups: no options, any group id is dropped.
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $this->setAdminUser();
        $this->assertSame([], filter::get_group_options($course, $context));
        $this->assertSame(0, filter::from_array(['groupid' => -1])->normalise($course, $context)->get_groupid());

        // Separate groups course.
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $context = \context_course::instance($course->id);
        $g1 = $generator->create_group(['courseid' => $course->id]);
        $g2 = $generator->create_group(['courseid' => $course->id]);
        $othercourse = $generator->create_course();
        $othergroup = $generator->create_group(['courseid' => $othercourse->id]);

        $filter = filter::from_array(['groupid' => $g2->id])->normalise($course, $context);
        $this->assertSame((int) $g2->id, $filter->get_groupid());
        $this->assertNull($filter->get_allowed_groupids());
        $this->assertFalse($filter->is_notingroup());
        $this->assertSame(
            USERSWITHOUTGROUP,
            filter::from_array(['groupid' => USERSWITHOUTGROUP])->normalise($course, $context)->get_groupid()
        );
        $this->assertSame(0, filter::from_array(['groupid' => $othergroup->id])->normalise($course, $context)->get_groupid());
        $this->assertSame(
            [0, USERSWITHOUTGROUP, (int) $g1->id, (int) $g2->id],
            array_keys(filter::get_group_options($course, $context))
        );

        $teacher = $generator->create_user();
        $lonely = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->enrol_user($lonely->id, $course->id, 'teacher');
        $generator->create_group_member(['groupid' => $g1->id, 'userid' => $teacher->id]);

        $this->setUser($teacher);
        $this->assertSame([0, (int) $g1->id], array_keys(filter::get_group_options($course, $context)));
        $filter = filter::from_array([])->normalise($course, $context);
        $this->assertSame([(int) $g1->id], $filter->get_allowed_groupids());
        $this->assertSame(0, $filter->get_groupid());
        $this->assertFalse($filter->is_notingroup());
        $this->assertSame(0, filter::from_array(['groupid' => $g2->id])->normalise($course, $context)->get_groupid());
        $this->assertSame(0, filter::from_array(['groupid' => USERSWITHOUTGROUP])->normalise($course, $context)->get_groupid());
        $this->assertSame((int) $g1->id, filter::from_array(['groupid' => $g1->id])->normalise($course, $context)->get_groupid());

        $this->setUser($lonely);
        $filter = filter::from_array(['groupid' => $g1->id])->normalise($course, $context);
        $this->assertTrue($filter->is_notingroup());
        $this->assertSame(0, $filter->get_groupid());
        $this->assertSame([], filter::get_group_options($course, $context));
    }

    /**
     * Rows per page are clamped to [1, maxperpage].
     */
    public function test_normalise_perpage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $this->assertSame(500, filter::from_array(['perpage' => 100000])->normalise($course, $context)->get_perpage());
        $this->assertSame(1, filter::from_array(['perpage' => 0])->normalise($course, $context)->get_perpage());
        $this->assertSame(1, filter::from_array(['perpage' => -5])->normalise($course, $context)->get_perpage());
        $this->assertSame(100, filter::from_array(['perpage' => 100])->normalise($course, $context)->get_perpage());

        set_config('maxperpage', 50, 'report_fastparticipants');
        $this->assertSame(50, filter::from_array(['perpage' => 100])->normalise($course, $context)->get_perpage());
        $this->assertSame(50, filter::get_max_perpage());
        $this->assertSame([10, 20, 50], array_keys(filter::get_perpage_options()));

        set_config('participantsperpage', 30, 'moodlecourse');
        set_config('maxperpage', 0, 'report_fastparticipants');
        $this->assertSame(30, filter::get_default_perpage());
        $this->assertSame(30, filter::from_array([])->get_perpage());
        $this->assertSame([], filter::from_array(['perpage' => 30])->to_url_params());
        $this->assertSame([10, 20, 30, 50, 100, 200, 500], array_keys(filter::get_perpage_options()));
    }

    /**
     * Normalising returns a new instance and leaves the original untouched.
     */
    public function test_normalise_is_immutable(): void {
        $this->resetAfterTest();
        [$course, $context, $teacher] = $this->create_course_with_teachers();
        $this->setUser($teacher);

        $filter = filter::from_array(['status' => filter::STATUS_SUSPENDED]);
        $normalised = $filter->normalise($course, $context);
        $this->assertNotSame($filter, $normalised);
        $this->assertSame(filter::STATUS_SUSPENDED, $filter->get_status());
        $this->assertFalse($filter->is_normalised());
        $this->assertSame(filter::STATUS_ANY, $normalised->get_status());
    }

    /**
     * The "select all" cap uses the setting, defaults to 5000 and is limited by max_input_vars.
     */
    public function test_get_max_selectall(): void {
        $this->resetAfterTest();

        $maxinput = (int) ini_get('max_input_vars');
        $limit = static fn(int $max): int => $maxinput > 0 ? min($max, max(0, $maxinput - 50)) : $max;

        unset_config('maxselectall', 'report_fastparticipants');
        $this->assertSame($limit(5000), filter::get_max_selectall());

        set_config('maxselectall', 0, 'report_fastparticipants');
        $this->assertSame(0, filter::get_max_selectall());

        set_config('maxselectall', 10, 'report_fastparticipants');
        $this->assertSame($limit(10), filter::get_max_selectall());

        set_config('maxselectall', -5, 'report_fastparticipants');
        $this->assertSame(0, filter::get_max_selectall());
    }
}
