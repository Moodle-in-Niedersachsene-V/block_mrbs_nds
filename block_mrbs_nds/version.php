<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

$plugin            = new stdClass();
$plugin->version   = 2026062732;          // YYYYMMDDNN – increment NN for same-day releases
$plugin->requires  = 2024100700;          // Moodle 4.5+ (minimum tested); Moodle 5.0 recommended
$plugin->component = 'block_mrbs_nds';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.0.0 (Build: 2026062732)';
$plugin->cron      = 0;                   // Moodle 5: must be explicitly 0 (cron replaced by scheduled task)

// Migration: this plugin supersedes block_mrbs_rlp.
// Data in block_mrbs_rlp_* tables will be migrated via db/upgrade.php.
