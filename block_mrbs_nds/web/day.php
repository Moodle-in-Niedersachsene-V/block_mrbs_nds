<?php

// This file is part of the MRBS block for Moodle
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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
global $PAGE;
require_once __DIR__ . "/config.inc.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/mrbs_nds_auth.php";
require_once __DIR__ . "/mincals.php";

$day = optional_param('day', 0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$area = optional_param('area', 0, PARAM_INT);
//$room = optional_param('room', 0, PARAM_INT);
$morningstarts_minutes = optional_param('morningstarts_minutes', 0, PARAM_INT);
$debug_flag = optional_param('debug_flag', 0, PARAM_INT);
$timetohighlight = optional_param('timetohighlight', -1, PARAM_INT);
$roomnotfound = optional_param('roomnotfound', null, PARAM_TEXT);

//If we dont know the right date then make it up
if (($day == 0) or ($month == 0) or ($year == 0)) {
    $day = date("d");
    $month = date("m");
    $year = date("Y");
} else {
    // Make the date valid if day is more then number of days in month
    while (!checkdate(intval($month), intval($day), intval($year))) {
        $day--;
    }
}

$format = "Gi";
if ($enable_periods) {
    $format = "i";
    $resolution = 60;
    $morningstarts = 12;
    $morningstarts_minutes = 0;
    $eveningends = 12;
    $eveningends_minutes = count($periods) - 1;
}

$baseurl = new moodle_url('/blocks/mrbs_nds/web/day.php', ['day' => $day, 'month' => $month, 'year' => $year]); // Used as basis for URLs throughout this file
$thisurl = new moodle_url($baseurl);
if ($area > 0) {
    $thisurl->param('area', $area);
} else {
    $area = get_default_area();
}
if ($morningstarts_minutes > 0) {
    $thisurl->param('morningstarts_minutes', $morningstarts_minutes);
}
if ($timetohighlight >= 0) {
    $thisurl->param('timetohighlight', $timetohighlight);
}

$PAGE->set_url($thisurl);
require_login();

// print the page header
print_header_mrbs_nds($day, $month, $year, $area);

$am7 = mktime($morningstarts, $morningstarts_minutes, 0, $month, $day, $year);
$pm7 = mktime($eveningends,   $eveningends_minutes,   0, $month, $day, $year);

$advanceok = check_max_advance_days($day, $month, $year);

if ($area <= 0) {
    echo '<div class="alert alert-warning">' . s(get_string('noareas', 'block_mrbs_nds')) . '</div>';
    show_colour_key();
    require_once __DIR__ . '/trailer.php';
    exit;
}

// Fetch room data
$rooms = $DB->get_records('block_mrbs_nds_room', ['area_id' => $area], 'room_name');
foreach ($rooms as $room) {
    $room->allowedtobook = allowed_to_book($USER, $room);
}

// Fetch entries for the day
$today = [];
if (!empty($rooms)) {
    $sql = "SELECT e.id AS eid, r.id AS rid, e.start_time, e.end_time,
                   e.name, e.type, e.description, e.repeat_id
              FROM {block_mrbs_nds_entry} e
              JOIN {block_mrbs_nds_room} r ON r.id = e.room_id
             WHERE r.area_id = ? AND e.start_time <= ? AND e.end_time > ?";
    $entries = $DB->get_records_sql($sql, [$area, $pm7, $am7]);

    foreach ($entries as $entry) {
        $start_t = max(round_t_down($entry->start_time, $resolution, $am7), $am7);
        $end_t   = min(round_t_up($entry->end_time, $resolution, $am7) - $resolution, $pm7);
        for ($t2 = $start_t; $t2 <= $end_t; $t2 += $resolution) {
            $key = date($format, $t2);
            if (empty($today[$entry->rid][$key])) {
                $today[$entry->rid][$key] = [
                    'id' => $entry->eid, 'color' => $entry->type,
                    'data' => '', 'long_descr' => '', 'double_booked' => false,
                ];
            } else {
                $today[$entry->rid][$key]['id']           .= ',' . $entry->eid;
                $today[$entry->rid][$key]['double_booked'] = true;
            }
        }
        $name_key = date($format, max($entry->start_time < $am7 ? $am7 : $start_t, $am7));
        $today[$entry->rid][$name_key]['data']      .= $entry->name;
        $today[$entry->rid][$name_key]['long_descr'] .= $entry->description;
    }
}

// ── Layout wrapper ───────────────────────────────────────────────────────────
echo '<div class="mrbs-layout">';

// ── Sidebar ──────────────────────────────────────────────────────────────────
echo '<div class="mrbs-sidebar">';
echo '<div class="mrbs-sidebar-label">' . s(get_string('areas', 'block_mrbs_nds')) . '</div>';
$allareas = $DB->get_records('block_mrbs_nds_area', null, 'area_name');
foreach ($allareas as $dbarea) {
    $areaurl = new moodle_url('/blocks/mrbs_nds/web/day.php',
        ['day' => $day, 'month' => $month, 'year' => $year, 'area' => $dbarea->id]);
    $active = ($dbarea->id == $area) ? ' active' : '';
    echo '<a class="mrbs-area-item' . $active . '" href="' . s($areaurl) . '">'
         . s($dbarea->area_name) . '</a>';
}

echo '<div style="margin-top:.6rem">';
echo '<div class="mrbs-sidebar-label">' . s(get_string('findroom', 'block_mrbs_nds')) . '</div>';
$gotoroom = new moodle_url('/blocks/mrbs_nds/web/gotoroom.php');
$gotoval  = $roomnotfound ? s($roomnotfound) : '';
$gotomsg  = $roomnotfound ? '<div class="small text-danger mt-1">' . s(get_string('noroomsfound', 'block_mrbs_nds')) . '</div>' : '';
echo '<form action="' . s($gotoroom) . '" method="get" class="d-flex flex-column gap-1">';
echo '<input class="form-control form-control-sm" type="text" name="room" value="' . $gotoval . '">';
echo '<input type="hidden" name="day"   value="' . (int)$day . '">';
echo '<input type="hidden" name="month" value="' . (int)$month . '">';
echo '<input type="hidden" name="year"  value="' . (int)$year . '">';
echo '<button type="submit" class="btn btn-sm btn-outline-secondary">'
     . s(get_string('goroom', 'block_mrbs_nds')) . '</button>';
echo '</form>' . $gotomsg;
echo '</div>';
echo '</div>'; // sidebar

// ── Main calendar ────────────────────────────────────────────────────────────
echo '<div class="mrbs-main">';

if (empty($rooms)) {
    echo '<div class="p-3">'
         . '<div class="alert alert-info">'
         . s(get_string('no_rooms_for_area', 'block_mrbs_nds'))
         . '</div></div>';
} else {
    echo '<table class="mrbs-cal">';
    echo '<thead><tr>';
    echo '<th style="width:48px">' . s($enable_periods ? get_string('period', 'block_mrbs_nds') : get_string('time')) . '</th>';

    $weekurl_base = new moodle_url('/blocks/mrbs_nds/web/week.php',
        ['year' => $year, 'month' => $month, 'day' => $day, 'area' => $area]);
    foreach ($rooms as $room) {
        $wurl = $weekurl_base->out(true, ['room' => $room->id]);
        $cap  = $room->capacity > 0 ? ' <span class="small text-muted">(' . (int)$room->capacity . ')</span>' : '';
        echo '<th><a href="' . s($wurl) . '" title="' . s(get_string('viewweek','block_mrbs_nds')) . '">'
             . s($room->room_name) . $cap . '</a></th>';
    }
    echo '</tr></thead><tbody>';

    for ($t = $am7; $t <= $pm7; $t += $resolution) {
        $time_t = date($format, $t);
        echo '<tr>';

        // Time cell
        if ($enable_periods) {
            $p_stripped = ltrim($time_t, '0') ?: '0';
            echo '<td class="time-col">' . s($periods[$p_stripped] ?? $p_stripped) . '</td>';
        } else {
            echo '<td class="time-col">' . userdate($t, hour_min_format()) . '</td>';
        }

        foreach ($rooms as $room) {
            $booked     = isset($today[$room->id][$time_t]['id']);
            $entry_data = $booked ? $today[$room->id][$time_t] : null;

            if ($booked) {
                $type  = $entry_data['color'];
                $dbl   = $entry_data['double_booked'];
                $slot_class = $dbl ? 'slot double' : 'slot booked';
                if ($type === 'U') { $slot_class = 'slot unconfirmed'; }
                if ($time_t == $timetohighlight) { $slot_class .= ' highlighted'; }

                // Make the cell clickable → view entry
                $ids = explode(',', $entry_data['id']);
                $viewurl = new moodle_url('/blocks/mrbs_nds/web/view_entry.php',
                    ['id' => (int)$ids[0], 'area' => $area,
                     'day' => $day, 'month' => $month, 'year' => $year]);
                echo '<td class="' . $slot_class . '">';
                echo '<a href="' . s($viewurl) . '" style="text-decoration:none;color:inherit;display:block">';
                $name_class = ($type === 'U') ? 'mrbs-slot-name unc' : 'mrbs-slot-name';
                echo '<div class="' . $name_class . '">' . s($entry_data['data']) . '</div>';
                echo '</a>';
                echo '</td>';
            } else {
                // Free slot
                $hour_val   = date('H', $t);
                $minute_val = date('i', $t);
                $slot_class = 'slot free';
                if ($time_t == $timetohighlight) { $slot_class .= ' highlighted'; }

                if ($pview == 1 || !$room->allowedtobook || !$advanceok) {
                    echo '<td class="' . $slot_class . '">&nbsp;</td>';
                } else {
                    // Clicking a free slot opens the side form
                    $editparams = ['room' => $room->id, 'area' => $area,
                                   'year' => $year, 'month' => $month, 'day' => $day];
                    if ($enable_periods) {
                        $p_stripped = ltrim($time_t, '0') ?: '0';
                        $editparams['period'] = $p_stripped;
                        $timestr = s($periods[$p_stripped] ?? $p_stripped);
                    } else {
                        $editparams['hour']   = $hour_val;
                        $editparams['minute'] = $minute_val;
                        $timestr = s(userdate($t, hour_min_format()));
                    }
                    $roomname_safe = s($room->room_name);
                    $editurl_str   = (new moodle_url('/blocks/mrbs_nds/web/edit_entry.php',
                                         $editparams))->out(false);

                    // Use single-quoted JS strings inside the double-quoted HTML attribute
                    $p_val  = $enable_periods ? (ltrim($time_t, '0') ?: '0') : '';
                    $js_url  = str_replace("'", "\'", $editurl_str);
                    $js_room = (int) $room->id;
                    $js_hour = (int) $hour_val;
                    $js_min  = (int) $minute_val;
                    $js_per  = addslashes($p_val);
                    $js_rn   = addslashes($roomname_safe);
                    $js_ts   = addslashes($timestr);
                    $onclick = "mrbsOpenPanel(this,'$js_url',$js_room,'$js_hour','$js_min','$js_per','$js_rn','$js_ts')";
                    echo '<td class="' . $slot_class . '"'
                         . ' style="cursor:pointer"'
                         . ' onclick="' . $onclick . '">&nbsp;</td>';
                }
            }
        }
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<div class="mrbs-legend">';
    echo '<span><span class="mrbs-legend-dot" style="background:#d1e7dd"></span>'
         . s(get_string('confirmed', 'block_mrbs_nds')) . '</span>';
    echo '<span><span class="mrbs-legend-dot" style="background:#fff3cd"></span>'
         . s(get_string('unconfirmedbooking', 'block_mrbs_nds')) . '</span>';
    echo '<span><span class="mrbs-legend-dot" style="background:var(--bg-accent,#e7f1ff);border:1px dashed #0d6efd"></span>'
         . s(get_string('free_click', 'block_mrbs_nds')) . '</span>';
    echo '</div>';
}

echo '</div>'; // mrbs-main

// ── Side form panel ──────────────────────────────────────────────────────────
echo '<div class="mrbs-form-panel" id="mrbs-form-panel">';
echo '<div class="mrbs-form-panel-head">';
echo '<span id="mrbs-panel-title">' . s(get_string('addentry', 'block_mrbs_nds')) . '</span>';
echo '<button class="mrbs-form-panel-close" onclick="mrbsClosePanel()" aria-label="Schließen">&#x2715;</button>';
echo '</div>';

// The panel form posts to edit_entry_handler.php (saves the booking).
// edit_entry.php is only for displaying/editing the full form.
$handlerurl = new moodle_url('/blocks/mrbs_nds/web/edit_entry_handler.php');
echo '<form id="mrbs-panel-form" method="post" action="' . s($handlerurl) . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="day"     id="fp_day"    value="' . (int)$day . '">';
echo '<input type="hidden" name="month"   id="fp_month"  value="' . (int)$month . '">';
echo '<input type="hidden" name="year"    id="fp_year"   value="' . (int)$year . '">';
echo '<input type="hidden" name="area"    id="fp_area"   value="' . (int)$area . '">';
echo '<input type="hidden" name="room_id" id="fp_room"   value="">';
echo '<input type="hidden" name="hour"    id="fp_hour"   value="">';
echo '<input type="hidden" name="minute"  id="fp_minute" value="">';
echo '<input type="hidden" name="period"  id="fp_period" value="">';
echo '<input type="hidden" name="create_by" value="' . (int)$USER->id . '">';

// Name / Reservierung
echo '<div class="mb-2">';
echo '<label class="form-label">' . s(get_string('namebooker', 'block_mrbs_nds')) . '</label>';
echo '<input class="form-control" type="text" name="name" id="fp_name" required value="' . s(fullname($USER)) . '">';
echo '</div>';

// Beschreibung
echo '<div class="mb-2">';
echo '<label class="form-label">' . s(get_string('description')) . '</label>';
echo '<textarea class="form-control" name="description" rows="2"></textarea>';
echo '</div>';

// Dauer
echo '<div class="mb-2">';
echo '<label class="form-label">' . s(get_string('duration', 'block_mrbs_nds')) . '</label>';
echo '<div class="d-flex gap-1">';
echo '<input class="form-control" type="number" name="duration" value="1" min="1" style="width:60px">';
echo '<select class="form-control" name="dur_units">';
$units_list = $enable_periods ? ['periods', 'days'] : ['minutes', 'hours', 'days'];
foreach ($units_list as $u) {
    echo '<option value="' . s($u) . '">' . s(get_string($u, 'block_mrbs_nds')) . '</option>';
}
echo '</select>';
echo '</div></div>';

// Art
if (!empty($typel)) {
    echo '<div class="mb-2">';
    echo '<label class="form-label">' . s(get_string('type', 'block_mrbs_nds')) . '</label>';
    echo '<select class="form-control" name="type">';
    foreach ($typel as $tc => $tl) {
        echo '<option value="' . s($tc) . '">' . s($tl) . '</option>';
    }
    echo '</select></div>';
}

// Rooms hidden (single room from clicked slot)
echo '<input type="hidden" name="rooms[]" id="fp_rooms" value="">';
echo '<input type="hidden" name="edit_type" value="">';

// Actions
echo '<div class="mrbs-form-actions">';
echo '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="mrbsClosePanel()">'
     . s(get_string('cancel', 'core')) . '</button>';
echo '<a class="btn btn-sm btn-outline-primary ms-1" id="mrbs-full-form-link" href="#">'
     . s(get_string('moredetails', 'block_mrbs_nds')) . '</a>';
echo '<button type="submit" class="btn btn-sm btn-primary">'
     . s(get_string('savechanges')) . '</button>';
echo '</div>';
echo '</form>';
echo '</div>'; // mrbs-form-panel

echo '</div>'; // mrbs-layout

show_colour_key();

// JS for panel open/close
echo <<<'JSPANEL'
<script>
function mrbsOpenPanel(cell, editUrl, roomId, hourVal, minuteVal, periodVal, roomName, timeStr) {
    var panel = document.getElementById('mrbs-form-panel');
    panel.classList.add('open');
    document.getElementById('mrbs-panel-title').textContent = roomName + ' · ' + timeStr;
    document.getElementById('fp_room').value   = roomId;
    document.getElementById('fp_rooms').value  = roomId;
    document.getElementById('fp_hour').value   = hourVal;
    document.getElementById('fp_minute').value = minuteVal;
    document.getElementById('fp_period').value = periodVal;
    document.getElementById('mrbs-full-form-link').href = editUrl;
}
function mrbsClosePanel() {
    document.getElementById('mrbs-form-panel').classList.remove('open');
}
</script>
JSPANEL;

unset($room);
require_once __DIR__ . '/trailer.php';
