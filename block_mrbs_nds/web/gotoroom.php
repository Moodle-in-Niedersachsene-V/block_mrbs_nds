<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Redirect to the day view for a given room name.
 * Phase 1+2: table names, require_once, require_login added.
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
global $PAGE;
require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/mrbs_nds_auth.php');
require_once(__DIR__ . '/mrbs_nds_sql.php');

$room  = required_param('room', PARAM_TEXT);
$day   = optional_param('day',   0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year  = optional_param('year',  0, PARAM_INT);

if (!$day || !$month || !$year) {
    $day   = (int) date('d');
    $month = (int) date('m');
    $year  = (int) date('Y');
} else {
    while (!checkdate($month, $day, $year)) {
        $day--;
    }
}

$thisurl = new moodle_url('/blocks/mrbs_nds/web/gotoroom.php',
    ['day' => $day, 'month' => $month, 'year' => $year, 'room' => $room]);
$PAGE->set_url($thisurl);

require_login();
if (!getAuthorised(1)) {
    showAccessDenied($day, $month, $year, null);
    exit();
}

$sql  = "SELECT r.area_id, a.area_name
           FROM {block_mrbs_nds_room} r
           JOIN {block_mrbs_nds_area} a ON a.id = r.area_id
          WHERE r.room_name = ? OR r.room_name = ?";
$area = $DB->get_record_sql($sql, [$room, '0' . $room], IGNORE_MULTIPLE);

if ($area) {
    redirect(new moodle_url('/blocks/mrbs_nds/web/day.php',
        ['day' => $day, 'month' => $month, 'year' => $year, 'area' => $area->area_id]));
} else {
    redirect(new moodle_url('/blocks/mrbs_nds/web/day.php',
        ['day' => $day, 'month' => $month, 'year' => $year, 'roomnotfound' => s($room)]));
}
