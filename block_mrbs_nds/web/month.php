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
// mrbs_rlp/month.php - Month-at-a-time view
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php'); //for Moodle integration
require_once __DIR__ . "/config.inc.php";
require_once __DIR__ . "/functions.php";
require_once('mrbs_nds_auth.php');

$month = optional_param('month', date("m"), PARAM_INT);
$year = optional_param('year', date("Y"), PARAM_INT);
$area = optional_param('area', 0, PARAM_INT);
$room = optional_param('room', 0, PARAM_INT);
$debug_flag = optional_param('debug_flag', 0, PARAM_INT);

// 3-value compare: Returns result of compare as "< " "= " or "> ".
function cmp3($a, $b)
{
    if ($a < $b) {
        return "< ";
    }
    if ($a == $b) {
        return "= ";
    }
    return "> ";
}

if (($month == 0) || ($year == 0) || !checkdate(intval($month), 1, intval($year))) {
    $month = date("m");
    $year = date("Y");
}
$day = 1;

$baseurl = new moodle_url('/blocks/mrbs_nds/web/month.php', ['month' => $month, 'year' => $year]); // Used as basis for URLs throughout this file
$thisurl = new moodle_url($baseurl);
if ($area > 0) {
    $thisurl->param('area', $area);
} else {
    $area = get_default_area();
}
if ($room > 0) {
    $thisurl->param('room', $room);
} else {
    $room = get_default_room($area);
    // Note $room will be 0 if there are no rooms; this is checked for below.
}

$PAGE->set_url($thisurl);
require_login();

// print the page header
print_header_mrbs_nds($day, $month, $year, $area, false, $room);

// Month view start time. This ignores morningstarts/eveningends because it
// doesn't make sense to not show all entries for the day, and it messes
// things up when entries cross midnight.
$month_start = mktime(0, 0, 0, $month, 1, $year);

// What column the month starts in: 0 means $weekstarts weekday.
$weekday_start = (date("w", $month_start) - $weekstarts + 7) % 7;

$days_in_month = date("t", $month_start);

$month_end = mktime(23, 59, 59, $month, $days_in_month, $year);

if ($enable_periods) {
    $resolution = 60;
    $morningstarts = 12;
    $eveningends = 12;
    $eveningends_minutes = count($periods) - 1;
}

// Define the start and end of each day of the month in a way which is not
// affected by daylight saving...
for ($j = 1; $j <= $days_in_month; $j++) {
    // are we entering or leaving daylight saving
    // dst_change:
    // -1 => no change
    //  0 => entering DST
    //  1 => leaving DST
    //$dst_change[$j] = is_dst($month, $j, $year);
    if (empty($enable_periods)) {
        $midnight[$j] = mktime(0, 0, 0, $month, $j, $year);
        $midnight_tonight[$j] = mktime(23, 59, 59, $month, $j, $year);
    } else {
        $midnight[$j] = mktime(12, 0, 0, $month, $j, $year);
        $midnight_tonight[$j] = mktime(12, count($periods), 59, $month, $j, $year);
    }
}

// Fetch area/room names for display
$this_area_name = $DB->get_field('block_mrbs_rlp_area', 'area_name', ['id' => $area]) ?: '';
$this_room_name = $DB->get_field('block_mrbs_rlp_room', 'room_name', ['id' => $room]) ?: '';

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

    echo '<div class="mrbs-sidebar-label">' . s(get_string('areas', 'block_mrbs_nds')) . '</div>';
    $allareas = $DB->get_records('block_mrbs_rlp_area', null, 'area_name');
    foreach ($allareas as $dbarea) {
        $areaurl = $baseurl->out(true, ['area' => $dbarea->id, 'room' => 0]);
        $active  = ($dbarea->id == $area) ? ' active' : '';
        echo '<a class="mrbs-area-item' . $active . '" href="' . s($areaurl) . '">'
             . s($dbarea->area_name) . '</a>';
    }

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

    echo '<div class="mrbs-main">';
    echo '<h3 class="mrbs-section-title mb-2">' . s($this_area_name) . ' &ndash; ' . s($this_room_name) . '</h3>';
}

if ($debug_flag) {
    echo "<p>DEBUG: month=$month year=$year start=$weekday_start range=$month_start:$month_end\n";
}

