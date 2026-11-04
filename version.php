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
 * Version details.
 *
 * @package    report_fastparticipants
 * @copyright  2026 Danilo Machado <danilo.alexandre182@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026110400;                // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2025100600;               // Requires this Moodle version (5.1).
$plugin->component = 'report_fastparticipants'; // Full name of the plugin (used for diagnostics).
$plugin->supported = [501, 501];               // Supported Moodle versions (branch numbers).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';
