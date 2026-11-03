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
 * Unit tests for \report_fastparticipants\table\participants_table.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\table;

use report_fastparticipants\local\filter;
use report_fastparticipants\local\participants_search;
use report_fastparticipants\local\row_loader;

/**
 * Unit tests for \report_fastparticipants\table\participants_table.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\table\participants_table
 */
final class participants_table_test extends \advanced_testcase {
    /**
     * Builds the table of a course with 5 students and 2 rows per page, already set up.
     *
     * @return participants_table
     */
    private function make_table(): participants_table {
        global $PAGE;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->create_and_enrol($course, 'student');
        }
        $context = \context_course::instance($course->id);
        $baseurl = new \moodle_url('/report/fastparticipants/index.php', ['id' => $course->id, 'run' => 1]);
        $PAGE->set_url($baseurl);

        $filter = filter::from_array(['run' => 1, 'perpage' => 2])->normalise($course, $context);
        $search = new participants_search($course, $context, $filter);
        $table = new participants_table($course, $context, $filter, $search, new row_loader($course, $context), $baseurl);
        $table->setup();
        return $table;
    }

    /**
     * A page beyond the last one shows the last page (flexible_table::set_page_number() is 1-based).
     */
    public function test_out_of_range_page_shows_last_page(): void {
        $this->resetAfterTest();
        $table = $this->make_table();

        $table->set_page_number(10);
        $table->query_db(2);

        $this->assertSame(5, $table->get_total());
        $this->assertSame(4, $table->get_page_start());
        $this->assertSame(1, $table->get_displayed_count());
        $this->assertCount(1, $table->rawdata);
    }

    /**
     * A page before the first one shows the first page.
     */
    public function test_negative_page_shows_first_page(): void {
        $this->resetAfterTest();
        $table = $this->make_table();

        $table->set_page_number(-3);
        $table->query_db(2);

        $this->assertSame(0, $table->get_page_start());
        $this->assertSame(2, $table->get_displayed_count());
    }

    /**
     * A page in range is kept.
     */
    public function test_page_in_range_is_kept(): void {
        $this->resetAfterTest();
        $table = $this->make_table();

        $table->set_page_number(2);
        $table->query_db(2);

        $this->assertSame(2, $table->get_page_start());
        $this->assertSame(2, $table->get_displayed_count());
    }
}