// Used below: localized "all day" text but with non-breaking spaces:
$all_day = str_replace(" ", "&nbsp;", get_string('all_day', 'block_mrbs_nds'));

//Get all meetings for this month in the room that we care about
// This data will be retrieved day-by-day fo the whole month
for ($day_num = 1; $day_num <= $days_in_month; $day_num++) {
    // Build an array of information about each day in the month.
    // The information is stored as:
    //  d[monthday]["id"][] = ID of each entry, for linking.
    //  d[monthday]["data"][] = "start-stop" times or "name" of each entry.

    $entries = $DB->get_records_select('block_mrbs_rlp_entry', 'room_id = ? AND start_time <= ? AND end_time > ?', [$room, $midnight_tonight[$day_num], $midnight[$day_num]], 'start_time');

    foreach ($entries as $entry) {
        if ($debug_flag) {
            echo "<br>DEBUG: result id {$entry->id}, starts {$entry->start_time}, ends {$entry->end_time}\n";
            echo "<br>DEBUG: Entry {$entry->id} day $day_num\n";
        }

        $d[$day_num]["id"][] = $entry->id;
        $d[$day_num]["shortdescrip"][] = $entry->name;

        // Describe the start and end time, accounting for "all day"
        // and for entries starting before/ending after today.
        // There are 9 cases, for start time < = or > midnight this morning,
        // and end time < = or > midnight tonight.
        // Use ~ (not -) to separate the start and stop times, because MSIE
        // will incorrectly line break after a -.

        if (empty($enable_periods)) {
            switch (cmp3($entry->start_time, $midnight[$day_num]) . cmp3($entry->end_time, $midnight_tonight[$day_num] + 1)) {
                case "> < ":         // Starts after midnight, ends before midnight
                case "= < ":         // Starts at midnight, ends before midnight
                    $d[$day_num]["data"][] = userdate($entry->start_time, hour_min_format()) . "~" . userdate($entry->end_time, hour_min_format());
                    break;
                case "> = ":         // Starts after midnight, ends at midnight
                    $d[$day_num]["data"][] = userdate($entry->start_time, hour_min_format()) . "~24:00";
                    break;
                case "> > ":         // Starts after midnight, continues tomorrow
                    $d[$day_num]["data"][] = userdate($entry->start_time, hour_min_format()) . "~====>";
                    break;
                case "= = ":         // Starts at midnight, ends at midnight
                    $d[$day_num]["data"][] = $all_day;
                    break;
                case "= > ":         // Starts at midnight, continues tomorrow
                    $d[$day_num]["data"][] = $all_day . "====>";
                    break;
                case "< < ":         // Starts before today, ends before midnight
                    $d[$day_num]["data"][] = "<====~" . userdate($entry->end_time, hour_min_format());
                    break;
                case "< = ":         // Starts before today, ends at midnight
                    $d[$day_num]["data"][] = "<====" . $all_day;
                    break;
                case "< > ":         // Starts before today, continues tomorrow
                    $d[$day_num]["data"][] = "<====" . $all_day . "====>";
                    break;
            }
        } else {
            $start_str = str_replace("&nbsp;", " ", period_time_string($entry->start_time));
            $end_str = str_replace("&nbsp;", " ", period_time_string($entry->end_time, -1));
            switch (cmp3($entry->start_time, $midnight[$day_num]) . cmp3($entry->end_time, $midnight_tonight[$day_num] + 1)) {
                case "> < ":         // Starts after midnight, ends before midnight
                case "= < ":         // Starts at midnight, ends before midnight
                    $d[$day_num]["data"][] = $start_str . "~" . $end_str;
                    break;
                case "> = ":         // Starts after midnight, ends at midnight
                    $d[$day_num]["data"][] = $start_str . "~24:00";
                    break;
                case "> > ":         // Starts after midnight, continues tomorrow
                    $d[$day_num]["data"][] = $start_str . "~====>";
                    break;
                case "= = ":         // Starts at midnight, ends at midnight
                    $d[$day_num]["data"][] = $all_day;
                    break;
                case "= > ":         // Starts at midnight, continues tomorrow
                    $d[$day_num]["data"][] = $all_day . "====>";
                    break;
                case "< < ":         // Starts before today, ends before midnight
                    $d[$day_num]["data"][] = "<====~" . $end_str;
                    break;
                case "< = ":         // Starts before today, ends at midnight
                    $d[$day_num]["data"][] = "<====" . $all_day;
                    break;
                case "< > ":         // Starts before today, continues tomorrow
                    $d[$day_num]["data"][] = "<====" . $all_day . "====>";
                    break;
            }
        }
    }
}
if ($debug_flag) {
    echo "<p>DEBUG: Array of month day data:<p><pre>\n";
    for ($i = 1; $i <= $days_in_month; $i++) {
        if (isset($d[$i]["id"])) {
            $n = count($d[$i]["id"]);
            echo "Day $i has $n entries:\n";
            for ($j = 0; $j < $n; $j++) {
                echo "  ID: " . $d[$i]["id"][$j] .
                " Data: " . $d[$i]["data"][$j] . "\n";
            }
        }
    }
    echo "</pre>\n";
}

