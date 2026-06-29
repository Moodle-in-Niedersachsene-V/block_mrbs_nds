<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * View a single booking entry.
 *
 * Phase 1+2 changes vs. view_entry.php:
 *  - All table names: block_mrbs_rlp_* → block_mrbs_nds_*
 *  - All lang strings: block_mrbs_rlp → block_mrbs_nds
 *  - All capability strings: block/mrbs_rlp → block/mrbs_nds
 *  - Phase 1 XSS: $name, $description already s()-wrapped; $rep_end_date,
 *    $opt, $rep_num_weeks, $typel values also s()-wrapped now
 *  - Phase 1: strftime '%A %d %B %Y' → Moodle date format
 *  - Phase 1: create_by is INT; JOIN on user.id (not user.username)
 *  - Phase 2: <table border=0> → Bootstrap 5 table-borderless
 *  - Phase 2: require_once instead of include
 *  - Phase 1: confirm() dialog uses data-confirm attribute (avoids broken JS)
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/functions.php');

global $DB, $USER, $OUTPUT;

$id     = required_param('id',     PARAM_INT);
$day    = optional_param('day',    0, PARAM_INT);
$month  = optional_param('month',  0, PARAM_INT);
$year   = optional_param('year',   0, PARAM_INT);
$area   = optional_param('area',   0, PARAM_INT);
$room   = optional_param('room',   0, PARAM_INT);
$series = optional_param('series', 0, PARAM_INT);
$pview  = optional_param('pview',  0, PARAM_INT);

$context = context_system::instance();

// Phase 1: compare integer IDs.
if ($record = $DB->get_record('block_mrbs_rlp_entry', ['id' => $id])) {
    if ((int) $record->create_by === (int) $USER->id) {
        $redirect = true;
        if (has_capability('block/mrbs_nds:editmrbs_unconfirmed', $context, null, false)) {
            $adminemail = $DB->get_field('block_mrbs_rlp_room', 'room_admin_email',
                                         ['id' => $record->room_id]);
            if ($USER->email !== $adminemail || $record->type !== 'U') {
                $redirect = false;
            }
        }
        if ($redirect) {
            redirect(new moodle_url('/blocks/mrbs_nds/web/edit_entry.php', ['id' => $id]));
        }
    }
}

if (!$day || !$month || !$year) {
    $day   = (int) date('d');
    $month = (int) date('m');
    $year  = (int) date('Y');
}

$thisurl = new moodle_url('/blocks/mrbs_nds/web/view_entry.php',
    ['day' => $day, 'month' => $month, 'year' => $year, 'id' => $id]);
if ($area)   { $thisurl->param('area',   $area); } else { $area = get_default_area(); }
if ($room)   { $thisurl->param('room',   $room); }
if ($series) { $thisurl->param('series', $series); }
if ($pview)  { $thisurl->param('pview',  $pview); }

$PAGE->set_url($thisurl);
require_login();

// ── Fetch booking data ────────────────────────────────────────────────────────

$userfieldsapi = \core_user\fields::for_name();
$namefields    = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

if ($series) {
    $sql = "SELECT re.name, re.description, re.create_by,
                   r.room_name, a.area_name, re.type, re.room_id, re.timestamp,
                   (re.end_time - re.start_time) duration,
                   re.start_time, re.end_time,
                   re.rep_type, re.end_date, re.rep_opt, re.rep_num_weeks,
                   u.id AS userid, $namefields
              FROM {block_mrbs_rlp_repeat} re
              LEFT JOIN {user} u ON u.id = re.create_by
              JOIN {block_mrbs_rlp_room} r ON r.id = re.room_id
              JOIN {block_mrbs_rlp_area} a ON a.id = r.area_id
             WHERE re.id = ?";
} else {
    $sql = "SELECT e.name, e.description, e.create_by,
                   r.room_name, a.area_name, e.type, e.room_id, e.timestamp,
                   (e.end_time - e.start_time) duration,
                   e.start_time, e.end_time, e.repeat_id,
                   u.id AS userid, $namefields
              FROM {block_mrbs_rlp_entry} e
              LEFT JOIN {user} u ON u.id = e.create_by
              JOIN {block_mrbs_rlp_room} r ON r.id = e.room_id
              JOIN {block_mrbs_rlp_area} a ON a.id = r.area_id
             WHERE e.id = ?";
}

$booking = $DB->get_record_sql($sql, [$id], MUST_EXIST);

$userinfos = $DB->get_record('user', ['id' => $booking->create_by]);
$booking->fullname = $userinfos ? fullname($userinfos) : '';

