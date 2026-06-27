<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Form handler: create or update a booking entry.
 *
 * Phase 1+2 changes vs. edit_entry_handler.php:
 *  - require_once instead of include                                  [Phase 2]
 *  - Table names: block_mrbs_rlp_* → block_mrbs_nds_*               [Phase 1]
 *  - Capability strings: block/mrbs_rlp → block/mrbs_nds            [Phase 1]
 *  - Event class: block_mrbs_rlp → block_mrbs_nds                   [Phase 1]
 *  - Lang strings: block_mrbs_rlp → block_mrbs_nds                  [Phase 1]
 *  - XSS: s() on ALL hidden form values                              [Phase 1]
 *  - Phase 1: authGetUserLevel() now takes int (user.id)             [Phase 1]
 *  - Phase 1: getWritable() compares int IDs                         [Phase 1]
 *  - Phase 1: userdate strftime '%A %d/%m/%Y' → Moodle format        [Phase 1]
 *  - Phase 2: <H2>/<UL> → Bootstrap 5 alert/list                    [Phase 2]
 *  - create_by as integer user.id throughout                         [Phase 1]
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
global $PAGE, $DB, $USER, $OUTPUT;
require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/mrbs_nds_auth.php');
require_once(__DIR__ . '/mrbs_nds_sql.php');

// ── Input parameters ──────────────────────────────────────────────────────────

$day           = optional_param('day',           0,        PARAM_INT);
$month         = optional_param('month',         0,        PARAM_INT);
$year          = optional_param('year',          0,        PARAM_INT);
$area          = optional_param('area',          0,        PARAM_INT);
$period        = optional_param('period',        0,        PARAM_INT);
$hour          = optional_param('hour',          0,        PARAM_INT);
$minute        = optional_param('minute',        0,        PARAM_INT);
$durationraw   = optional_param('duration',      0,        PARAM_RAW);
$dur_units     = optional_param('dur_units',     'periods', PARAM_TEXT);
$create_by     = optional_param('create_by',     0,        PARAM_INT);
$name          = optional_param('name',          '',       PARAM_TEXT);
$description   = optional_param('description',   '',       PARAM_TEXT);
$id            = optional_param('id',            0,        PARAM_INT);
$rep_type      = optional_param('rep_type',      0,        PARAM_INT);
$rep_end_month = optional_param('rep_end_month', 0,        PARAM_INT);
$rep_end_day   = optional_param('rep_end_day',   0,        PARAM_INT);
$rep_end_year  = optional_param('rep_end_year',  0,        PARAM_INT);
$rep_num_weeks = optional_param('rep_num_weeks', 0,        PARAM_INT);
$rep_opt       = optional_param('rep_opt',       '',       PARAM_SEQUENCE);
$rep_enddate   = optional_param('rep_enddate',   0,        PARAM_INT);
$forcebook     = optional_param('forcebook',     false,    PARAM_BOOL);
$edit_type     = optional_param('edit_type',     '',       PARAM_TEXT);
$type          = optional_param('type',          '',       PARAM_TEXT);
$all_day       = optional_param('all_day',       false,    PARAM_BOOL);
$ampm          = optional_param('ampm',          null,     PARAM_TEXT);
$rep_day       = optional_param_array('rep_day', null,     PARAM_RAW);
$rooms         = optional_param_array('rooms',   [],       PARAM_INT);
$doublebook    = optional_param('doublebook',    0,        PARAM_INT);
$roomchange    = optional_param('roomchange',    false,    PARAM_BOOL);

define('MRBS_ERR_DOUBLEBOOK', 1);
define('MRBS_ERR_TOOMANY',    2);

if (!$day || !$month || !$year) {
    $day   = (int) date('d');
    $month = (int) date('m');
    $year  = (int) date('Y');
}
if (!$area) {
    $area = get_default_area();
}

$PAGE->set_url(new moodle_url('/blocks/mrbs_nds/web/edit_entry_handler.php'));
require_login();

if (!getAuthorised(1)) {
    showAccessDenied($day, $month, $year, $area);
    exit;
}