// Include the active cell content management routines.
$today_day   = (int) date('j');
$today_month = (int) date('n');
$today_year  = (int) date('Y');
$is_current_month = ($month == $today_month && $year == $today_year);

echo '<table class="mrbs-month-grid">';
echo '<tr>';
for ($weekcol = 0; $weekcol < 7; $weekcol++) {
    $wd = ($weekcol + $weekstarts) % 7;
    echo '<th>' . s(day_name($wd)) . '</th>';
}
echo '</tr><tr>';

// Skip days in week before start of month (previous month, shown dimmed).
$prev_month_days = (int) date('t', mktime(0, 0, 0, $month - 1, 1, $year));
for ($weekcol = 0; $weekcol < $weekday_start; $weekcol++) {
    $pmday = $prev_month_days - $weekday_start + $weekcol + 1;
    echo '<td class="other-month"><div class="mrbs-day-num">' . (int)$pmday . '</div></td>';
}

$roomdata      = $DB->get_record('block_mrbs_rlp_room', ['id' => $room]);
$allowedtobook = allowed_to_book($USER, $roomdata);

for ($cday = 1; $cday <= $days_in_month; $cday++) {
    if ($weekcol == 0 && $cday > 1) {
        echo '</tr><tr>';
    }

    $is_today  = ($is_current_month && $cday == $today_day);
    $td_class  = $is_today ? 'today' : '';

    $dayurl = new moodle_url('/blocks/mrbs_nds/web/day.php',
        ['year' => $year, 'month' => $month, 'day' => $cday, 'area' => $area, 'room' => $room]);

    echo '<td class="' . $td_class . '" style="cursor:pointer" onclick="location.href=\''
         . $dayurl->out(false) . '\'">';

    $num_class = $is_today ? 'mrbs-day-num text-primary fw-bold' : 'mrbs-day-num';
    echo '<div class="' . $num_class . '">' . (int)$cday . '</div>';

    // Show entries for this day.
    if (isset($d[$cday]['id'][0])) {
        $n = count($d[$cday]['id']);
        $shown = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($shown >= 3 && $n > 4) {
                echo '<div class="small text-muted">+' . ($n - $shown) . ' ' . s(get_string('more', 'core')) . '</div>';
                break;
            }
            $viewentry_url = new moodle_url('/blocks/mrbs_nds/web/view_entry.php',
                ['id' => $d[$cday]['id'][$i], 'day' => $cday, 'month' => $month, 'year' => $year]);
            $title_attr = s(substr($d[$cday]['shortdescrip'][$i], 0, 60));
            echo '<a href="' . $viewentry_url->out(false) . '" class="mrbs-month-event"'
                 . ' title="' . $title_attr . '" onclick="event.stopPropagation()">'
                 . s(substr($d[$cday]['shortdescrip'][$i], 0, 18)) . '</a>';
            $shown++;
        }
    }

    echo '</td>';

    if (++$weekcol == 7) {
        $weekcol = 0;
    }
}

// Skip from end of month to end of week (next month, shown dimmed).
if ($weekcol > 0) {
    $nmday = 1;
    for (; $weekcol < 7; $weekcol++) {
        echo '<td class="other-month"><div class="mrbs-day-num">' . (int)$nmday . '</div></td>';
        $nmday++;
    }
}
echo '</tr></table>';

if ($pview != 1) {
    echo '</div>'; // mrbs-main
    echo '</div>'; // mrbs-layout
}

show_colour_key();

require_once __DIR__ . "/trailer.php";