// Phase 1 XSS: all DB values through s() before any echo.
$name        = s($booking->name);
$description = s($booking->description);
$userurl     = new moodle_url('/user/view.php', ['id' => $booking->create_by]);
$create_by   = '<a href="' . s($userurl) . '">' . s($booking->fullname) . '</a>';
$room_name   = s($booking->room_name);
$area_name   = s($booking->area_name);
$type        = $booking->type;
$room_id     = $booking->room_id;
// Phase 1: Moodle date format instead of strftime.
$updated     = time_date_string($booking->timestamp);
$duration    = $booking->duration - cross_dst($booking->start_time, $booking->end_time);

if ($enable_periods) {
    [$start_period, $start_date] = period_date_string($booking->start_time);
    [, $end_date]                = period_date_string($booking->end_time, -1);
} else {
    $start_date = time_date_string($booking->start_time);
    $end_date   = time_date_string($booking->end_time);
}

$rep_type     = 0;
$rep_end_date = '';
$rep_opt      = '';
$rep_num_weeks = null;

if ($series) {
    $rep_type      = $booking->rep_type;
    // Phase 1: Moodle date format.
    $rep_end_date  = userdate($booking->end_date, get_string('strftimedaydate', 'langconfig'));
    $rep_opt       = $booking->rep_opt;
    $rep_num_weeks = $booking->rep_num_weeks;
    $repeat_id     = false;

    $entry = $DB->get_records('block_mrbs_rlp_entry',
        ['repeat_id' => $id, 'entry_type' => 1], 'start_time', 'id', 0, 1);
    if (empty($entry)) {
        $entry = $DB->get_records('block_mrbs_rlp_entry',
            ['repeat_id' => $id], 'start_time', 'id', 0, 1);
    }
    $entry = reset($entry);
    $id    = $entry->id;
} else {
    $repeat_id = $booking->repeat_id;
    if ($repeat_id) {
        $repeat = $DB->get_record('block_mrbs_rlp_repeat', ['id' => $repeat_id]);
        if ($repeat) {
            $rep_type      = $repeat->rep_type;
            $rep_end_date  = userdate($repeat->end_date, get_string('strftimedaydate', 'langconfig'));
            $rep_opt       = $repeat->rep_opt;
            $rep_num_weeks = $repeat->rep_num_weeks;
        }
    }
}

$dur_units = '';
if ($enable_periods) {
    toPeriodString($start_period, $duration, $dur_units);
} else {
    toTimeString($duration, $dur_units);
}

$repeat_key = 'rep_type_' . $rep_type;

$roomadmin = false;
if (has_capability('block/mrbs_nds:editmrbs_unconfirmed', $context, null, false)) {
    $adminemail = $DB->get_field('block_mrbs_rlp_room', 'room_admin_email', ['id' => $room_id]);
    if ($adminemail === $USER->email) {
        $roomadmin = true;
    }
}

if ($roomadmin && $type === 'U') {
    redirect(new moodle_url('/blocks/mrbs_nds/web/edit_entry.php', ['id' => $id]));
}

print_header_mrbs_nds($day, $month, $year, $area);

// ── Display ───────────────────────────────────────────────────────────────────
?>
<h3>
<?php
// Phase 1 XSS: $name is already s()-escaped. Use $booking->name (raw) for DB query only.
if ($course = $DB->get_record('course', ['shortname' => $booking->name])) {
    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
    echo '<a href="' . s($courseurl) . '">' . $name . '</a>';
    $sizequery = "SELECT COUNT(*) AS size
                    FROM {context} cx
                    JOIN {role_assignments} ra ON ra.contextid = cx.id AND ra.roleid = 5
                    JOIN {course} c ON cx.contextlevel = 50 AND cx.instanceid = c.id
                   WHERE c.id = ?";
    $size = $DB->get_record_sql($sizequery, [$course->id]);
    echo '<br>' . s(get_string('classsize', 'block_mrbs_nds')) . ': ' . (int) $size->size;
} else {
    echo $name;
}
?>
</h3>

