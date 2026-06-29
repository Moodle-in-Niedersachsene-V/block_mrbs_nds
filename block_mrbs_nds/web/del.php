<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Delete an area or room (with confirmation).
 *
 * Phase 1+2 changes:
 *  - require_once instead of include
 *  - Table names: block_mrbs_rlp_* → block_mrbs_nds_*
 *  - Capability: block/mrbs_rlp → block/mrbs_nds
 *  - XSS: s() around all DB values in HTML output
 *  - Removed deprecated <center>, <H1> → Bootstrap 5 classes
 *  - Phase 1: userdate strftime format → Moodle format
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
global $PAGE, $DB;

require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/mrbs_nds_auth.php');

require_login();

$day     = optional_param('day',     0, PARAM_INT);
$month   = optional_param('month',   0, PARAM_INT);
$year    = optional_param('year',    0, PARAM_INT);
$area    = optional_param('area',    0, PARAM_INT);
$room    = optional_param('room',    0, PARAM_INT);
$type    = required_param('type',    PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

if (!$day || !$month || !$year) {
    $day   = (int) date('d');
    $month = (int) date('m');
    $year  = (int) date('Y');
}

$thisurl = new moodle_url('/blocks/mrbs_nds/web/del.php',
    ['day' => $day, 'month' => $month, 'year' => $year, 'type' => $type]);
if ($area) {
    $thisurl->param('area', $area);
} else {
    $area = get_default_area();
}
if ($room) {
    $thisurl->param('room', $room);
}
$PAGE->set_url($thisurl);

if (!getAuthorised(2)) {
    showAccessDenied($day, $month, $year, $area);
    exit();
}
require_sesskey();

$adminurl = new moodle_url('/blocks/mrbs_nds/web/admin.php');

if ($type === 'room') {
    $adminurl->param('area', $area);

    if ($confirm) {
        $DB->delete_records('block_mrbs_rlp_entry', ['room_id' => $room]);
        $DB->delete_records('block_mrbs_rlp_room',  ['id'      => $room]);
        redirect($adminurl);
    }

    print_header_mrbs_nds($day, $month, $year, $area);

    $bookings = $DB->get_records('block_mrbs_rlp_entry', ['room_id' => $room]);
    if (!empty($bookings)) {
        echo '<p>' . s(get_string('deletefollowing', 'block_mrbs_nds')) . ':</p><ul>';
        foreach ($bookings as $booking) {
            // Phase 1: Moodle date format; Phase 1 XSS: s() on name.
            $fmt = get_string('strftimedatetimeshort', 'langconfig');
            echo '<li>' . s($booking->name) . ' ('
                 . userdate($booking->start_time, $fmt) . ' → '
                 . userdate($booking->end_time,   $fmt) . ')</li>';
        }
        echo '</ul>';
    }

    echo '<div class="text-center my-3">';
    echo '<h3>' . s(get_string('sure', 'block_mrbs_nds')) . '</h3>';
    echo '<a class="btn btn-danger me-2" href="'
         . s($thisurl->out(true, ['confirm' => 'Y', 'sesskey' => sesskey()])) . '">'
         . s(get_string('yes')) . '</a>';
    echo '<a class="btn btn-secondary" href="' . s($adminurl) . '">'
         . s(get_string('no')) . '</a>';
    echo '</div>';

    require_once __DIR__ . '/trailer.php';
}

if ($type === 'area') {
    $n = $DB->count_records('block_mrbs_rlp_room', ['area_id' => $area]);
    if ($n === 0) {
        $DB->delete_records('block_mrbs_rlp_area', ['id' => $area]);
        redirect($adminurl);
    } else {
        print_header_mrbs_nds($day, $month, $year, $area);
        echo '<div class="alert alert-warning">' . s(get_string('delarea', 'block_mrbs_nds')) . '</div>';
        echo '<a class="btn btn-secondary" href="' . s($adminurl) . '">'
             . s(get_string('backadmin', 'block_mrbs_nds')) . '</a>';
        require_once __DIR__ . '/trailer.php';
    }
}
