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
 * Tests for the report_fastparticipants events.
 *
 * @package    report_fastparticipants
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\event;

/**
 * Tests for the report_fastparticipants events.
 *
 * @package    report_fastparticipants
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\event\report_viewed
 */
final class events_test extends \advanced_testcase {
    /**
     * Test the report viewed event.
     */
    public function test_report_viewed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $event = report_viewed::create(['context' => $context]);

        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(report_viewed::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($course->id, $event->courseid);
        $this->assertEquals('r', $event->crud);
        $this->assertEquals(\core\event\base::LEVEL_TEACHING, $event->edulevel);

        $url = new \moodle_url('/report/fastparticipants/index.php', ['id' => $course->id]);
        $this->assertEquals($url, $event->get_url());

        $this->assertSame(get_string('eventreportviewed', 'report_fastparticipants'), report_viewed::get_name());
        $this->assertStringContainsString((string) $course->id, $event->get_description());
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Creating the event in a context other than a course context must throw a coding_exception.
     */
    public function test_report_viewed_with_invalid_context(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_COURSE.');
        report_viewed::create(['context' => \context_system::instance()]);
    }
}
