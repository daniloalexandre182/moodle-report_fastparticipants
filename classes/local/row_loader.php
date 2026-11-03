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
 * Batch loader of the per-row data (roles, groups, enrolments) of the fast participants report.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\local;

use core_user\output\status_field;

/**
 * Batch loader of the per-row data (roles, groups, enrolments) of the fast participants report.
 *
 * {@see self::load()} runs a constant number of queries whatever the number of users: one for the role
 * assignments, one for the group memberships and one for the user enrolments (through a single, reused
 * course_enrolment_manager). The course wide data (roles, assignable/profile/viewable roles, groups,
 * enrolment instances) is computed only once per instance. The getters then build the same renderables
 * as core_user\table\participants (inline editable roles and groups, status fields).
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class row_loader {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \context_course The course context. */
    private \context_course $context;

    /** @var bool Whether the course wide data was computed. */
    private bool $prepared = false;

    /** @var bool Whether the current user has moodle/course:enrolreview. */
    private bool $canreviewenrol = false;

    /** @var bool Whether the current user has moodle/role:assign. */
    private bool $canassignroles = false;

    /** @var bool Whether the current user has moodle/site:viewfullnames. */
    private bool $canviewfullnames = false;

    /** @var bool Whether the current user can see every group of the course. */
    private bool $canviewallgroups = true;

    /** @var \stdClass[] All roles, with localname, as core participants table. */
    private array $allroles = [];

    /** @var array Assignable roles (id => name). */
    private array $assignableroles = [];

    /** @var array Profile roles (id => role). */
    private array $profileroles = [];

    /** @var array Viewable roles (id => name). */
    private array $viewableroles = [];

    /** @var \stdClass[] Groups of the course visible to the current user, id => group, ordered by name. */
    private array $coursegroups = [];

    /** @var string Formatted course name. */
    private string $coursename = '';

    /** @var \course_enrolment_manager|null Enrolment manager, created lazily and reused. */
    private ?\course_enrolment_manager $manager = null;

    /** @var array Enrolment instance names, id => name. */
    private array $instancenames = [];

    /** @var array Loaded role assignments, userid => [raid => record]. */
    private array $userroles = [];

    /** @var array Loaded group ids, userid => int[]. */
    private array $usergroups = [];

    /** @var array Loaded user enrolments, userid => [ueid => ue]. */
    private array $userenrolments = [];

    /**
     * Constructor.
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     */
    public function __construct(\stdClass $course, \context_course $context) {
        $this->course = $course;
        $this->context = $context;
    }

    /**
     * Loads roles, groups and enrolments of the given users, replacing previously loaded data.
     *
     * The number of queries does not depend on the number of users.
     *
     * @param int[] $userids User ids.
     * @return void
     */
    public function load(array $userids): void {
        global $DB;

        $this->prepare();
        $this->userroles = [];
        $this->usergroups = [];
        $this->userenrolments = [];

        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (empty($userids)) {
            return;
        }

        // Roles: one query.
        $this->userroles = get_users_roles($this->context, $userids, true, 'c.contextlevel DESC, r.sortorder ASC');

        // Groups: one query, only when the course has groups visible to the user.
        if (!empty($this->coursegroups)) {
            [$usersql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'rfpu');
            $params['rfpcourseid'] = $this->course->id;
            $visibility = '';
            if (!$this->canviewallgroups) {
                [$visibility, $visparams] = $this->get_member_visibility_condition();
                $params = array_merge($params, $visparams);
            }
            $sql = "SELECT gm.id, gm.userid, gm.groupid
                      FROM {groups_members} gm
                      JOIN {groups} g ON g.id = gm.groupid
                     WHERE g.courseid = :rfpcourseid
                       AND gm.userid {$usersql}
                           {$visibility}
                  ORDER BY g.name ASC, g.id ASC";
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $membership) {
                if (isset($this->coursegroups[$membership->groupid])) {
                    $this->usergroups[(int) $membership->userid][] = (int) $membership->groupid;
                }
            }
            $rs->close();
        }

        // Enrolments: one query through a single enrolment manager.
        if ($this->canreviewenrol) {
            $manager = $this->get_manager();
            if (!empty($manager->get_enrolment_instances())) {
                foreach ($manager->get_users_enrolments($userids) as $userid => $user) {
                    $enrolments = $user->enrolments;
                    ksort($enrolments);
                    foreach ($enrolments as $ue) {
                        $ue->enrolmentinstancename = $this->instancenames[$ue->enrolid] ?? '';
                    }
                    $this->userenrolments[(int) $userid] = $enrolments;
                }
            }
        }
    }

    /**
     * Returns the inline editable roles of a loaded user, built exactly as core participants table.
     *
     * @param \stdClass $user The user (with name fields).
     * @return \core_user\output\user_roles_editable
     */
    public function get_user_roles_editable(\stdClass $user): \core_user\output\user_roles_editable {
        $this->prepare();
        return new \core_user\output\user_roles_editable(
            $this->course,
            $this->context,
            $user,
            $this->allroles,
            $this->assignableroles,
            $this->profileroles,
            $this->userroles[$user->id] ?? [],
            $this->viewableroles,
        );
    }

    /**
     * Returns the inline editable groups of a loaded user, built exactly as core participants table.
     *
     * @param \stdClass $user The user (with name fields).
     * @return \core_group\output\user_groups_editable
     */
    public function get_user_groups_editable(\stdClass $user): \core_group\output\user_groups_editable {
        $this->prepare();
        return new \core_group\output\user_groups_editable(
            $this->course,
            $this->context,
            $user,
            $this->coursegroups,
            $this->usergroups[$user->id] ?? [],
        );
    }

    /**
     * Returns the status fields (one per enrolment) of a loaded user, built exactly as core participants table.
     *
     * Empty without moodle/course:enrolreview.
     *
     * @param \stdClass $user The user (with name fields).
     * @return status_field[]
     */
    public function get_status_fields(\stdClass $user): array {
        $this->prepare();
        if (!$this->canreviewenrol || empty($this->userenrolments[$user->id])) {
            return [];
        }

        $manager = $this->get_manager();
        $fullname = htmlspecialchars(fullname($user, $this->canviewfullnames), ENT_QUOTES, 'utf-8');
        $fields = [];
        foreach ($this->userenrolments[$user->id] as $ue) {
            [$status, $statusval] = $this->get_enrolment_status($ue);
            $actions = $ue->enrolmentplugin->get_user_enrolment_actions($manager, $ue);
            $statusfield = new status_field(
                $ue->enrolmentinstancename,
                $this->coursename,
                $fullname,
                $status,
                $ue->timestart,
                $ue->timeend,
                $actions,
                $ue->timecreated
            );
            $fields[] = $statusfield->set_status($statusval);
        }
        return $fields;
    }

    /**
     * Returns the names of the roles of a loaded user that the current user may see (as the roles column).
     *
     * @param int $userid User id.
     * @return string[]
     */
    public function get_role_names(int $userid): array {
        $this->prepare();
        $visible = array_merge(array_keys($this->viewableroles), array_keys($this->assignableroles));
        $names = [];
        foreach ($this->userroles[$userid] ?? [] as $assignment) {
            $roleid = (int) $assignment->roleid;
            if (isset($names[$roleid]) || !in_array($roleid, $visible) || !isset($this->allroles[$roleid])) {
                continue;
            }
            if ($this->canassignroles || array_key_exists($roleid, $this->profileroles)) {
                $names[$roleid] = format_string($this->allroles[$roleid]->localname, true, ['context' => $this->context]);
            }
        }
        return array_values($names);
    }

    /**
     * Returns the names of the visible groups of a loaded user.
     *
     * @param int $userid User id.
     * @return string[]
     */
    public function get_group_names(int $userid): array {
        $this->prepare();
        $names = [];
        foreach ($this->usergroups[$userid] ?? [] as $groupid) {
            $names[] = format_string($this->coursegroups[$groupid]->name, true, ['context' => $this->context]);
        }
        return $names;
    }

    /**
     * Returns one text per enrolment of a loaded user, e.g. "Manual enrolments: Active".
     *
     * Empty without moodle/course:enrolreview.
     *
     * @param int $userid User id.
     * @return string[]
     */
    public function get_status_texts(int $userid): array {
        $this->prepare();
        if (!$this->canreviewenrol) {
            return [];
        }
        $texts = [];
        foreach ($this->userenrolments[$userid] ?? [] as $ue) {
            [$status] = $this->get_enrolment_status($ue);
            $texts[] = get_string('enrolmentstatustext', 'report_fastparticipants', (object) [
                'instance' => $ue->enrolmentinstancename,
                'status' => $status,
            ]);
        }
        return $texts;
    }

    /**
     * Computes the course wide data once.
     *
     * @return void
     */
    private function prepare(): void {
        if ($this->prepared) {
            return;
        }
        $this->prepared = true;

        $this->canreviewenrol = has_capability('moodle/course:enrolreview', $this->context);
        $this->canassignroles = has_capability('moodle/role:assign', $this->context);
        $this->canviewfullnames = has_capability('moodle/site:viewfullnames', $this->context);
        $this->canviewallgroups = \core_group\visibility::can_view_all_groups($this->course->id);

        // Same role data as core_user\table\participants::out().
        $rolenamedisplay = $this->canreviewenrol ? ROLENAME_BOTH : ROLENAME_ALIAS;
        $this->allroles = role_fix_names(get_all_roles($this->context), $this->context, $rolenamedisplay);
        $this->assignableroles = get_assignable_roles($this->context, ROLENAME_BOTH, false);
        $this->profileroles = get_profile_roles($this->context);
        $this->viewableroles = get_viewable_roles($this->context);

        // Groups visible to the current user, without members.
        $this->coursegroups = groups_get_all_groups($this->course->id);

        $this->coursename = format_string($this->course->fullname, true, ['context' => $this->context]);

        if ($this->canreviewenrol) {
            $this->instancenames = $this->get_manager()->get_enrolment_instance_names() ?? [];
        }
    }

    /**
     * Returns the (single) enrolment manager.
     *
     * @return \course_enrolment_manager
     */
    private function get_manager(): \course_enrolment_manager {
        global $CFG, $PAGE;

        if ($this->manager === null) {
            require_once($CFG->dirroot . '/enrol/locallib.php');
            $this->manager = new \course_enrolment_manager($PAGE, $this->course);
        }
        return $this->manager;
    }

    /**
     * Returns the status label and status_field constant of a user enrolment (as core participants::col_status()).
     *
     * @param \stdClass $ue User enrolment with enrolmentinstance.
     * @return array [string label, int status_field::STATUS_*]
     */
    private function get_enrolment_status(\stdClass $ue): array {
        $status = get_string('participationactive', 'enrol');
        $statusval = status_field::STATUS_ACTIVE;
        switch ($ue->status) {
            case ENROL_USER_ACTIVE:
                $now = time();
                $isexpired = $ue->timestart > $now || ($ue->timeend > 0 && $ue->timeend < $now);
                $enrolmentdisabled = $ue->enrolmentinstance->status == ENROL_INSTANCE_DISABLED;
                // If user enrolment status has not yet started/already ended or the enrolment instance is disabled.
                if ($isexpired || $enrolmentdisabled) {
                    $status = get_string('participationnotcurrent', 'enrol');
                    $statusval = status_field::STATUS_NOT_CURRENT;
                }
                break;
            case ENROL_USER_SUSPENDED:
                $status = get_string('participationsuspended', 'enrol');
                $statusval = status_field::STATUS_SUSPENDED;
                break;
        }
        return [$status, $statusval];
    }

    /**
     * Returns the group membership visibility condition for the gm/g aliases (as groups_get_all_groups()).
     *
     * @return array [string sql starting with " AND ", array params]
     */
    private function get_member_visibility_condition(): array {
        global $USER;

        $sql = " AND (g.visibility = :rfpvisall
                      OR (g.visibility = :rfpvismembers
                          AND g.id IN (SELECT gmv.groupid FROM {groups_members} gmv WHERE gmv.userid = :rfpvisme))
                      OR (g.visibility = :rfpvisown AND gm.userid = :rfpvisme2))";
        $params = [
            'rfpvisall' => GROUPS_VISIBILITY_ALL,
            'rfpvismembers' => GROUPS_VISIBILITY_MEMBERS,
            'rfpvisme' => $USER->id,
            'rfpvisown' => GROUPS_VISIBILITY_OWN,
            'rfpvisme2' => $USER->id,
        ];
        return [$sql, $params];
    }
}