$context = context_system::instance();
$PAGE->set_context($context);

require_sesskey();

// ── Authorisation ─────────────────────────────────────────────────────────────

$roomadmin      = false;
$editunconfirmed = has_capability('block/mrbs_nds:editmrbs_unconfirmed', $context, null, false);

// Phase 1: getWritable() and create_by both use integer IDs.
if (!getWritable($create_by, getUserID())) {
    if ($editunconfirmed) {
        foreach ($rooms as $key => $room_id) {
            $adminemail = $DB->get_field('block_mrbs_nds_room', 'room_admin_email', ['id' => $room_id]);
            if ($adminemail === $USER->email) {
                $roomadmin = true;
            } else {
                unset($rooms[$key]);
            }
        }
    }
    if (!$roomadmin) {
        showAccessDenied($day, $month, $year, $area);
        exit;
    }
}

// Non-room-admin unconfirmed users cannot create confirmed bookings.
if (authGetUserLevel(getUserID()) < 2 && $editunconfirmed) {
    foreach ($rooms as $room_id) {
        $adminemail = $DB->get_field('block_mrbs_nds_room', 'room_admin_email', ['id' => $room_id]);
        if ($adminemail !== $USER->email) {
            $type = 'U';
            break;
        }
    }
}

// ── Validation ────────────────────────────────────────────────────────────────

$name        = trim($name);
$description = trim($description);

if ($name === '') {
    print_header_mrbs_nds($day, $month, $year, $area);
    echo $OUTPUT->notification(s(get_string('must_set_name', 'block_mrbs_nds')), 'error');
    echo $OUTPUT->footer();
    exit;
}

if ($description === '') {
    print_header_mrbs_nds($day, $month, $year, $area);
    echo $OUTPUT->notification(s(get_string('must_set_description', 'block_mrbs_nds')), 'error');
    echo $OUTPUT->footer();
    exit;
}

if (!check_max_advance_days($day, $month, $year)) {
    print_header_mrbs_nds($day, $month, $year, $area);
    echo $OUTPUT->notification(s(get_string('toofaradvance', 'block_mrbs_nds', $max_advance_days)), 'error');
    echo $OUTPUT->footer();
    exit;
}

$roomdetails = $DB->get_records_list('block_mrbs_nds_room', 'id', $rooms);
foreach ($roomdetails as $roomrec) {
    if (!allowed_to_book($USER, $roomrec)) {
        print_header_mrbs_nds($day, $month, $year, $area);
        echo $OUTPUT->notification(s(get_string('notallowedbook', 'block_mrbs_nds')), 'error');
        echo $OUTPUT->footer();
        exit;
    }
}

// ── Duration calculation ──────────────────────────────────────────────────────

$durationparts = explode(':', (string) $durationraw, 2);
if ($dur_units === 'hours' && count($durationparts) === 2) {
    $duration = (float) intval($durationparts[0]) + ((float) intval($durationparts[1]) / 60.0);
} else {
    $duration = (float) unformat_float($durationraw);
}

if ($enable_periods) {
    $resolution  = 60;
    $hour        = 12;
    $minute      = $period;
    $max_periods = count($periods);
    if ($dur_units === 'periods' && ($minute + $duration) > $max_periods) {
        $duration = (24 * 60 * floor($duration / $max_periods)) + fmod($duration, $max_periods);
    }
    if ($dur_units === 'days' && $minute == 0) {
        $dur_units = 'periods';
        $duration  = $max_periods + ($duration - 1) * 60 * 24;
    }
}

// Convert duration to seconds.
$units = 1.0;
switch ($dur_units) {
    case 'years':   $units *= 52; // fall through
    case 'weeks':   $units *= 7;  // fall through
    case 'days':    $units *= 24; // fall through
    case 'hours':   $units *= 60; // fall through
    case 'periods':
    case 'minutes': $units *= 60; // fall through
    case 'seconds': break;
}

// ── Start / end time ──────────────────────────────────────────────────────────

