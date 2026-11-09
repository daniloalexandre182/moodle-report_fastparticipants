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
 * Export of the whole filtered participants list of the fast participants report.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\local;

use core_user\fields;

/**
 * Streams the whole filtered participants list with a dataformat plugin (CSV, Excel, ...).
 *
 * The matching user ids are read with a single recordset and processed in chunks: for each chunk the users
 * are loaded with {@see participants_search::get_users()} and their roles, groups and enrolments with one
 * {@see row_loader::load()} call, so memory and the number of queries per chunk stay constant.
 * The columns follow the table: identity fields, hidden user fields (groups, last access) and the enrolment
 * status (moodle/course:enrolreview) are respected.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exporter {
    /** Number of users loaded at once. */
    public const CHUNK_SIZE = 500;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \context_course The course context. */
    private \context_course $context;

    /** @var participants_search The search (built with a normalised filter). */
    private participants_search $search;

    /** @var array Sort columns, column => SORT_ASC|SORT_DESC. */
    private array $sortcolumns;

    /** @var row_loader Loader of roles, groups and enrolments. */
    private row_loader $loader;

    /** @var string[] Identity fields exported. */
    private array $identityfields;

    /** @var bool Whether the groups column is exported. */
    private bool $showgroups;

    /** @var bool Whether the last access column is exported. */
    private bool $showlastaccess;

    /** @var bool Whether the enrolment status column is exported. */
    private bool $showstatus;

    /** @var bool Whether the current user can see the full names. */
    private bool $canviewfullnames;

    /** @var string[] Country names, code => name (loaded only when the country is exported). */
    private array $countries = [];

    /**
     * Constructor.
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @param participants_search $search The search (built with a normalised filter).
     * @param array $sortcolumns Sort columns, column => SORT_ASC|SORT_DESC (e.g. the sort of the table).
     */
    public function __construct(\stdClass $course, \context_course $context, participants_search $search, array $sortcolumns = []) {
        $this->course = $course;
        $this->context = $context;
        $this->search = $search;
        $this->sortcolumns = $sortcolumns;
        $this->loader = new row_loader($course, $context);

        $this->identityfields = fields::get_identity_fields($context);
        $this->showgroups = !filter::is_hidden_user_field('groups', $context);
        $this->showlastaccess = !filter::is_hidden_user_field('lastaccess', $context);
        $this->showstatus = has_capability('moodle/course:enrolreview', $context);
        $this->canviewfullnames = has_capability('moodle/site:viewfullnames', $context);
        if (in_array('country', $this->identityfields, true)) {
            $this->countries = get_string_manager()->get_list_of_countries(true);
        }
    }

    /**
     * Returns the enabled dataformat plugins.
     *
     * @return string[] Dataformat names.
     */
    public static function get_enabled_dataformats(): array {
        return array_values(\core_plugin_manager::instance()->get_enabled_plugins('dataformat') ?? []);
    }

    /**
     * Whether a dataformat plugin exists and is enabled.
     *
     * @param string $dataformat Dataformat name, e.g. 'csv'.
     * @return bool
     */
    public static function is_valid_dataformat(string $dataformat): bool {
        return $dataformat !== '' && in_array($dataformat, self::get_enabled_dataformats(), true);
    }

    /**
     * Returns the exported columns.
     *
     * @return array column key => header
     */
    public function get_columns(): array {
        $columns = ['fullname' => get_string('fullnameuser')];
        foreach ($this->identityfields as $field) {
            $columns[$field] = fields::get_display_name($field);
        }
        $columns['roles'] = get_string('roles');
        if ($this->showgroups) {
            $columns['groups'] = get_string('groups');
        }
        if ($this->showlastaccess) {
            $columns['lastaccess'] = get_string('lastcourseaccess');
        }
        if ($this->showstatus) {
            $columns['status'] = get_string('participationstatus', 'enrol');
            $columns['timeend'] = get_string('enroltimeend', 'enrol');
        }
        return $columns;
    }

    /**
     * Returns the exported rows (plain text values keyed as {@see self::get_columns()}).
     *
     * @return \Generator Yields one array per user.
     */
    public function get_rows(): \Generator {
        $rs = $this->search->get_userids_recordset($this->sortcolumns);
        try {
            $chunk = [];
            foreach ($rs as $record) {
                $chunk[] = (int) $record->id;
                if (count($chunk) >= self::CHUNK_SIZE) {
                    yield from $this->get_chunk_rows($chunk);
                    $chunk = [];
                }
            }
            if (!empty($chunk)) {
                yield from $this->get_chunk_rows($chunk);
            }
        } finally {
            $rs->close();
        }
    }

    /**
     * Streams the export to the browser (the caller must stop the script afterwards).
     *
     * @param string $dataformat Dataformat name, e.g. 'csv'.
     * @return void
     * @throws \moodle_exception When the dataformat is not enabled.
     */
    public function download(string $dataformat): void {
        if (!self::is_valid_dataformat($dataformat)) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }

        \core\dataformat::download_data(
            $this->get_filename(),
            $dataformat,
            $this->get_columns(),
            $this->get_rows(),
            static function (array $row, bool $supportshtml): array {
                if ($supportshtml) {
                    $row = array_map('s', $row);
                }
                return $row;
            }
        );
    }

    /**
     * Returns the file name of the export (without extension).
     *
     * @return string
     */
    public function get_filename(): string {
        $shortname = self::plain(format_string($this->course->shortname, true, ['context' => $this->context]));
        return clean_filename($shortname . ' ' . get_string('participants'));
    }

    /**
     * Returns the rows of a chunk of users.
     *
     * @param int[] $userids User ids, in display order.
     * @return \Generator Yields one array per user.
     */
    private function get_chunk_rows(array $userids): \Generator {
        $users = $this->search->get_users($userids);
        $this->loader->load(array_keys($users));
        foreach ($users as $user) {
            yield $this->get_row($user);
        }
    }

    /**
     * Builds the row of a user (the row loader must have loaded the user).
     *
     * @param \stdClass $user The user, as returned by {@see participants_search::get_users()}.
     * @return array column key => plain text value
     */
    private function get_row(\stdClass $user): array {
        $row = ['fullname' => fullname($user, $this->canviewfullnames)];
        foreach ($this->identityfields as $field) {
            $value = (string) ($user->{$field} ?? '');
            if ($field === 'country') {
                $value = $this->countries[$value] ?? $value;
            }
            $row[$field] = $value;
        }
        $row['roles'] = self::implode_plain($this->loader->get_role_names((int) $user->id));
        if ($this->showgroups) {
            $row['groups'] = self::implode_plain($this->loader->get_group_names((int) $user->id));
        }
        if ($this->showlastaccess) {
            $row['lastaccess'] = !empty($user->lastaccess) ? userdate((int) $user->lastaccess) : get_string('never');
        }
        if ($this->showstatus) {
            $row['status'] = self::implode_plain($this->loader->get_status_texts((int) $user->id));
            $row['timeend'] = self::implode_plain($this->loader->get_timeend_texts((int) $user->id));
        }
        return $row;
    }

    /**
     * Joins formatted (HTML escaped) names into one plain text value.
     *
     * @param string[] $values Values formatted with format_string().
     * @return string
     */
    private static function implode_plain(array $values): string {
        return implode(', ', array_map([self::class, 'plain'], $values));
    }

    /**
     * Converts a value formatted for HTML (format_string()) into plain text.
     *
     * @param string $value The formatted value.
     * @return string
     */
    private static function plain(string $value): string {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
