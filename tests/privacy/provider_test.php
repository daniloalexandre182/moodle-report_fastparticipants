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
 * Privacy provider tests for report_fastparticipants.
 *
 * @package    report_fastparticipants
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_fastparticipants\privacy;

use core_privacy\local\metadata\null_provider;
use core_privacy\manager;

/**
 * Privacy provider tests for report_fastparticipants.
 *
 * @package    report_fastparticipants
 * @category   test
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_fastparticipants\privacy\provider
 */
class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * The provider must be a null provider whose reason is an existing language string.
     */
    public function test_get_reason(): void {
        $this->assertTrue(is_subclass_of(provider::class, null_provider::class));

        $reason = provider::get_reason();
        $this->assertIsString($reason);
        $this->assertSame('privacy:metadata', $reason);
        $this->assertTrue(get_string_manager()->string_exists($reason, 'report_fastparticipants'));
        $this->assertNotEmpty(get_string($reason, 'report_fastparticipants'));
        $this->assertDebuggingNotCalled();
    }

    /**
     * The privacy manager must detect the provider and consider the component compliant.
     */
    public function test_component_is_compliant(): void {
        $this->assertSame(provider::class, manager::get_provider_classname_for_component('report_fastparticipants'));

        $manager = new manager();
        $this->assertTrue($manager->component_is_compliant('report_fastparticipants'));
        $this->assertSame('privacy:metadata', $manager->get_null_provider_reason('report_fastparticipants'));
    }
}
