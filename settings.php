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
 * Admin settings for the fast participants report.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The $settings page under Reports is created automatically by core for report plugins.
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'report_fastparticipants/replaceparticipants',
        get_string('replaceparticipants', 'report_fastparticipants'),
        get_string('replaceparticipants_desc', 'report_fastparticipants'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'report_fastparticipants/maxperpage',
        get_string('maxperpage', 'report_fastparticipants'),
        get_string('maxperpage_desc', 'report_fastparticipants'),
        500,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'report_fastparticipants/maxselectall',
        get_string('maxselectall', 'report_fastparticipants'),
        get_string('maxselectall_desc', 'report_fastparticipants'),
        5000,
        PARAM_INT
    ));
}
