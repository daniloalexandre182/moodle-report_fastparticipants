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
 * Participants table of the fast participants report.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\table;

use core_user\fields;
use html_writer;
use moodle_url;
use report_fastparticipants\local\filter;
use report_fastparticipants\local\participants_search;
use report_fastparticipants\local\row_loader;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Server rendered (non dynamic) participants table.
 *
 * Same columns and cell markup as core_user\table\participants, but the data comes from
 * {@see participants_search} (count, ids of the page, users of the page) and the per row data from a single
 * {@see row_loader} batch, so the number of queries does not depend on the number of rows. Sorting and
 * paging use the usual GET parameters of flexible_table (tsort, tdir, page), so every page can be bookmarked.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participants_table extends \core_table\sql_table {
    /** Toggle group of the user checkboxes (DOM contract with the JS). */
    public const TOGGLE_GROUP = 'report-fastparticipants-table';

    /** @var \stdClass The course. */
    protected \stdClass $course;

    /** @var \context_course The course context. */
    protected \context_course $context;

    /** @var filter The normalised filter. */
    protected filter $filter;

    /** @var participants_search The search. */
    protected participants_search $search;

    /** @var row_loader The loader of roles, groups and enrolments of the displayed users. */
    protected row_loader $loader;

    /** @var string[] Identity fields displayed as columns. */
    protected array $identityfields = [];

    /** @var string[] Country names, code => name (loaded only when the country column is displayed). */
    protected array $countries = [];

    /** @var bool Whether the current user can see the full names (moodle/site:viewfullnames). */
    protected bool $canviewfullnames = false;

    /** @var int Total number of matching users (known once the table was queried). */
    protected int $total = 0;

    /**
     * Constructor.
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @param filter $filter The normalised filter.
     * @param participants_search $search The search built with the same filter.
     * @param row_loader $loader The row loader.
     * @param moodle_url $baseurl Base URL of the table (page URL with the filter params, without sort/page params).
     */
    public function __construct(
        \stdClass $course,
        \context_course $context,
        filter $filter,
        participants_search $search,
        row_loader $loader,
        moodle_url $baseurl
    ) {
        parent::__construct(self::get_uniqueid_for_course((int) $course->id));
        $this->course = $course;
        $this->context = $context;
        $this->filter = $filter;
        $this->search = $search;
        $this->loader = $loader;
        $this->canviewfullnames = has_capability('moodle/site:viewfullnames', $context);

        $this->define_baseurl($baseurl);
        $this->define_table_columns();

        $this->is_downloadable(false);
        $this->initialbars(false);
        $this->collapsible(false);
        $this->set_attribute('id', 'report-fastparticipants-participants');
    }

    /**
     * Returns the unique id of the table of a course (also used by the JS, see the DOM contract).
     *
     * @param int $courseid Course id.
     * @return string
     */
    public static function get_uniqueid_for_course(int $courseid): string {
        return 'report-fastparticipants-' . $courseid;
    }

    /**
     * Defines the columns, the headers and the sorting of the table.
     *
     * @return void
     */
    protected function define_table_columns(): void {
        global $OUTPUT;

        $columns = [];
        $headers = [];

        $mastercheckbox = new \core\output\checkbox_toggleall(self::TOGGLE_GROUP, true, [
            'id' => 'report-fastparticipants-selectall-page',
            'label' => get_string('selectall'),
            'labelclasses' => 'sr-only',
            'classes' => 'm-1',
            'checked' => false,
        ]);
        $columns[] = 'select';
        $headers[] = $OUTPUT->render($mastercheckbox);

        $columns[] = 'fullname';
        $headers[] = get_string('fullnameuser');

        $this->identityfields = fields::get_identity_fields($this->context);
        foreach ($this->identityfields as $field) {
            $columns[] = $field;
            $headers[] = fields::get_display_name($field);
        }
        if (in_array('country', $this->identityfields, true)) {
            $this->countries = get_string_manager()->get_list_of_countries(true);
        }

        $columns[] = 'roles';
        $headers[] = get_string('roles');

        if (!filter::is_hidden_user_field('groups', $this->context)) {
            $columns[] = 'groups';
            $headers[] = get_string('groups');
        }

        if (!filter::is_hidden_user_field('lastaccess', $this->context)) {
            $columns[] = 'lastaccess';
            $headers[] = get_string('lastcourseaccess');
        }

        if (has_capability('moodle/course:enrolreview', $this->context)) {
            $columns[] = 'status';
            $headers[] = get_string('participationstatus', 'enrol');
            $columns[] = 'timeend';
            $headers[] = get_string('enroltimeend', 'enrol');
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_header_column('fullname');

        // Only whitelisted columns can be sorted; the default sort is the last name, as core.
        $this->sortable(true, 'lastname', SORT_ASC);
        $sortable = participants_search::get_sortable_columns($this->context);
        foreach (array_merge($columns, fields::get_name_fields()) as $column) {
            if (!in_array($column, $sortable, true)) {
                $this->no_sorting($column);
            }
        }
    }

    /**
     * Queries the users of the current page: count, ids of the page, users, then the per row data in batch.
     *
     * @param int $pagesize Size of page for paginated displayed table.
     * @param bool $useinitialsbar Ignored, the initials bar is not used.
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = false) {
        $this->total = $this->search->count();
        $this->pagesize((int) $pagesize, $this->total);

        // Stay on the last page when the requested page is out of range (e.g. after changing perpage).
        $pagesize = max(1, (int) $this->get_page_size());
        $lastpage = $this->total > 0 ? intdiv($this->total - 1, $pagesize) : 0;
        if ($this->currpage > $lastpage) {
            $this->set_page_number($lastpage + 1); // The method is 1-based.
        }
        if ($this->currpage < 0) {
            $this->set_page_number(1);
        }

        $userids = $this->search->get_page_userids(
            $this->get_sort_columns(),
            (int) $this->get_page_start(),
            (int) $this->get_page_size()
        );
        $users = $this->search->get_users($userids);
        $this->loader->load(array_keys($users));
        $this->rawdata = $users;
    }

    /**
     * Returns the current sort columns (column => SORT_ASC|SORT_DESC), setting the table up when needed.
     *
     * Used to export the whole list in the same order as the table.
     *
     * @return array
     */
    public function get_current_sort_columns(): array {
        if (!$this->setup) {
            $this->setup();
        }
        return $this->get_sort_columns();
    }

    /**
     * Returns the total number of matching users (0 before the table is rendered).
     *
     * @return int
     */
    public function get_total(): int {
        return $this->total;
    }

    /**
     * Returns the number of users displayed on the current page (0 before the table is rendered).
     *
     * @return int
     */
    public function get_displayed_count(): int {
        return is_array($this->rawdata) ? count($this->rawdata) : 0;
    }

    /**
     * Renders the table inside the wrapper expected by the JS (see the DOM contract).
     *
     * @param int $pagesize Rows per page.
     * @return string
     */
    public function render(int $pagesize): string {
        ob_start();
        $this->out($pagesize, false);
        $html = ob_get_clean();

        return html_writer::div($html, 'report-fastparticipants-table', [
            'data-region' => 'report_fastparticipants/table',
            'data-course-id' => $this->course->id,
            'data-context-id' => $this->context->id,
            'data-table-uniqueid' => $this->uniqueid,
            'data-total' => $this->total,
        ]);
    }

    /**
     * Returns the context of the table (used by flexible_table for the full name sort links).
     *
     * @return \core\context
     */
    public function get_context(): \core\context {
        return $this->context;
    }

    /**
     * Generates the select column (slave checkbox of the toggle group).
     *
     * @param \stdClass $data The user.
     * @return string
     */
    public function col_select($data) {
        global $OUTPUT;

        $checkbox = new \core\output\checkbox_toggleall(self::TOGGLE_GROUP, false, [
            'classes' => 'usercheckbox m-1',
            'id' => 'user' . $data->id,
            'name' => 'user' . $data->id,
            'value' => 1,
            'checked' => false,
            'label' => get_string('selectitem', 'moodle', s(fullname($data, $this->canviewfullnames))),
            'labelclasses' => 'accesshide',
        ]);
        return $OUTPUT->render($checkbox);
    }

    /**
     * Generates the full name column (picture and name linked to the course profile), as core.
     *
     * @param \stdClass $data The user.
     * @return string
     */
    public function col_fullname($data) {
        global $OUTPUT;

        return $OUTPUT->render(\core_user::get_profile_picture($data, $this->context, [
            'courseid' => $this->course->id,
            'includefullname' => true,
        ]));
    }

    /**
     * Generates the roles column (inline editable).
     *
     * @param \stdClass $data The user.
     * @return string
     */
    public function col_roles($data) {
        global $OUTPUT;

        $editable = $this->loader->get_user_roles_editable($data);
        return $OUTPUT->render_from_template('core/inplace_editable', $editable->export_for_template($OUTPUT));
    }

    /**
     * Generates the groups column (inline editable).
     *
     * @param \stdClass $data The user.
     * @return string
     */
    public function col_groups($data) {
        global $OUTPUT;

        $editable = $this->loader->get_user_groups_editable($data);
        return $OUTPUT->render_from_template('core/inplace_editable', $editable->export_for_template($OUTPUT));
    }

    /**
     * Generates the course last access column.
     *
     * @param \stdClass $data The user.
     * @return string
     */
    public function col_lastaccess($data) {
        if (!empty($data->lastaccess)) {
            return format_time(time() - (int) $data->lastaccess);
        }
        return get_string('never');
    }

    /**
     * Generates the enrolment status column (one status field per enrolment), as core.
     *
     * @param \stdClass $data The user.
     * @return string
     */
    public function col_status($data) {
        global $OUTPUT;

        $output = '';
        foreach ($this->loader->get_status_fields($data) as $statusfield) {
            $output .= $OUTPUT->render_from_template('core_user/status_field', $statusfield->export_for_template($OUTPUT));
        }
        return $output;
    }

    /**
     * Generates the "Enrolment ends" column: one end date per enrolment, with an edit link when allowed.
     *
     * The link opens the report_fastparticipants\form\enrolment_timeend_form modal (amd timeend_field).
     *
     * @param \stdClass $data The user.
     * @return string
     */
    public function col_timeend($data) {
        global $OUTPUT;

        $items = $this->loader->get_timeend_items((int) $data->id);
        if (empty($items)) {
            return '';
        }
        $fullname = fullname($data, $this->canviewfullnames);
        $showinstance = count($items) > 1;
        $format = get_string('strftimedatetimeshort', 'langconfig');

        $output = '';
        foreach ($items as $item) {
            $content = $item['timeend']
                ? html_writer::span(userdate($item['timeend'], $format))
                : html_writer::span(get_string('noenrolmentend', 'report_fastparticipants'), 'text-muted');
            if ($showinstance) {
                $content .= ' ' . html_writer::tag('small', '(' . s($item['instancename']) . ')', ['class' => 'text-muted']);
            }
            if ($item['canedit']) {
                $label = get_string('edittimeendfor', 'report_fastparticipants', (object) [
                    'fullname' => $fullname,
                    'instance' => $item['instancename'],
                ]);
                $content .= html_writer::link('#', $OUTPUT->pix_icon('t/edit', $label), [
                    'class' => 'ml-1',
                    'role' => 'button',
                    'data-action' => 'report_fastparticipants/edittimeend',
                    'data-ueid' => $item['ueid'],
                    // Escaped as in the core status field: the name is used in HTML strings by the JS.
                    'data-fullname' => s($fullname),
                ]);
            }
            $output .= html_writer::div($content, 'report-fastparticipants-timeend text-nowrap', [
                'data-region' => 'report_fastparticipants/timeend',
            ]);
        }
        return $output;
    }

    /**
     * Generates the identity field columns.
     *
     * @param string $column The column name.
     * @param \stdClass $row The user.
     * @return string
     */
    public function other_cols($column, $row) {
        if (!in_array($column, $this->identityfields, true)) {
            return '';
        }
        $value = (string) ($row->{$column} ?? '');
        if ($column === 'country') {
            return s($this->countries[$value] ?? $value);
        }
        return s($value);
    }
}
