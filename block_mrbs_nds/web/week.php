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
// mrbs_rlp/week.php - Week-at-a-time view

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once __DIR__ . "/config.inc.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/mrbs_nds_auth.php";
require_once __DIR__ . "/mincals.php";

$day = optional_param('day', 0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$area = optional_param('area', 0, PARAM_INT);
$room = optional_param('room', 0, PARAM_INT);
$debug_flag = optional_param('debug_flag', 0, PARAM_INT);
$morningstarts_minutes = optional_param('morningstarts_minutes', 0, PARAM_INT);
$pview = optional_param('pview', 0, PARAM_INT);
$timetohighlight = optional_param('timetohighlight', -1, PARAM_INT);

$num_of_days = $cfg_mrbs_nds->weeklength;
if ($num_of_days == 0) {
    $num_of_days = 7; //if user has not configured this, default to 7
}

// If we don't know the right date then use today:
if (($day == 0) or ($month == 0) or ($year == 0)) {
    $day = date("d");
    $month = date("m");
    $year = date("Y");
} else {
    // Make the date valid if day is more then number of days in month:
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

// Set the date back to the previous $weekstarts day (Sunday, if 0):
$time = mktime(12, 0, 0, $month, $day, $year);
if (($weekday = (date("w", $time) - $weekstarts + 7) % 7) > 0) {
    $time -= $weekday * 86400;
    $day = date("d", $time);
    $month = date("m", $time);
    $year = date("Y", $time);
}

$baseurl = new moodle_url('/blocks/mrbs_nds/web/week.php', ['day' => $day, 'month' => $month, 'year' => $year]); // Used as the basis for URLs throughout this file
$thisurl = new moodle_url($baseurl);
if ($area > 0) {
    $thisurl->param('area', $area);
} else {
    $area = get_default_area();
}
if ($room > 0) {
    $thisurl->param('room', $area);
} else {
    $room = get_default_room($area);
    // Note $room will be 0 if there are no rooms; this is checked for below.
}
if ($morningstarts_minutes > 0) {
    $thisurl->param('morningstarts_minutes', $morningstarts_minutes);
}

$PAGE->set_url($thisurl);
require_login();

// print the page header
print_header_mrbs_nds($day, $month, $year, $area, false, $room);

// Define the start and end of each day of the week in a way which is not
// affected by daylight saving...
for ($j = 0; $j <= ($num_of_days - 1); $j++) {
    // are we entering or leaving daylight saving
    // dst_change:
    // -1 => no change
    //  0 => entering DST
    //  1 => leaving DST
    //$dst_change[$j] = is_dst($month, $day + $j, $year);
    $am7[$j] = mktime($morningstarts, $morningstarts_minutes, 0, $month, $day + $j, $year);
    $pm7[$j] = mktime($eveningends, $eveningends_minutes, 0, $month, $day + $j, $year);
}

// Fetch area/room names for display
$this_area_name        = $DB->get_field('block_mrbs_rlp_area', 'area_name', ['id' => $area]) ?: '';
$this_room_name        = $DB->get_field('block_mrbs_rlp_room', 'room_name', ['id' => $room]) ?: '';
$this_room_description = $DB->get_field('block_mrbs_rlp_room', 'description', ['id' => $room]) ?: '';

// Don't continue if this area has no rooms:
if ($room <= 0) {
    echo '<div class="alert alert-info">' . s(get_string('no_rooms_for_area', 'block_mrbs_nds')) . '</div>';
    require_once __DIR__ . '/trailer.php';
    exit;
}

if ($pview != 1) {
    echo '<div class="mrbs-layout">';

    // ── Sidebar ───────────────────────────────────────────────────────────────
    echo '<div class="mrbs-sidebar">';

    // Areas
    echo '<div class="mrbs-sidebar-label">' . s(get_string('areas', 'block_mrbs_nds')) . '</div>';
    $allareas = $DB->get_records('block_mrbs_rlp_area', null, 'area_name');
    foreach ($allareas as $dbarea) {
        $areaurl = $baseurl->out(true, ['area' => $dbarea->id, 'room' => 0]);
        $active  = ($dbarea->id == $area) ? ' active' : '';
        echo '<a class="mrbs-area-item' . $active . '" href="' . s($areaurl) . '">'
             . s($dbarea->area_name) . '</a>';
    }

    // Rooms in current area
    echo '<div style="margin-top:.6rem">';
    echo '<div class="mrbs-sidebar-label">' . s(get_string('rooms', 'block_mrbs_nds')) . '</div>';
    $allrooms = $DB->get_records('block_mrbs_rlp_room', ['area_id' => $area], 'room_name');
    foreach ($allrooms as $dbroom) {
        $roomurl = $baseurl->out(true, ['area' => $area, 'room' => $dbroom->id]);
        $active  = ($dbroom->id == $room) ? ' active' : '';
        $cap     = $dbroom->capacity > 0
            ? ' <span class="small text-muted">(' . (int)$dbroom->capacity . ')</span>' : '';
        echo '<a class="mrbs-area-item' . $active . '" href="' . s($roomurl) . '">'
             . s($dbroom->room_name) . $cap . '</a>';
    }
    echo '</div>';
    echo '</div>'; // sidebar

    // ── Main content ──────────────────────────────────────────────────────────
    echo '<div class="mrbs-main">';
}

//y? are year, month and day of the previous week.
//t? are year, month and day of the next week.

$i = mktime(12, 0, 0, $month, $day - 7, $year);
$yy = date("Y", $i);
$ym = date("m", $i);
$yd = date("d", $i);

$i = mktime(12, 0, 0, $month, $day + 7, $year);
$ty = date("Y", $i);
$tm = date("m", $i);
$td = date("d", $i);

if ($pview != 1) {
    // Prev/next navigation now handled by the top nav bar (print_header_mrbs_nds).
    // Keep URLs for reference by calendar table.
    $thisweekurl = new moodle_url($baseurl, ['area' => $area, 'room' => $room]);
    $thisweekurl->remove_params('day', 'month', 'year');
    $weekbefore = new moodle_url($thisweekurl, ['year' => $yy, 'month' => $ym, 'day' => $yd]);
    $weekafter  = new moodle_url($thisweekurl, ['year' => $ty, 'month' => $tm, 'day' => $td]);
}

$roomdata = $DB->get_record('block_mrbs_rlp_room', ['id' => $room]);
$allowedtobook = allowed_to_book($USER, $roomdata);

//Get all appointments for this week in the room that we care about
// This data will be retrieved day-by-day
for ($j = 0; $j <= ($num_of_days - 1); $j++) {

    // Each row returned from the query is a meeting. Build an array of the
    // form:  d[weekday][slot][x], where x = id, color, data, long_desc.
    // [slot] is based at 000 (HHMM) for midnight, but only slots within
    // the hours of interest (morningstarts : eveningends) are filled in.
    // [id], [data] and [long_desc] are only filled in when the meeting
    // should be labeled,  which is once for each meeting on each weekday.
    // Note: weekday here is relative to the $weekstarts configuration variable.
    // If 0, then weekday=0 means Sunday. If 1, weekday=0 means Monday.

    $sql = 'room_id = ? AND start_time <= ? AND end_time > ?';
    $entries = $DB->get_records_select('block_mrbs_rlp_entry', $sql, [$room, $pm7[$j], $am7[$j]]);

    foreach ($entries as $entry) {
        if ($debug_flag) {
            echo "<br>DEBUG: result $i, id $entry->id, starts $entry->start_time, ends $entry->end_time\n";
        }

        // $d is a map of the screen that will be displayed
        // It looks like:
        //     $d[Day][Time][id]
        //                  [color]
        //                  [data]
        // where Day is in the range 0 to $num_of_days.
        // Fill in the map for this meeting. Start at the meeting start time,
        // or the day start time, whichever is later. End one slot before the
        // meeting end time (since the next slot is for meetings which start then),
        // or at the last slot in the day, whichever is earlier.
        // Note: int casts on database rows for max may be needed for PHP3.
        // Adjust the starting and ending times so that bookings which don't
        // start or end at a recognized time still appear.

        $start_t = max(round_t_down($entry->start_time, $resolution, $am7[$j]), $am7[$j]);
        $end_t = min(round_t_up($entry->end_time, $resolution, $am7[$j]) - $resolution, $pm7[$j]);
        for ($t = $start_t; $t <= $end_t; $t += $resolution) {
            $d[$j][date($format, $t)]["id"] = $entry->id;
            $d[$j][date($format, $t)]["color"] = $entry->type;
            $d[$j][date($format, $t)]["data"] = "";
            $d[$j][date($format, $t)]["long_descr"] = "";
        }

        // Show the name of the booker in the first segment that the booking
        // happens in, or at the start of the day if it started before today.
        if ($entry->end_time < $am7[$j]) {
            $d[$j][date($format, $am7[$j])]["data"] = $entry->name;
            $d[$j][date($format, $am7[$j])]["long_descr"] = $entry->description;
        } else {
            $d[$j][date($format, $start_t)]["data"] = $entry->name;
            $d[$j][date($format, $start_t)]["long_descr"] = $entry->description;
        }
    }
}

if ($debug_flag) {
    echo "<p>DEBUG:<pre>\n";
    //echo "\$dst_change = ";
    //print_r($dst_change);
    print "\n";
    print "\$am7 =\n";
    foreach ($am7 as $am7_val) {
        print "$am7_val - " . date("r", $am7_val) . "\n";
    }
    print "\$pm7 =\n";
    foreach ($pm7 as $pm7_val) {
        print "$pm7_val - " . date("r", $pm7_val) . "\n";
    }

    echo "<p>\$d =\n";
    if (gettype($d) == "array") {
        while (list($w_k, $w_v) = each($d)) {
            while (list($t_k, $t_v) = each($w_v)) {
                while (list($k_k, $k_v) = each($t_v)) {
                    echo "d[$w_k][$t_k][$k_k] = '$k_v'\n";
                }
            }
        }
    } else {
        echo "d is not an array!\n";
    }
    echo "</pre><p>\n";
}

// Include the active cell content management routines.
// Must be included before the beginnning of the main table.
if ($javascript_cursor) { // If authorized in config.inc.php, include the javascript cursor management.
    echo "<SCRIPT>InitActiveCell("
    . ($show_plus_link ? "true" : "false") . ", "
    . "true, "
    . ((false != $times_right_side) ? "true" : "false") . ", "
    . "\"$highlight_method\", "
    . "\"" . get_string('click_to_reserve', 'block_mrbs_nds') . "\""
    . ");</SCRIPT>\n";
}

//This is where we start displaying stuff
echo "<table cellspacing=0 border=1 width=\"100%\">";

// The header row contains the weekday names and short dates.
echo "<tr><th width=\"1%\"><br>" . ($enable_periods ? get_string('period', 'block_mrbs_nds') : get_string('time')) . "</th>";
if (empty($dateformat)) {
    $dformat = "%a<br>%b %d";
} else {
    $dformat = "%a<br>%d %b";
}
for ($j = 0; $j <= ($num_of_days - 1); $j++) {
    $t = mktime(12, 0, 0, $month, $day + $j, $year);
    $dayurl = new moodle_url('/blocks/mrbs_nds/web/day.php', ['year' => date('Y', $t), 'month' => date('m', $t), 'day' => date('d', $t), 'area' => $area]);
    echo '<th width="14%"><a href="' . $dayurl . '" title="' . get_string('viewday', 'block_mrbs_nds') . '">';
    echo userdate($t, $dformat) . "</a></th>\n";
}
// next line to display times on right side
if (false != $times_right_side) {
    echo "<th width=\"1%\"><br>"
    . ($enable_periods ? get_string('period', 'block_mrbs_nds') : get_string('time'))
    . "</th>";
}

echo "</tr>\n";


// This is the main bit of the display. Outer loop is for the time slots,
// inner loop is for days of the week.
// URL for highlighting a time. Don't use REQUEST_URI or you will get
// the timetohighlight parameter duplicated each time you click.
$hiliteurl = new moodle_url($baseurl, ['area' => $area, 'room' => $room]);

// if the first day of the week to be displayed contains as DST change then
// move to the next day to get the hours in the day.
//( $dst_change[0] != -1 ) ? $j = 1 : $j = 0;

$row_class = "even_row";
$starttime = mktime($morningstarts, $morningstarts_minutes, 0, $month, $day + $j, $year);
$endtime = mktime($eveningends, $eveningends_minutes, 0, $month, $day + $j, $year);
for ($t = $starttime; $t <= $endtime; $t += $resolution) {
    $row_class = ($row_class == "even_row") ? "odd_row" : "even_row";
    // use hour:minute format
    $time_t = date($format, $t);
    $hiliteurl->param('timetohighlight', $time_t);
    // Show the time linked to the URL for highlighting that time:
    echo "<tr>";
    tdcell("red");
    if ($enable_periods) {
        $time_t_stripped = preg_replace("/^0/", "", $time_t);
        echo '<a href="' . $hiliteurl . '"  title="' . get_string('highlight_line', 'block_mrbs_nds') . '">';
        echo $periods[$time_t_stripped] . "</a></td>";
    } else {
        echo '<a href="' . $hiliteurl . '" title="' . get_string('highlight_line', 'block_mrbs_nds') . '">';
        echo userdate($t, hour_min_format()) . "</a></td>";
    }

    // Color to use for empty cells: white, unless highlighting this row:
    if ($timetohighlight == $time_t) {
        $empty_color = "red";
    } else {
        $empty_color = "white";
    }

    // See note above: weekday==0 is day $weekstarts, not necessarily Sunday.
    for ($thisday = 0; $thisday <= ($num_of_days - 1); $thisday++) {
        // Three cases:
        // color:  id:   Slot is:   Color:    Link to:
        // -----   ----- --------   --------- -----------------------
        // unset   -     empty      white,red add new entry
        // set     unset used       by type   none (unlabelled slot)
        // set     set   used       by type   view entry

        $wt = mktime(12, 0, 0, $month, $day + $thisday, $year);
        $wday = date("d", $wt);
        $wmonth = date("m", $wt);
        $wyear = date("Y", $wt);

        if (isset($d[$thisday][$time_t]["id"])) {
            $id = $d[$thisday][$time_t]["id"];
            $color = $d[$thisday][$time_t]["color"];
            $descr = s($d[$thisday][$time_t]["data"]);
            $long_descr = s($d[$thisday][$time_t]["long_descr"]);
        } else {
            unset($id);
        }

        // $c is the colour of the cell that the browser sees. White normally,
        // red if were hightlighting that line and a nice attractive green if the room is booked.
        // We tell if its booked by $id having something in it
        if (isset($id)) {
            $c = $color;
        } elseif ($time_t == $timetohighlight) {
            $c = "red";
        } else {
            $c = $row_class;
        }

        // ── Slot rendering ──────────────────────────────────────────────────────
        if (!isset($id)) {
            $hour   = date("H", $t);
            $minute = date("i", $t);

            if ($pview == 1 || !$allowedtobook || !check_max_advance_days($wday, $wmonth, $wyear)) {
                tdcell($c);
                echo '&nbsp;';
                echo "</td>\n";
            } else {
                // Free bookable slot – opens side panel like day.php
                $editparams = ['room' => $room, 'area' => $area,
                               'year' => $wyear, 'month' => $wmonth, 'day' => $wday];
                if ($enable_periods) {
                    $p_val = ltrim($time_t_stripped, '0') ?: '0';
                    $editparams['period'] = $p_val;
                    $timestr_js = addslashes(s($periods[$p_val] ?? $p_val));
                } else {
                    $editparams['hour']   = $hour;
                    $editparams['minute'] = $minute;
                    $timestr_js = addslashes(s(userdate($t, hour_min_format())));
                }
                $editurl_str   = (new moodle_url('/blocks/mrbs_nds/web/edit_entry.php', $editparams))->out(false);
                $roomname_safe = addslashes(s($this_room_name));
                $js_url        = str_replace("'", "\'", $editurl_str);
                $p_arg         = isset($p_val) ? addslashes($p_val) : '';
                $onclick = "mrbsOpenPanel(this,'$js_url',$room,'$hour','$minute','$p_arg','$roomname_safe','$timestr_js')";
                echo '<td class="slot free ' . s($c) . '" style="cursor:pointer"'
                     . ' onclick="' . $onclick . '">&nbsp;</td>' . "\n";
            }
        } elseif ($descr != "") {
            // Booked – show entry name as link
            tdcell($c);
            $viewentry = new moodle_url('/blocks/mrbs_nds/web/view_entry.php',
                ['id' => $id, 'area' => $area, 'day' => $wday,
                 'month' => $wmonth, 'year' => $wyear]);
            $slot_class = ($c === 'U') ? 'mrbs-slot-name unc' : 'mrbs-slot-name';
            echo '<a href="' . $viewentry->out(false) . '" style="text-decoration:none;color:inherit;display:block">'
                 . '<div class="' . $slot_class . '">' . s($descr) . '</div></a>';
            echo "</td>\n";
        } else {
            tdcell($c);
            echo '&nbsp;';
            echo "</td>\n";
        }

    }

    // next lines to display times on right side
    if (false != $times_right_side) {
        if ($enable_periods) {
            tdcell("red");
            $time_t_stripped = preg_replace("/^0/", "", $time_t);
            echo '<a href="' . $hiliteurl . '" title="' . get_string('highlight_line', 'block_mrbs_nds') . '">';
            echo $periods[$time_t_stripped] . "</a></td>";
        } else {
            tdcell("red");
            echo '<a href="' . $hiliteurl . '" title="' . get_string('highlight_line', 'block_mrbs_nds') . '">';
            echo userdate($t, hour_min_format()) . "</a></td>";
        }
    }

    echo "</tr>\n";
}
echo "</table>";

if ($pview != 1) {
    echo '</div>'; // mrbs-main

    // ── Side form panel (same as day.php) ────────────────────────────────────
    echo '<div class="mrbs-form-panel" id="mrbs-form-panel">';
    echo '<div class="mrbs-form-panel-head">';
    echo '<span id="mrbs-panel-title">' . s(get_string('addentry', 'block_mrbs_nds')) . '</span>';
    echo '<button class="mrbs-form-panel-close" onclick="mrbsClosePanel()" aria-label="Schließen">&#x2715;</button>';
    echo '</div>';

    $handlerurl = new moodle_url('/blocks/mrbs_nds/web/edit_entry_handler.php');
    echo '<form id="mrbs-panel-form" method="post" action="' . $handlerurl->out(false) . '">';
    echo '<input type="hidden" name="sesskey"  value="' . sesskey() . '">';
    echo '<input type="hidden" name="area"     id="fp_area"   value="' . (int)$area . '">';
    echo '<input type="hidden" name="day"      id="fp_day"    value="' . (int)$day . '">';
    echo '<input type="hidden" name="month"    id="fp_month"  value="' . (int)$month . '">';
    echo '<input type="hidden" name="year"     id="fp_year"   value="' . (int)$year . '">';
    echo '<input type="hidden" name="room_id"  id="fp_room"   value="">';
    echo '<input type="hidden" name="hour"     id="fp_hour"   value="">';
    echo '<input type="hidden" name="minute"   id="fp_minute" value="">';
    echo '<input type="hidden" name="period"   id="fp_period" value="">';
    echo '<input type="hidden" name="rooms[]"  id="fp_rooms"  value="">';
    echo '<input type="hidden" name="create_by" value="' . (int)$USER->id . '">';
    echo '<input type="hidden" name="edit_type" value="">';

    echo '<div class="mb-2"><label class="form-label">' . s(get_string('namebooker', 'block_mrbs_nds')) . '</label>';
    echo '<input class="form-control" type="text" name="name" required value="' . s(fullname($USER)) . '"></div>';
    echo '<div class="mb-2"><label class="form-label">' . s(get_string('description')) . '</label>';
    echo '<textarea class="form-control" name="description" rows="2"></textarea></div>';

    echo '<div class="mb-2"><label class="form-label">' . s(get_string('duration', 'block_mrbs_nds')) . '</label>';
    echo '<div class="d-flex gap-1">';
    echo '<input class="form-control" type="number" name="duration" value="1" min="1" style="width:60px">';
    echo '<select class="form-control" name="dur_units">';
    $units_list = $enable_periods ? ['periods', 'days'] : ['minutes', 'hours', 'days'];
    foreach ($units_list as $u) {
        echo '<option value="' . s($u) . '">' . s(get_string($u, 'block_mrbs_nds')) . '</option>';
    }
    echo '</select></div></div>';

    if (!empty($typel)) {
        echo '<div class="mb-2"><label class="form-label">' . s(get_string('type', 'block_mrbs_nds')) . '</label>';
        echo '<select class="form-control" name="type">';
        foreach ($typel as $tc => $tl) {
            echo '<option value="' . s($tc) . '">' . s($tl) . '</option>';
        }
        echo '</select></div>';
    }

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

    echo "<script>\n" .
         "function mrbsOpenPanel(cell,editUrl,roomId,hourVal,minuteVal,periodVal,roomName,timeStr){\n" .
         "  var panel=document.getElementById('mrbs-form-panel');\n" .
         "  panel.classList.add('open');\n" .
         "  document.getElementById('mrbs-panel-title').textContent=roomName+' \u00b7 '+timeStr;\n" .
         "  document.getElementById('fp_room').value=roomId;\n" .
         "  document.getElementById('fp_rooms').value=roomId;\n" .
         "  document.getElementById('fp_hour').value=hourVal;\n" .
         "  document.getElementById('fp_minute').value=minuteVal;\n" .
         "  document.getElementById('fp_period').value=periodVal;\n" .
         "  var url=new URL(editUrl,location.href);\n" .
         "  var d=url.searchParams.get('day');\n" .
         "  var m=url.searchParams.get('month');\n" .
         "  var y=url.searchParams.get('year');\n" .
         "  if(d)document.getElementById('fp_day').value=d;\n" .
         "  if(m)document.getElementById('fp_month').value=m;\n" .
         "  if(y)document.getElementById('fp_year').value=y;\n" .
         "  document.getElementById('mrbs-full-form-link').href=editUrl;\n" .
         "}\n" .
         "function mrbsClosePanel(){\n" .
         "  document.getElementById('mrbs-form-panel').classList.remove('open');\n" .
         "}\n" .
         "</script>\n";
}

show_colour_key();
require_once __DIR__ . "/trailer.php";