if ($all_day) {
    if ($enable_periods) {
        $starttime = mktime(12, 0,          0, $month, $day, $year);
        $endtime   = mktime(12, $max_periods, 0, $month, $day, $year);
    } else {
        $end_minutes = $eveningends_minutes + $morningstarts_minutes;
        $starttime   = mktime($morningstarts, 0,           0, $month, $day, $year);
        $endtime     = mktime($eveningends,   $end_minutes, 0, $month, $day, $year);
    }
} else {
    if (!$twentyfourhour_format && !is_null($ampm)) {
        if ($ampm === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($ampm === 'am' && $hour > 11) {
            $hour -= 12;
        }
    }

    $starttime = mktime($hour, $minute, 0, $month, $day, $year);
    $endtime   = $starttime + (int) ($units * $duration);

    $diff = $endtime - $starttime;
    $tmp  = $diff % $resolution;
    if ($tmp !== 0 || $diff === 0) {
        $endtime += $resolution - $tmp;
    }
    $endtime += cross_dst($starttime, $endtime);
}

// ── Repeat settings ───────────────────────────────────────────────────────────

if (isset($rep_type, $rep_end_month, $rep_end_day, $rep_end_year)) {
    $rep_enddate = mktime($hour, $minute, 0, $rep_end_month, $rep_end_day, $rep_end_year);
} else {
    $rep_type = 0;
}

if (!isset($rep_day)) {
    $rep_day = [];
}

// Build rep_opt bitmask string for weekly/n-weekly repeats.
$rep_opt_str = '';
if (in_array($rep_type, [2, 6])) {
    for ($i = 0; $i < 7; $i++) {
        $rep_opt_str .= empty($rep_day[$i]) ? '0' : '1';
    }
}

// Convert bitmask string to array for mrbs_ndsGetRepeatEntryList.
$rep_opt_array = [];
for ($i = 0; $i < 7; $i++) {
    $rep_opt_array[$i] = isset($rep_opt_str[$i]) && $rep_opt_str[$i] === '1' ? 1 : 0;
}

if ($rep_type !== 0) {
    $reps = mrbs_ndsGetRepeatEntryList(
        $starttime, $rep_enddate ?? 0,
        $rep_type, $rep_opt_array,
        $max_rep_entrys, $rep_num_weeks
    );
}

// Ignore current entry/series when checking for clashes on edit.
$repeat_id = 0;
if ($id > 0) {
    $ignore_id = $id;
    $repeat_id = (int) $DB->get_field('block_mrbs_nds_entry', 'repeat_id', ['id' => $id]);
    if ($repeat_id < 0) {
        $repeat_id = 0;
    }
} else {
    $ignore_id = 0;
}

// ── Clash checking ────────────────────────────────────────────────────────────

$err             = '';
$errtype         = 0;
$forcemoveoutput = '';

foreach ($rooms as $room_id) {
    if ($rep_type !== 0 && !empty($reps)) {
        if (count($reps) < $max_rep_entrys) {
            foreach ($reps as $rep_start) {
                $diff  = $endtime - $starttime;
                $diff += cross_dst($rep_start, $rep_start + $diff);
                $tmp   = mrbs_ndsCheckFree($room_id, $rep_start, $rep_start + $diff, $ignore_id, $repeat_id);
                if ($doublebook !== 1 && !empty($tmp)) {
                    $err    .= $tmp;
                    $errtype = MRBS_ERR_DOUBLEBOOK;
                }
            }
        } else {
            $err    .= '<p>' . s(get_string('too_may_entrys', 'block_mrbs_nds')) . '</p>';
            $errtype = MRBS_ERR_TOOMANY;
        }
    } else {
        if (has_capability('block/mrbs_nds:forcebook', $context) && $forcebook) {
            require_once __DIR__ . '/force_book.php';
            $forcemoveoutput .= mrbs_ndsForceMove($room_id, $starttime, $endtime, $name, $id);
        } elseif ($doublebook && has_capability('block/mrbs_nds:doublebook', $context)) {
            // Double-book: notify existing bookers.
            $sql = "SELECT e.id AS entryid, e.name AS entryname, e.create_by,
                           r.room_name, e.start_time
                      FROM {block_mrbs_nds_entry} e
                      JOIN {block_mrbs_nds_room} r ON r.id = e.room_id
                     WHERE r.id = ?
                       AND ((e.start_time >= ? AND e.end_time < ?)
                         OR (e.start_time < ?  AND e.end_time > ?)
                         OR (e.start_time < ?  AND e.end_time >= ?))";
            $clashes = $DB->get_records_sql($sql,
                [$room_id, $starttime, $endtime, $starttime, $starttime, $endtime, $endtime]);
            foreach ($clashes as $clash) {
                $olduser  = $DB->get_record('user', ['id' => $clash->create_by]);
                $langvars = new stdClass();
                $langvars->user       = fullname($USER);
                $langvars->room       = s($clash->room_name);
                $langvars->time       = to_hr_time($clash->start_time);
                // Phase 1: Moodle date format.
                $langvars->date       = userdate($clash->start_time,
                                            get_string('strftimedatefullshort', 'langconfig'));
                $langvars->oldbooking = s($clash->entryname);
                $langvars->newbooking = s($name);
                $langvars->admin      = s($mrbs_nds_admin . ' (' . $mrbs_nds_admin_email . ')');
                if ($olduser) {
                    email_to_user($olduser, $USER,
                        get_string('doublebookesubject', 'block_mrbs_nds'),
                        get_string('doublebookebody',    'block_mrbs_nds', $langvars));
                }
            }
        } else {
            $err .= mrbs_ndsCheckFree($room_id, $starttime, $endtime - 1, $ignore_id, 0);
        }
    }
}

// ── Save bookings ─────────────────────────────────────────────────────────────

if (empty($err)) {
    foreach ($rooms as $room_id) {
        if ($edit_type === 'series' || $doublebook === 1) {
            $rep_details = mrbs_ndsCreateRepeatingEntrys(
                $starttime, $endtime, $rep_type, $rep_enddate ?? 0,
                $rep_opt_array, $room_id, $create_by, $name, $type,
                $description, $rep_num_weeks ?: 0, $roomchange, $id
            );
            $new_id  = $rep_details->id;
            $enddate = null;
            if ($rep_details->created && $rep_details->created < $rep_details->requested) {
                $forcemoveoutput .= s(get_string('notallcreated', 'block_mrbs_nds', $rep_details));
                $enddate = $rep_details->lasttime;
            }

            $dbroom    = $DB->get_record_sql(
                "SELECT r.id, r.room_name, r.area_id, a.area_name
                   FROM {block_mrbs_nds_room} r JOIN {block_mrbs_nds_area} a ON a.id = r.area_id
                  WHERE r.id = ?",
                [$room_id], MUST_EXIST
            );
            $room_name = $dbroom->room_name;
            $area_name = $dbroom->area_name;

            $event = \block_mrbs_nds\event\booking_created::create([
                'objectid' => $new_id,
                'context'  => \context_system::instance(),
                'other'    => ['name' => $name, 'room' => $room_name],
            ]);
            $event->trigger();

            $send_mail = MAIL_ADMIN_ON_BOOKINGS || MAIL_AREA_ADMIN_ON_BOOKINGS
                      || MAIL_ROOM_ADMIN_ON_BOOKINGS || MAIL_BOOKER;
            if ($send_mail && $new_id) {
                if ($id > 0) {
                    $mail_previous = getPreviousEntryData($id, (int) $rep_details->repeating);
                }
                notifyAdminOnBooking(($id == 0), $new_id, $enddate);
            }

        } else {
            $entry_type = ($repeat_id > 0) ? 2 : 0;
            $new_id     = mrbs_ndsCreateSingleEntry(
                $starttime, $endtime, $entry_type, $repeat_id,
                $room_id, $create_by, $name, $type, $description, $id, $roomchange
            );

            $dbroom    = $DB->get_record_sql(
                "SELECT r.id, r.room_name, r.area_id, a.area_name
                   FROM {block_mrbs_nds_room} r JOIN {block_mrbs_nds_area} a ON a.id = r.area_id
                  WHERE r.id = ?",
                [$room_id], MUST_EXIST
            );
            $room_name = $dbroom->room_name;
            $area_name = $dbroom->area_name;

            $event = \block_mrbs_nds\event\booking_updated::create([
                'objectid' => $new_id,
                'context'  => \context_system::instance(),
                'other'    => ['name' => $name, 'room' => $room_name],
            ]);
            $event->trigger();

            $send_mail = MAIL_ADMIN_ON_BOOKINGS || MAIL_AREA_ADMIN_ON_BOOKINGS
                      || MAIL_ROOM_ADMIN_ON_BOOKINGS || MAIL_BOOKER;
            if ($send_mail && $new_id) {
                if ($id > 0) {
                    $mail_previous = getPreviousEntryData($id, 0);
                }
                notifyAdminOnBooking(($id == 0), $new_id);
            }
        }
    }

    $area   = mrbs_ndsGetRoomArea($room_id);
    $dayurl = new moodle_url('/blocks/mrbs_nds/web/day.php',
        ['year' => $year, 'month' => $month, 'day' => $day, 'area' => $area]);
    redirect($dayurl, $forcemoveoutput, 20);
    exit;
}

// ── Clash error display ───────────────────────────────────────────────────────

print_header_mrbs_nds($day, $month, $year, $area);

echo '<div class="alert alert-warning">';
echo '<h4>' . s(get_string('sched_conflict', 'block_mrbs_nds')) . '</h4>';
if (!isset($hide_title)) {
    echo '<p>' . s(get_string('conflict', 'block_mrbs_nds')) . '</p>';
}
echo $err;
echo '</div>';

if (has_capability('block/mrbs_nds:doublebook', $context) && $errtype === MRBS_ERR_DOUBLEBOOK) {
    $thisurl = new moodle_url('/blocks/mrbs_nds/web/edit_entry_handler.php');
    echo '<form method="post" action="' . s($thisurl) . '">';

    // Phase 1 XSS fix: s() on all values.
    foreach ([
        'name'        => s($name),
        'description' => s($description),
        'day'         => (int) $day,
        'month'       => (int) $month,
        'year'        => (int) $year,
        'area'        => (int) $area,
        'create_by'   => (int) $create_by,
        'id'          => (int) $id,
        'rep_type'    => (int) $rep_type,
        'rep_end_month' => (int) $rep_end_month,
        'rep_end_day'   => (int) $rep_end_day,
        'rep_end_year'  => (int) $rep_end_year,
        'rep_num_weeks' => (int) $rep_num_weeks,
        'rep_opt'     => s($rep_opt_str),
        'rep_enddate' => (int) ($rep_enddate ?? 0),
        'hour'        => (int) $hour,
        'minute'      => (int) $minute,
        'period'      => (int) $period,
        'duration'    => s($durationraw),
        'dur_units'   => s($dur_units),
        'type'        => s($type),
        'doublebook'  => 1,
    ] as $fname => $fval) {
        echo '<input type="hidden" name="' . $fname . '" value="' . $fval . '">';
    }

    for ($i = 0; $i < 7; $i++) {
        $val = empty($rep_day[$i]) ? '' : 'on';
        echo '<input type="hidden" name="rep_day[' . $i . ']" value="' . $val . '">';
    }
    foreach ($rooms as $r) {
        echo '<input type="hidden" name="rooms[]" value="' . (int) $r . '">';
    }
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<button type="submit" class="btn btn-warning">'
         . s(get_string('idontcare', 'block_mrbs_nds')) . '</button>';
    echo '</form>';
}

$returl = new moodle_url('/blocks/mrbs_nds/web/index.php');
echo '<p><a href="' . s($returl) . '">' . s(get_string('returncal', 'block_mrbs_nds')) . '</a></p>';

require_once __DIR__ . '/trailer.php';
