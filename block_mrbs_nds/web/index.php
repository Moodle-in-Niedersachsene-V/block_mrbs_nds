<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Entry point: redirect to the configured default calendar view.
 *
 * Phase 2 addition: require_login() + require_capability() added.
 * Phase 1: table names and config component updated.
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(__DIR__ . '/config.inc.php');

global $DB;

// Phase 2: require login on entry point (was missing).
require_login();
$context = context_system::instance();
require_capability('block/mrbs_nds:viewmrbs', $context);

$day   = (int) date('d');
$month = (int) date('m');
$year  = (int) date('Y');

switch ($default_view) {
    case 'month':
        $redirect = new moodle_url('/blocks/mrbs_nds/web/month.php',
            ['year' => $year, 'month' => $month]);
        break;
    case 'week':
        $redirect = new moodle_url('/blocks/mrbs_nds/web/week.php',
            ['year' => $year, 'month' => $month, 'day' => $day]);
        break;
    default:
        $redirect = new moodle_url('/blocks/mrbs_nds/web/day.php',
            ['day' => $day, 'month' => $month, 'year' => $year]);
}

if (!empty($default_room)) {
    $res = $DB->get_record('block_mrbs_nds_room', ['id' => $default_room]);
    if (!empty($res)) {
        $redirect->params(['area' => $res->area_id, 'room' => $default_room]);
    }
}

redirect($redirect);