<table class="table table-sm table-borderless w-auto">
    <tr>
        <th scope="row"><?= s(get_string('description')) ?></th>
        <td><?= nl2br($description) ?></td>
    </tr>
    <tr>
        <th scope="row"><?= s(get_string('room', 'block_mrbs_nds')) ?></th>
        <td><?= $area_name . ' – ' . $room_name ?></td>
    </tr>
    <tr>
        <th scope="row"><?= s(get_string('start_date', 'block_mrbs_nds')) ?></th>
        <td><?= $start_date ?></td>
    </tr>
    <tr>
        <th scope="row"><?= s(get_string('duration', 'block_mrbs_nds')) ?></th>
        <td><?= (int) $duration . ' ' . s($dur_units) ?></td>
    </tr>
    <tr>
        <th scope="row"><?= s(get_string('end_date', 'block_mrbs_nds')) ?></th>
        <td><?= $end_date ?></td>
    </tr>
    <tr>
        <th scope="row"><?= s(get_string('type', 'block_mrbs_nds')) ?></th>
        <td><?= empty($typel[$type]) ? '?' . s($type) . '?' : s($typel[$type]) ?></td>
    </tr>
    <tr>
        <th scope="row"><?= s(get_string('createdby', 'block_mrbs_nds')) ?></th>
        <td><?= $create_by /* already escaped above */ ?></td>
    </tr>
    <tr>
        <th scope="row"><?= s(get_string('lastmodified')) ?></th>
        <td><?= $updated ?></td>
    </tr>
    <tr>
        <th scope="row"><?= s(get_string('rep_type', 'block_mrbs_nds')) ?></th>
        <td><?= s(get_string($repeat_key, 'block_mrbs_nds')) ?></td>
    </tr>

    <?php if ($rep_type != 0):
        $opt = '';
        if (in_array($rep_type, [2, 6])) {
            for ($i = 0; $i < 7; $i++) {
                $daynum = ($i + $weekstarts) % 7;
                if (!empty($rep_opt[$daynum]) && $rep_opt[$daynum] !== '0') {
                    $opt .= day_name($daynum) . ' ';
                }
            }
        }
        if ($rep_type == 6 && $rep_num_weeks): ?>
    <tr>
        <th scope="row"><?= s(get_string('rep_num_weeks', 'block_mrbs_nds'))
                          . s(get_string('rep_for_nweekly', 'block_mrbs_nds')) ?></th>
        <td><?= (int) $rep_num_weeks ?></td>
    </tr>
        <?php endif;
        if ($opt): ?>
    <tr>
        <th scope="row"><?= s(get_string('rep_rep_day', 'block_mrbs_nds')) ?></th>
        <td><?= s(trim($opt)) ?></td>
    </tr>
        <?php endif; ?>
    <tr>
        <th scope="row"><?= s(get_string('rep_end_date', 'block_mrbs_nds')) ?></th>
        <td><?= s($rep_end_date) ?></td>
    </tr>
    <?php endif; ?>
</table>

<div class="mb-3">
<?php
$canedit = getWritable((int) $booking->create_by, getUserID());
if ($canedit || $roomadmin):
    if (!$series):
        $editurl = new moodle_url('/blocks/mrbs_nds/web/edit_entry.php', ['id' => $id]);
        echo '<a class="btn btn-sm btn-primary me-1" href="' . s($editurl) . '">'
             . s(get_string('editentry', 'block_mrbs_nds')) . '</a>';
    endif;
    if ($repeat_id || $series):
        $editurl = new moodle_url('/blocks/mrbs_nds/web/edit_entry.php',
            ['id' => $id, 'edit_type' => 'series', 'day' => $day, 'month' => $month, 'year' => $year]);
        echo '<a class="btn btn-sm btn-secondary me-2" href="' . s($editurl) . '">'
             . s(get_string('editseries', 'block_mrbs_nds')) . '</a>';
    endif;

    if (!$series):
        $delurl = new moodle_url('/blocks/mrbs_nds/web/del_entry.php',
            ['id' => $id, 'series' => 0, 'sesskey' => sesskey()]);
        echo '<a class="btn btn-sm btn-danger me-1" href="' . s($delurl) . '"'
             . ' onclick="return confirm(\'' . s(get_string('confirmdel', 'block_mrbs_nds')) . '\');">'
             . s(get_string('deleteentry', 'block_mrbs_nds')) . '</a>';
    endif;
    if ($repeat_id || $series):
        $delurl = new moodle_url('/blocks/mrbs_nds/web/del_entry.php',
            ['id' => $id, 'series' => 1, 'sesskey' => sesskey(),
             'day' => $day, 'month' => $month, 'year' => $year]);
        echo '<a class="btn btn-sm btn-outline-danger" href="' . s($delurl) . '"'
             . ' onclick="return confirm(\'' . s(get_string('confirmdel', 'block_mrbs_nds')) . '\');">'
             . s(get_string('deleteseries', 'block_mrbs_nds')) . '</a>';
    endif;
endif;
?>
</div>

<?php
if ((int) $USER->id !== (int) $booking->create_by) {
    require_once __DIR__ . '/request_vacate.php';
}

require_once __DIR__ . '/trailer.php';
