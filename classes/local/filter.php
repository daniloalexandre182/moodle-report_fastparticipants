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
 * Immutable set of filters applied to the fast participants report.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\local;

use core_user\fields;

/**
 * Immutable set of filters applied to the fast participants report.
 *
 * Instances are created from the request ({@see self::from_request()}) or from an array
 * ({@see self::from_array()}) and must be checked against the current user permissions with
 * {@see self::normalise()} before being used to search. Normalising never trusts the request:
 * every value that the current user is not allowed to use is reset to its default, and the
 * restrictions that core forces on the participants page (active enrolments only, separate groups)
 * are computed and exposed through {@see self::is_forced_active()}, {@see self::get_allowed_groupids()}
 * and {@see self::is_notingroup()}.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter {
    /** Search field that matches the user full name. */
    public const FIELD_FULLNAME = 'fullname';

    /** Role filter value matching users without any role in the course (or its parent contexts). */
    public const ROLE_NONE = -1;

    /** Status filter value matching any enrolment status. */
    public const STATUS_ANY = -1;

    /** Status filter value matching users with an active enrolment. */
    public const STATUS_ACTIVE = 0;

    /** Status filter value matching users with a suspended enrolment. */
    public const STATUS_SUSPENDED = 1;

    /** Status filter value matching users with an enrolment that is not current (not started, ended or disabled). */
    public const STATUS_NOTCURRENT = 2;

    /** Last access filter value matching users who never accessed the course. */
    public const ACCESS_NEVER = -1;

    /** Default hard limit of rows per page, used when the admin setting is empty. */
    private const DEFAULT_MAXPERPAGE = 500;

    /** Default number of rows per page, used when the core setting is empty. */
    private const DEFAULT_PERPAGE = 20;

    /** Default maximum number of users selected by "select all", used when the admin setting is unset. */
    private const DEFAULT_MAXSELECTALL = 5000;

    /** Highest accepted "not accessed for N days" value (100 years). */
    private const MAX_ACCESSSINCE_DAYS = 36500;

    /** @var string Field searched by the text search (fullname or an identity field). */
    private string $searchfield = self::FIELD_FULLNAME;

    /** @var string Text searched (contains). */
    private string $search = '';

    /** @var int Role id, 0 for any, {@see self::ROLE_NONE} for no role. */
    private int $roleid = 0;

    /** @var int Group id, 0 for any, USERSWITHOUTGROUP for users without group. */
    private int $groupid = 0;

    /** @var int One of the STATUS_* constants. */
    private int $status = self::STATUS_ANY;

    /** @var int Enrolment instance id, 0 for any. */
    private int $enrolid = 0;

    /** @var int 0 for any, {@see self::ACCESS_NEVER} or a number of days without access. */
    private int $accesssince = 0;

    /** @var int Rows per page. */
    private int $perpage = self::DEFAULT_PERPAGE;

    /** @var bool Whether the search must run (false shows only the form). */
    private bool $run = false;

    /** @var bool Whether {@see self::normalise()} produced this instance. */
    private bool $normalised = false;

    /** @var bool Whether only users with an active enrolment can be listed. */
    private bool $forcedactive = true;

    /** @var int[]|null Groups the listed users must belong to (null when there is no forced restriction). */
    private ?array $allowedgroupids = null;

    /** @var bool Whether the user is in separate groups mode without any group (nobody can be listed). */
    private bool $notingroup = false;

    /**
     * Private constructor, use {@see self::from_request()} or {@see self::from_array()}.
     */
    private function __construct() {
        $this->perpage = self::get_default_perpage();
    }

    /**
     * Builds the filter from the current request parameters (GET or POST).
     *
     * @return self
     */
    public static function from_request(): self {
        $filter = new self();
        $filter->searchfield = (string) optional_param('searchfield', self::FIELD_FULLNAME, PARAM_ALPHANUMEXT);
        $filter->search = trim((string) optional_param('search', '', PARAM_TEXT));
        $filter->roleid = (int) optional_param('roleid', 0, PARAM_INT);
        $filter->groupid = (int) optional_param('groupid', 0, PARAM_INT);
        $filter->status = (int) optional_param('status', self::STATUS_ANY, PARAM_INT);
        $filter->enrolid = (int) optional_param('enrolid', 0, PARAM_INT);
        $filter->accesssince = (int) optional_param('accesssince', 0, PARAM_INT);
        $filter->perpage = (int) optional_param('perpage', $filter->perpage, PARAM_INT);
        $filter->run = (bool) optional_param('run', false, PARAM_BOOL);
        if ($filter->searchfield === '') {
            $filter->searchfield = self::FIELD_FULLNAME;
        }
        return $filter;
    }

    /**
     * Builds the filter from an array using the same keys and types as the URL parameters.
     *
     * Missing keys get their default value.
     *
     * @param array $params Parameters, e.g. ['searchfield' => 'email', 'search' => 'abc', 'run' => 1].
     * @return self
     */
    public static function from_array(array $params): self {
        $filter = new self();
        if (array_key_exists('searchfield', $params)) {
            $filter->searchfield = (string) clean_param($params['searchfield'], PARAM_ALPHANUMEXT);
        }
        if (array_key_exists('search', $params)) {
            $filter->search = trim((string) clean_param($params['search'], PARAM_TEXT));
        }
        if (array_key_exists('roleid', $params)) {
            $filter->roleid = (int) clean_param($params['roleid'], PARAM_INT);
        }
        if (array_key_exists('groupid', $params)) {
            $filter->groupid = (int) clean_param($params['groupid'], PARAM_INT);
        }
        if (array_key_exists('status', $params)) {
            $filter->status = (int) clean_param($params['status'], PARAM_INT);
        }
        if (array_key_exists('enrolid', $params)) {
            $filter->enrolid = (int) clean_param($params['enrolid'], PARAM_INT);
        }
        if (array_key_exists('accesssince', $params)) {
            $filter->accesssince = (int) clean_param($params['accesssince'], PARAM_INT);
        }
        if (array_key_exists('perpage', $params)) {
            $filter->perpage = (int) clean_param($params['perpage'], PARAM_INT);
        }
        if (array_key_exists('run', $params)) {
            $filter->run = (bool) clean_param($params['run'], PARAM_BOOL);
        }
        if ($filter->searchfield === '') {
            $filter->searchfield = self::FIELD_FULLNAME;
        }
        return $filter;
    }

    /**
     * Returns a copy of this filter checked against the permissions of the current user.
     *
     * Values the user cannot use are reset to their defaults, and the restrictions core applies to
     * the participants page are computed:
     * - status and enrolment method filters require moodle/course:enrolreview;
     * - only active enrolments are listed unless the user has both moodle/course:enrolreview and
     *   moodle/course:viewsuspendedusers (as core_user\table\participants_search::get_status_sql());
     * - in separate groups mode without moodle/site:accessallgroups only members of the user's own groups
     *   are listed (and nobody when the user is not in any group);
     * - the last access filter is ignored when "lastaccess" is a hidden user field;
     * - perpage is clamped to [1, report_fastparticipants/maxperpage].
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @return self
     */
    public function normalise(\stdClass $course, \context_course $context): self {
        global $USER;

        $filter = clone($this);
        $filter->normalised = true;
        $filter->allowedgroupids = null;
        $filter->notingroup = false;

        // Search field.
        if (!array_key_exists($filter->searchfield, self::get_searchfield_options($context))) {
            $filter->searchfield = self::FIELD_FULLNAME;
        }

        // Role.
        if ($filter->roleid !== 0 && !array_key_exists($filter->roleid, self::get_role_options($context))) {
            $filter->roleid = 0;
        }

        // Groups, mirroring user/index.php and core_user\table\participants_search::get_enrolled_sql().
        if ($filter->groupid !== 0 && !array_key_exists($filter->groupid, self::get_group_options($course, $context))) {
            $filter->groupid = 0;
        }
        if (self::is_separate_groups_restricted($course, $context)) {
            $allowed = array_map('intval', array_keys(groups_get_all_groups($course->id, $USER->id)));
            if (empty($allowed)) {
                $filter->notingroup = true;
                $filter->groupid = 0;
            } else {
                if ($filter->groupid !== 0 && !in_array($filter->groupid, $allowed, true)) {
                    $filter->groupid = 0;
                }
                $filter->allowedgroupids = $allowed;
            }
        }

        // Enrolment status and method.
        $canreview = has_capability('moodle/course:enrolreview', $context);
        $filter->forcedactive = !($canreview && has_capability('moodle/course:viewsuspendedusers', $context));
        $validstatus = [self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_NOTCURRENT];
        if ($filter->forcedactive || !in_array($filter->status, $validstatus, true)) {
            $filter->status = self::STATUS_ANY;
        }
        if ($filter->enrolid !== 0 && !array_key_exists($filter->enrolid, self::get_enrol_options($course, $context))) {
            $filter->enrolid = 0;
        }

        // Last access.
        if (self::is_hidden_user_field('lastaccess', $context)) {
            $filter->accesssince = 0;
        } else if ($filter->accesssince < self::ACCESS_NEVER || $filter->accesssince > self::MAX_ACCESSSINCE_DAYS) {
            $filter->accesssince = 0;
        }

        // Rows per page.
        $filter->perpage = max(1, min($filter->perpage, self::get_max_perpage()));

        return $filter;
    }

    /**
     * Returns the URL parameters representing this filter: only non-default values, plus run=1 when running.
     *
     * @return array
     */
    public function to_url_params(): array {
        $params = [];
        if ($this->searchfield !== self::FIELD_FULLNAME) {
            $params['searchfield'] = $this->searchfield;
        }
        if ($this->search !== '') {
            $params['search'] = $this->search;
        }
        if ($this->roleid !== 0) {
            $params['roleid'] = $this->roleid;
        }
        if ($this->groupid !== 0) {
            $params['groupid'] = $this->groupid;
        }
        if ($this->status !== self::STATUS_ANY) {
            $params['status'] = $this->status;
        }
        if ($this->enrolid !== 0) {
            $params['enrolid'] = $this->enrolid;
        }
        if ($this->accesssince !== 0) {
            $params['accesssince'] = $this->accesssince;
        }
        if ($this->perpage !== self::get_default_perpage()) {
            $params['perpage'] = $this->perpage;
        }
        if ($this->run) {
            $params['run'] = 1;
        }
        return $params;
    }

    /**
     * Returns the searched field: {@see self::FIELD_FULLNAME} or an identity field name.
     *
     * @return string
     */
    public function get_searchfield(): string {
        return $this->searchfield;
    }

    /**
     * Returns the searched text (trimmed, empty when not searching).
     *
     * @return string
     */
    public function get_search(): string {
        return $this->search;
    }

    /**
     * Returns the role filter: 0 any, {@see self::ROLE_NONE} no role, or a role id.
     *
     * @return int
     */
    public function get_roleid(): int {
        return $this->roleid;
    }

    /**
     * Returns the group filter: 0 any, USERSWITHOUTGROUP (-1) no group, or a group id.
     *
     * @return int
     */
    public function get_groupid(): int {
        return $this->groupid;
    }

    /**
     * Returns the enrolment status filter, one of the STATUS_* constants.
     *
     * @return int
     */
    public function get_status(): int {
        return $this->status;
    }

    /**
     * Returns the enrolment instance filter: 0 any, or an enrol instance id of the course.
     *
     * @return int
     */
    public function get_enrolid(): int {
        return $this->enrolid;
    }

    /**
     * Returns the last access filter: 0 any, {@see self::ACCESS_NEVER}, or a number of days without access.
     *
     * @return int
     */
    public function get_accesssince(): int {
        return $this->accesssince;
    }

    /**
     * Returns the number of rows per page (clamped once normalised).
     *
     * @return int
     */
    public function get_perpage(): int {
        return $this->perpage;
    }

    /**
     * Whether the search must run (false means only the form is displayed and no user is queried).
     *
     * @return bool
     */
    public function is_run(): bool {
        return $this->run;
    }

    /**
     * Whether this instance was produced by {@see self::normalise()}.
     *
     * @return bool
     */
    public function is_normalised(): bool {
        return $this->normalised;
    }

    /**
     * Whether only users with an active enrolment may be listed (user lacks enrolreview or viewsuspendedusers).
     *
     * @return bool
     */
    public function is_forced_active(): bool {
        return $this->forcedactive;
    }

    /**
     * Returns the groups the listed users must belong to in separate groups mode.
     *
     * @return int[]|null Null when there is no forced group restriction.
     */
    public function get_allowed_groupids(): ?array {
        return $this->allowedgroupids;
    }

    /**
     * Whether the user is restricted by separate groups but does not belong to any group.
     *
     * In this case the page must show the core string 'notingroup' and list nobody.
     *
     * @return bool
     */
    public function is_notingroup(): bool {
        return $this->notingroup;
    }

    /**
     * Returns the fields the current user may search in: the full name and the visible identity fields.
     *
     * Identity fields require moodle/site:viewuseridentity, must be configured in showuseridentity and respect
     * hiddenuserfields / moodle/course:viewhiddenuserfields and the profile field visibility.
     *
     * @param \context_course $context The course context.
     * @return array field => label
     */
    public static function get_searchfield_options(\context_course $context): array {
        $options = [self::FIELD_FULLNAME => get_string('fullnameuser')];
        foreach (fields::get_identity_fields($context) as $field) {
            $options[$field] = fields::get_display_name($field);
        }
        return $options;
    }

    /**
     * Returns the role filter options: any, no role, viewable roles and, with moodle/role:assign, assignable roles.
     *
     * @param \context_course $context The course context.
     * @return array roleid => label (0 = any role, -1 = no role)
     */
    public static function get_role_options(\context_course $context): array {
        $options = [0 => get_string('anyrole', 'report_fastparticipants')];
        $options += [self::ROLE_NONE => get_string('noroles', 'role')];
        $options += get_viewable_roles($context, null, ROLENAME_BOTH);
        if (has_capability('moodle/role:assign', $context)) {
            $options += get_assignable_roles($context, ROLENAME_BOTH);
        }
        return $options;
    }

    /**
     * Returns the group filter options.
     *
     * Users that can see all groups get every visible group plus "No group"; in separate groups mode without
     * moodle/site:accessallgroups only the user's own groups are offered.
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @return array groupid => label (0 = any group, -1 = no group); empty when there is no group to offer
     */
    public static function get_group_options(\stdClass $course, \context_course $context): array {
        global $USER;

        if (self::is_separate_groups_restricted($course, $context)) {
            $groups = groups_get_all_groups($course->id, $USER->id);
            $nogroup = [];
        } else {
            $groups = groups_get_all_groups($course->id);
            $nogroup = [USERSWITHOUTGROUP => get_string('nogroup', 'group')];
        }
        if (empty($groups)) {
            return [];
        }

        $options = [0 => get_string('allparticipants')] + $nogroup;
        foreach ($groups as $group) {
            $options[(int) $group->id] = format_string($group->name, true, ['context' => $context]);
        }
        return $options;
    }

    /**
     * Returns the enrolment status filter options (requires moodle/course:enrolreview).
     *
     * Empty when the user cannot filter by status, including when only active enrolments can be listed.
     *
     * @param \context_course $context The course context.
     * @return array status => label (-1 = any status)
     */
    public static function get_status_options(\context_course $context): array {
        if (
            !has_capability('moodle/course:enrolreview', $context) ||
            !has_capability('moodle/course:viewsuspendedusers', $context)
        ) {
            return [];
        }
        return [
            self::STATUS_ANY => get_string('anystatus', 'report_fastparticipants'),
            self::STATUS_ACTIVE => get_string('participationactive', 'enrol'),
            self::STATUS_SUSPENDED => get_string('participationsuspended', 'enrol'),
            self::STATUS_NOTCURRENT => get_string('participationnotcurrent', 'enrol'),
        ];
    }

    /**
     * Returns the enrolment method filter options (requires moodle/course:enrolreview).
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @return array enrolid => label (0 = any method)
     */
    public static function get_enrol_options(\stdClass $course, \context_course $context): array {
        if (!has_capability('moodle/course:enrolreview', $context) || $course->id == SITEID) {
            return [];
        }
        $instances = enrol_get_instances($course->id, false);
        $plugins = enrol_get_plugins(false);
        $options = [];
        foreach ($instances as $instance) {
            if (!array_key_exists($instance->enrol, $plugins)) {
                continue;
            }
            $options[(int) $instance->id] = $plugins[$instance->enrol]->get_instance_name($instance);
        }
        if (empty($options)) {
            return [];
        }
        return [0 => get_string('anyenrolmethod', 'report_fastparticipants')] + $options;
    }

    /**
     * Returns the last access filter options, empty when "lastaccess" is hidden from the user.
     *
     * @param \context_course $context The course context.
     * @return array value => label (0 = any, N = not accessed for N days, -1 = never accessed)
     */
    public static function get_accesssince_options(\context_course $context): array {
        if (self::is_hidden_user_field('lastaccess', $context)) {
            return [];
        }

        $options = [0 => get_string('anylastaccess', 'report_fastparticipants')];
        $periods = [
            // Singular string, plural string, number of options, days per unit.
            ['numday', 'numdays', 6, 1],
            ['numweek', 'numweeks', 10, 7],
            ['nummonth', 'nummonths', 11, 30],
            ['numyear', 'numyears', 1, 365],
        ];
        foreach ($periods as [$singular, $plural, $count, $unitdays]) {
            for ($i = 1; $i <= $count; $i++) {
                $days = $i * $unitdays;
                if (array_key_exists($days, $options)) {
                    continue;
                }
                $period = get_string($i === 1 ? $singular : $plural, 'moodle', $i);
                $options[$days] = get_string('notaccessedfor', 'report_fastparticipants', $period);
            }
        }
        $options[self::ACCESS_NEVER] = get_string('accesssince_never', 'report_fastparticipants');
        return $options;
    }

    /**
     * Returns the rows per page options, limited by the maxperpage setting.
     *
     * @return array perpage => label
     */
    public static function get_perpage_options(): array {
        $max = self::get_max_perpage();
        $values = [10, 20, 50, 100, 200, 500, self::get_default_perpage(), $max];
        $values = array_unique(array_filter($values, static fn(int $value): bool => $value >= 1 && $value <= $max));
        sort($values);
        $options = [];
        foreach ($values as $value) {
            $options[$value] = (string) $value;
        }
        return $options;
    }

    /**
     * Returns the hard limit of rows per page (admin setting report_fastparticipants/maxperpage).
     *
     * @return int
     */
    public static function get_max_perpage(): int {
        $max = (int) get_config('report_fastparticipants', 'maxperpage');
        return $max > 0 ? $max : self::DEFAULT_MAXPERPAGE;
    }

    /**
     * Returns the effective maximum number of users that "select all" can select.
     *
     * It is the admin setting report_fastparticipants/maxselectall (5000 when unset, 0 disables "select all"),
     * lowered so the submitted bulk form fits in PHP max_input_vars: the selected ids are posted as one input
     * each, and a margin of 50 inputs is kept for sesskey, id, returnto, formaction and the other fixed fields.
     *
     * @return int
     */
    public static function get_max_selectall(): int {
        $value = get_config('report_fastparticipants', 'maxselectall');
        $max = ($value === false || $value === '') ? self::DEFAULT_MAXSELECTALL : max(0, (int) $value);
        $maxinput = (int) ini_get('max_input_vars');
        if ($maxinput > 0) {
            $max = min($max, max(0, $maxinput - 50));
        }
        return $max;
    }

    /**
     * Returns the default rows per page (core setting moodlecourse/participantsperpage), within the hard limit.
     *
     * @return int
     */
    public static function get_default_perpage(): int {
        $perpage = (int) get_config('moodlecourse', 'participantsperpage');
        if ($perpage <= 0) {
            $perpage = self::DEFAULT_PERPAGE;
        }
        return min($perpage, self::get_max_perpage());
    }

    /**
     * Whether a user field is hidden from the current user (hiddenuserfields without viewhiddenuserfields).
     *
     * @param string $field User field name, e.g. 'lastaccess' or 'groups'.
     * @param \context_course $context The course context.
     * @return bool
     */
    public static function is_hidden_user_field(string $field, \context_course $context): bool {
        global $CFG;

        $hidden = array_map('trim', explode(',', (string) ($CFG->hiddenuserfields ?? '')));
        if (!in_array($field, $hidden, true)) {
            return false;
        }
        return !has_capability('moodle/course:viewhiddenuserfields', $context);
    }

    /**
     * Whether the current user only sees members of their own groups (separate groups without accessallgroups).
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @return bool
     */
    private static function is_separate_groups_restricted(\stdClass $course, \context_course $context): bool {
        return (int) $course->groupmode === SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context);
    }
}
