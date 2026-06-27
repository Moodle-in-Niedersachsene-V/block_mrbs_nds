<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Core helper functions for block_mrbs_nds web pages.
 *
 * Phase 1+2 changes vs. block_mrbs_nds/web/functions.php:
 *  - get_config('block_mrbs_nds') → get_config('block_mrbs_nds')   [Phase 1]
 *  - All table references: block_mrbs_rlp_* → block_mrbs_nds_*      [Phase 1]
 *  - All capability strings: block/mrbs_rlp → block/mrbs_nds        [Phase 1]
 *  - All strftime %-format strings replaced with Moodle date formats [Phase 1]
 *  - Navigation rebuilt with Bootstrap 5 nav instead of HTML tables  [Phase 2]
 *  - <font> tags, align=, bgcolor= attributes removed                [Phase 2]
 *  - language="javascript" removed from <script> tags                [Phase 2]
 *  - include → require_once throughout                               [Phase 2]
 *  - s() applied to every user-data variable echoed as HTML          [Phase 1]
 *  - $HTTP_REFERER → $_SERVER['HTTP_REFERER'] with s()               [Phase 1]
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(__DIR__ . '/mrbs_nds_auth.php');

$pview = optional_param('pview', 0, PARAM_INT);

// ── Header / Navigation ───────────────────────────────────────────────────────

function print_user_header_mrbs_nds(
    ?int $day = null, ?int $month = null, ?int $year = null, ?int $area = null
): void {
    print_header_mrbs_nds($day, $month, $year, $area, true);
}

function print_header_mrbs_nds(
    ?int $day = null, ?int $month = null, ?int $year = null,
    ?int $area = null, bool $userview = false
): void {
    global $OUTPUT, $PAGE, $USER, $CFG, $search_str, $locale_warning, $pview;

    // Phase 1: underscore, not slash.
    $cfg = get_config('block_mrbs_nds');
    $title = get_string('blockname', 'block_mrbs_nds');

    if (!get_site()) {
        redirect(new moodle_url('/admin/index.php'));
    }

    $context = context_system::instance();
    require_capability('block/mrbs_nds:viewmrbs', $context);

    $day   = $day   ?: (int) date('d');
    $month = $month ?: (int) date('m');
    $year  = $year  ?: (int) date('Y');

    $search_str = $search_str ?? '';

    $PAGE->set_context($context);
    $PAGE->navbar->add($title);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title($title);
    $PAGE->set_heading(format_string($title));

    // Phase 2: AMD module instead of plain JS file.
    $PAGE->requires->js_call_amd('block_mrbs_nds/dateselector', 'init', [
        get_string('mon', 'calendar'),
        get_string('tue', 'calendar'),
        get_string('wed', 'calendar'),
        get_string('thu', 'calendar'),
        get_string('fri', 'calendar'),
        get_string('sat', 'calendar'),
        get_string('sun', 'calendar'),
    ]);

    echo $OUTPUT->header();

    echo '<div id="mrbs_nds_container">';

    if ($pview == 1) {
        return;
    }

    if (!empty($locale_warning)) {
        echo $OUTPUT->notification(s($locale_warning), 'warning');
    }

    $homeurl       = new moodle_url('/blocks/mrbs_nds/web/index.php');
    $gotourl       = new moodle_url($userview
        ? '/blocks/mrbs_nds/web/userweek.php'
        : '/blocks/mrbs_nds/web/day.php');
    $roomsearchurl = new moodle_url('/blocks/mrbs_nds/web/roomsearch.php');
    $helpurl       = new moodle_url('/blocks/mrbs_nds/web/help.php',
                         ['day' => $day, 'month' => $month, 'year' => $year]);
    $adminurl      = new moodle_url('/blocks/mrbs_nds/web/admin.php',
                         ['day' => $day, 'month' => $month, 'year' => $year]);
    $reporturl     = new moodle_url('/blocks/mrbs_nds/web/report.php');
    $searchurl     = new moodle_url('/blocks/mrbs_nds/web/search.php');
    $searchadvurl  = new moodle_url($searchurl, ['advanced' => 1]);
    $editurl       = new moodle_url('/blocks/mrbs_nds/web/edit_entry.php');

    $level    = authGetUserLevel($USER->id);
    $canadmin = ($level >= 2);
    $currentpage = basename($_SERVER['PHP_SELF']);

    // Phase 2: Bootstrap 5 nav instead of HTML table.
    echo '<nav class="navbar navbar-expand-md navbar-light bg-light mb-3 border rounded">';
    echo '<div class="container-fluid flex-wrap gap-2">';

    // Brand / home link.
    echo '<a class="navbar-brand fw-bold" href="' . s($homeurl) . '">'
         . s(get_string('mrbs_nds', 'block_mrbs_nds')) . '</a>';

    // Date-goto form.
    echo '<form class="d-flex align-items-center gap-1 me-2" action="' . s($gotourl) . '" method="get" name="form_mrbs_goto">';
    genDateSelector('', $day, $month, $year);
    if (!empty($area)) {
        echo '<input type="hidden" name="area" value="' . (int) $area . '">';
    }
    echo '<button type="submit" class="btn btn-sm btn-secondary">'
         . s(get_string('goto', 'block_mrbs_nds')) . '</button>';
    echo '</form>';

    if (!$userview) {
        // Add entry.
        $activeclass = ($currentpage === 'edit_entry.php') ? ' active" aria-current="page' : '';
        echo '<a class="nav-link' . $activeclass . '" href="' . s($editurl) . '">'
             . s(get_string('addentry', 'block_mrbs_nds')) . '</a>';

        // Room search.
        $activeclass = ($currentpage === 'roomsearch.php') ? ' active" aria-current="page' : '';
        echo '<a class="nav-link' . $activeclass . '" href="' . s($roomsearchurl) . '">'
             . s(get_string('roomsearch', 'block_mrbs_nds')) . '</a>';

        // Admin.
        if ($canadmin) {
            $activeclass = ($currentpage === 'admin.php') ? ' active" aria-current="page' : '';
            echo '<a class="nav-link' . $activeclass . '" href="' . s($adminurl) . '">'
                 . s(get_string('admin')) . '</a>';
        }

        // Report.
        $activeclass = ($currentpage === 'report.php') ? ' active" aria-current="page' : '';
        echo '<a class="nav-link' . $activeclass . '" href="' . s($reporturl) . '">'
             . s(get_string('report')) . '</a>';

        // Search form.
        $activeclass = ($currentpage === 'search.php') ? ' active" aria-current="page' : '';
        echo '<form class="d-flex align-items-center gap-1" method="get" action="' . s($searchurl) . '">';
        echo '<a class="nav-link p-0' . $activeclass . '" href="' . s($searchadvurl) . '">'
             . s(get_string('search')) . '</a>';
        echo '<input class="form-control form-control-sm" type="search" name="search_str"'
             . ' value="' . s($search_str) . '" style="width:8rem">';
        echo '<input type="hidden" name="day"   value="' . (int) $day . '">';
        echo '<input type="hidden" name="month" value="' . (int) $month . '">';
        echo '<input type="hidden" name="year"  value="' . (int) $year . '">';
        if (!empty($area)) {
            echo '<input type="hidden" name="area" value="' . (int) $area . '">';
        }
        echo '</form>';
    }

    // Help.
    echo '<a class="nav-link ms-auto" href="' . s($helpurl) . '">'
         . s(get_string('help'))
         . '</a>';

    echo '</div></nav>';
}

// ── Duration helpers ──────────────────────────────────────────────────────────

function toTimeString(float &$dur, mixed &$units): void {
    if ($dur >= 60) {
        $dur /= 60;
        if ($dur >= 60) {
            $dur /= 60;
            if ($dur >= 24 && fmod($dur, 24) == 0) {
                $dur /= 24;
                if ($dur >= 7 && fmod($dur, 7) == 0) {
                    $dur /= 7;
                    if ($dur >= 52 && fmod($dur, 52) == 0) {
                        $dur /= 52;
                        $units = get_string('years');
                    } else {
                        $units = get_string('weeks', 'block_mrbs_nds');
                    }
                } else {
                    $units = get_string('days');
                }
            } else {
                $units = get_string('hours', 'block_mrbs_nds');
            }
        } else {
            $units = get_string('minutes');
        }
    } else {
        $units = get_string('secs');
    }
}

function toPeriodString(int $start_period, float &$dur, mixed &$units): void {
    global $periods;

    $max_periods = count($periods);
    $dur /= 60;

    if ($dur >= $max_periods || $start_period == 0) {
        if ($start_period == 0 && $dur == $max_periods) {
            $units = get_string('days');
            $dur   = 1;
            return;
        }
        $dur /= 60;
        if ($dur >= 24 && is_int((int) $dur) && fmod($dur, 1) == 0) {
            $dur /= 24;
            $units = get_string('days');
        } else {
            $dur   = fmod($dur * 60, $max_periods) + floor($dur * 60 / (24 * 60)) * $max_periods;
            $units = get_string('periods', 'block_mrbs_nds');
        }
        return;
    }

    $units = get_string('periods', 'block_mrbs_nds');
}

// ── Date selector ─────────────────────────────────────────────────────────────

/**
 * Phase 1: replaced strftime('%b') with Moodle's userdate() using 'strftimemonthshort'.
 * Phase 2: uses Bootstrap 5 form-select instead of 'custom-select'.
 */
function genDateSelector(
    string $prefix, int $day, int $month, int $year,
    bool $updatefreerooms = false, bool $roomsearch = false
): void {
    $day   = $day   ?: (int) date('d');
    $month = $month ?: (int) date('m');
    $year  = $year  ?: (int) date('Y');

    $onchange_day = '';
    if ($updatefreerooms) {
        $onchange_day = ' onchange="updateFreeRooms()"';
    } elseif ($roomsearch) {
        $onchange_day = ' onchange="RoomSearch()"';
    }

    echo '<select class="form-select form-select-sm d-inline-block w-auto"'
         . ' name="' . s($prefix) . 'day"' . $onchange_day . '>';
    for ($i = 1; $i <= 31; $i++) {
        $sel = ($i == $day) ? ' selected' : '';
        echo '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
    }
    echo '</select>';

    $month_extra = '';
    if ($updatefreerooms) {
        $month_extra = ',true';
    } elseif ($roomsearch) {
        $month_extra = ',false,true';
    }
    $month_onchange = 'onchange="ChangeOptionDays(this.form,\'' . s($prefix) . '\'' . $month_extra . ')"';

    echo '<select class="form-select form-select-sm d-inline-block w-auto"'
         . ' name="' . s($prefix) . 'month" ' . $month_onchange . '>';
    for ($i = 1; $i <= 12; $i++) {
        // Phase 1: Moodle's get_string for month abbreviations (no strftime).
        $mname = userdate(mktime(12, 0, 0, $i, 1, $year), get_string('strftimemonth', 'langconfig'));
        $sel = ($i == $month) ? ' selected' : '';
        echo '<option value="' . $i . '"' . $sel . '>' . s($mname) . '</option>';
    }
    echo '</select>';

    $year_onchange = 'onchange="ChangeOptionDays(this.form,\'' . s($prefix) . '\'' . $month_extra . ')"';

    echo '<select class="form-select form-select-sm d-inline-block w-auto"'
         . ' name="' . s($prefix) . 'year" ' . $year_onchange . '>';
    $min = min($year, (int) date('Y')) - 5;
    $max = max($year, (int) date('Y')) + 5;
    for ($i = $min; $i <= $max; $i++) {
        $sel = ($i == $year) ? ' selected' : '';
        echo '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
    }
    echo '</select>';
}

// ── Error helper ──────────────────────────────────────────────────────────────

function fatal_error(bool $need_header, string $message): void {
    if ($need_header) {
        print_header_mrbs_nds(0, 0, 0, 0);
    }
    echo $message;
    require_once __DIR__ . '/trailer.php';
    exit;
}

// ── Default area / room ───────────────────────────────────────────────────────

function get_default_area(): int {
    global $DB;
    $area = $DB->get_records('block_mrbs_nds_area', null, 'area_name', 'id', 0, 1);
    if (empty($area)) {
        return 0;
    }
    return (int) reset($area)->id;
}

function get_default_room(int $area): int {
    global $DB;
    $room = $DB->get_records('block_mrbs_nds_room', ['area_id' => $area], 'room_name', 'id', 0, 1);
    if (empty($room)) {
        return 0;
    }
    return (int) reset($room)->id;
}

// ── Date / time formatters ────────────────────────────────────────────────────

/**
 * Phase 1: all strftime %-format strings replaced with Moodle lang-string formats.
 * Moodle's userdate() still accepts strftime strings on Moodle < 5.0, but
 * PHP 8.1 emits deprecation warnings and PHP 8.3 removed strftime().
 * We use the Moodle lang-string equivalents throughout.
 */

function day_name(int $daynumber): string {
    // Map day number (0=Sun,1=Mon,...) to Moodle calendar lang string.
    // Moodle's 'calendar' component has 'sunday','monday',... as full names.
    static $daykeys = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
    return get_string($daykeys[$daynumber % 7], 'calendar');
}

function hour_min_format(): string {
    global $twentyfourhour_format;
    return $twentyfourhour_format
        ? get_string('strftimetime24', 'langconfig')   // H:i
        : get_string('strftimetime',   'langconfig');  // g:i a
}

function period_date_string(int $t, int $mod_time = 0): array {
    global $periods;
    $time  = getdate($t);
    $p_num = (int) $time['minutes'] + $mod_time;
    $p_num = max(0, min($p_num, count($periods) - 1));
    return [
        $p_num,
        s($periods[$p_num]) . userdate($t, ', ' . get_string('strftimedaydate', 'langconfig')),
    ];
}

function period_time_string(int $t, int $mod_time = 0): string {
    global $periods;
    $time  = getdate($t);
    $p_num = (int) $time['minutes'] + $mod_time;
    $p_num = max(0, min($p_num, count($periods) - 1));
    return s($periods[$p_num]);
}

function time_date_string(int $t): string {
    global $twentyfourhour_format;
    $fmt = $twentyfourhour_format
        ? get_string('strftimedatetimeshort', 'langconfig')
        : get_string('strftimedatetimeshort', 'langconfig');
    return userdate($t, $fmt);
}

// ── Area / Room select forms ──────────────────────────────────────────────────

function make_area_select_html(string $link, int $current, int $year, int $month, int $day): string {
    global $DB;

    $out  = '<form name="areaChangeForm" method="get" action="' . s($link) . '">';
    $out .= '<select class="form-select form-select-sm d-inline-block w-auto"'
            . ' name="area" onchange="document.areaChangeForm.submit()">';

    $areas = $DB->get_records('block_mrbs_nds_area', null, 'area_name');
    foreach ($areas as $area) {
        $sel  = ($area->id == $current) ? ' selected' : '';
        $out .= '<option' . $sel . ' value="' . (int) $area->id . '">' . s($area->area_name) . '</option>';
    }

    $out .= '</select>';
    $out .= '<input type="hidden" name="day"   value="' . (int) $day . '">';
    $out .= '<input type="hidden" name="month" value="' . (int) $month . '">';
    $out .= '<input type="hidden" name="year"  value="' . (int) $year . '">';
    $out .= '<noscript><button type="submit" class="btn btn-sm btn-secondary ms-1">'
            . s(get_string('savechanges')) . '</button></noscript>';
    $out .= '</form>';

    return $out;
}

function make_room_select_html(string $link, int $area, int $current, int $year, int $month, int $day): string {
    global $DB;

    $out  = '<form name="roomChangeForm" method="get" action="' . s($link) . '">';
    $out .= '<select class="form-select form-select-sm d-inline-block w-auto"'
            . ' name="room" onchange="document.roomChangeForm.submit()">';

    $rooms = $DB->get_records('block_mrbs_nds_room', ['area_id' => $area], 'room_name');
    foreach ($rooms as $room) {
        $sel  = ($room->id == $current) ? ' selected' : '';
        $out .= '<option' . $sel . ' value="' . (int) $room->id . '">' . s($room->room_name) . '</option>';
    }

    $out .= '</select>';
    $out .= '<input type="hidden" name="day"   value="' . (int) $day . '">';
    $out .= '<input type="hidden" name="month" value="' . (int) $month . '">';
    $out .= '<input type="hidden" name="year"  value="' . (int) $year . '">';
    $out .= '<input type="hidden" name="area"  value="' . (int) $area . '">';
    $out .= '<noscript><button type="submit" class="btn btn-sm btn-secondary ms-1">'
            . s(get_string('savechanges')) . '</button></noscript>';
    $out .= '</form>';

    return $out;
}

// ── DST helpers ───────────────────────────────────────────────────────────────

function is_dst(int $month, int $day, int $year, int $hour = -1): int {
    if ($hour !== -1 && $hour > 3) {
        return -1;
    }
    $prev = mktime(12, 0, 0, $month, $day - 1, $year);
    $curr = mktime(12, 0, 0, $month, $day,     $year);
    if (!date('I', $prev) && date('I', $curr)) {
        return 0; // entering DST
    }
    if (date('I', $prev) && !date('I', $curr)) {
        return 1; // leaving DST
    }
    return -1;
}

function cross_dst(int $start, int $end): int {
    if (!date('I', $start) && date('I', $end)) {
        return -3600;
    }
    if (date('I', $start) && !date('I', $end)) {
        return 3600;
    }
    return 0;
}

// ── Colour key ────────────────────────────────────────────────────────────────

function tdcell(string $colclass): void {
    static $ecolors = null;
    if ($ecolors === null) {
        $ecolors = [
            'A' => '#FFCCFF', 'B' => '#99CCCC', 'C' => '#FF9999',
            'D' => '#FFFF99', 'E' => '#C0E0FF', 'F' => '#FFCC99',
            'G' => '#FF6666', 'H' => '#66FFFF', 'I' => '#DDFFDD',
            'J' => '#CCCCCC', 'red' => '#FFF0F0', 'white' => '#FFFFFF',
        ];
    }
    if (isset($ecolors[$colclass])) {
        echo '<td class="' . s($colclass) . '" style="background-color:' . $ecolors[$colclass] . ' !important">';
    } else {
        echo '<td class="' . s($colclass) . '">';
    }
}

function show_colour_key(): void {
    global $typel;
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-bordered"><tr>';
    $nct = 0;
    for ($ct = 'A'; $ct <= 'Z'; $ct++) {
        if (!empty($typel[$ct])) {
            if (++$nct > 5) {
                $nct = 0;
                echo '</tr><tr>';
            }
            tdcell($ct);
            echo s($typel[$ct]) . '</td>';
        }
    }
    echo '</tr></table></div>';
}

// ── Rounding helpers ──────────────────────────────────────────────────────────

function round_t_down(int $t, int $resolution, int $am7): int {
    return $t - abs(($t - $am7) % $resolution);
}

function round_t_up(int $t, int $resolution, int $am7): int {
    if (($t - $am7) % $resolution != 0) {
        return $t + $resolution - abs(($t - $am7) % $resolution);
    }
    return $t;
}

// ── Mail helpers ──────────────────────────────────────────────────────────────

function removeMailUnicode(string $string): string {
    // Moodle uses UTF-8 throughout; no conversion needed.
    return $string;
}

/**
 * Phase 1: getMailPeriodDateString – replaced strftime with Moodle userdate format.
 */
function getMailPeriodDateString(int $t, int $mod_time = 0): array {
    global $periods;
    $time  = getdate($t);
    $p_num = (int) $time['minutes'] + $mod_time;
    $p_num = max(0, min($p_num, count($periods) - 1));
    return [
        $p_num,
        $periods[$p_num] . userdate($t, ', ' . get_string('strftimedaydate', 'langconfig')),
    ];
}

function getMailTimeDateString(int $t, bool $inc_time = true): string {
    global $twentyfourhour_format;
    if (!$inc_time) {
        return userdate($t, get_string('strftimedaydate', 'langconfig'));
    }
    $fmt = $twentyfourhour_format
        ? get_string('strftimedatetimeshort', 'langconfig')
        : get_string('strftimedatetimeshort', 'langconfig');
    return userdate($t, $fmt);
}

function unHtmlEntities(string $string): string {
    return html_entity_decode($string, ENT_QUOTES, 'UTF-8');
}

function get_user_by_email(string $email): mixed {
    $user = get_complete_user_data('email', $email);
    return $user ?: false;
}

/**
 * Phase 1: to_hr_time – get_config with underscore prefix.
 */
function to_hr_time(int $time): string {
    $cfg = get_config('block_mrbs_nds');
    if (!empty($cfg->enable_periods) && !empty($cfg->periods)) {
        $periods = explode("\n", $cfg->periods);
        $period  = (int) date('i', $time);
        return trim($periods[$period] ?? '');
    }
    return date('G:i', $time);
}

// ── Max advance days ──────────────────────────────────────────────────────────

function check_max_advance_days_internal(DateTime $checkdate): bool {
    global $max_advance_days;

    if ($max_advance_days < 0) {
        return true;
    }

    $context = context_system::instance();
    if (has_capability('block/mrbs_nds:ignoremaxadvancedays', $context)) {
        return true;
    }

    $now = new DateTime();
    if ($checkdate <= $now) {
        return true;
    }

    $interval = (int) $checkdate->format('U') - (int) $now->format('U');
    $interval = (int) ($interval / (24 * 60 * 60));

    return $interval <= $max_advance_days;
}

function check_max_advance_days_timestamp(int $ts): bool {
    $d = new DateTime();
    $d->setTimestamp($ts);
    $check = new DateTime();
    $check->setDate((int) $d->format('Y'), (int) $d->format('m'), (int) $d->format('d'));
    return check_max_advance_days_internal($check);
}

function check_max_advance_days(int $day, int $month, int $year): bool {
    $check = new DateTime();
    $check->setDate($year, $month, $day);
    return check_max_advance_days_internal($check);
}

// ── Booking permission ────────────────────────────────────────────────────────

function allowed_to_book(stdClass $user, stdClass $room): bool {
    if (empty($room->booking_users)) {
        return true;
    }
    $allowed = array_map('trim', explode(',', $room->booking_users));
    // booking_users stores user IDs as integers.
    return in_array((string) $user->id, $allowed, true);
}

// ── Email notifications ───────────────────────────────────────────────────────

function compareEntries(string $new_value, string $previous_value, bool $new_entry): string {
    if ($new_entry) {
        return $new_value;
    }
    if ($new_value !== $previous_value) {
        return $new_value . ' (' . $previous_value . ')';
    }
    return $new_value;
}

function notifyAdminOnBooking(bool $new_entry, int $new_id, ?int $modified_enddate = null): bool {
    global $DB, $url_base, $returl, $name, $description, $area_name;
    global $room_name, $starttime, $duration, $dur_units, $endtime;
    global $rep_enddate, $typel, $type, $create_by, $rep_type, $enable_periods;
    global $rep_opt, $rep_num_weeks, $mail_previous, $weekstarts;

    $recipientlist = [];
    $id_table = ($rep_type > 0) ? 'rep' : 'e';

    $userinfos = $DB->get_record('user', ['id' => $create_by]);
    $fullname  = $userinfos ? fullname($userinfos) : '';

    if (defined('MAIL_AREA_ADMIN_ON_BOOKINGS') && MAIL_AREA_ADMIN_ON_BOOKINGS) {
        if ($new_entry) {
            $sql = "SELECT a.area_admin_email
                      FROM {block_mrbs_nds_room} r
                      JOIN {block_mrbs_nds_area} a ON a.id = r.area_id
                      JOIN {block_mrbs_nds_entry} e ON e.room_id = r.id
                     WHERE e.id = ?";
            $emails = $DB->get_records_sql($sql, [$new_id], 0, 1);
            if (!empty($emails)) {
                $email = reset($emails);
                if (!empty($email->area_admin_email)) {
                    $recipientlist[] = $email->area_admin_email;
                }
            }
        } elseif (!empty($mail_previous['area_admin_email'])) {
            $recipientlist[] = $mail_previous['area_admin_email'];
        }
    }

    if (defined('MAIL_ROOM_ADMIN_ON_BOOKINGS') && MAIL_ROOM_ADMIN_ON_BOOKINGS) {
        if ($new_entry) {
            $sql = "SELECT r.room_admin_email
                      FROM {block_mrbs_nds_room} r
                      JOIN {block_mrbs_nds_entry} e ON e.room_id = r.id
                     WHERE e.id = ?";
            $emails = $DB->get_records_sql($sql, [$new_id], 0, 1);
            if (!empty($emails)) {
                $email = reset($emails);
                if (!empty($email->room_admin_email)) {
                    $recipientlist[] = $email->room_admin_email;
                }
            }
        } elseif (!empty($mail_previous['room_admin_email'])) {
            $recipientlist[] = $mail_previous['room_admin_email'];
        }
    }

    if (defined('MAIL_BOOKER') && MAIL_BOOKER) {
        $uid   = $new_entry ? $create_by : ($mail_previous['createdby'] ?? 0);
        $email = $uid ? $DB->get_field('user', 'email', ['id' => $uid]) : false;
        if ($email) {
            $recipientlist[] = $email;
        }
    }

    $recipientlist = array_unique(array_filter($recipientlist));
    if (empty($recipientlist)) {
        return false;
    }

    $subjdetails = new stdClass();
    if ($enable_periods) {
        [, $startdatestr] = getMailPeriodDateString($starttime);
        $subjdetails->date = $startdatestr;
    } else {
        $subjdetails->date = getMailTimeDateString($starttime);
    }
    $subjdetails->user       = $fullname;
    $subjdetails->room       = $room_name;
    $subjdetails->entry_type = $typel[$type] ?? '';

    $subject = get_string(
        $new_entry ? 'mail_subject_newentry' : 'mail_subject_entry',
        'block_mrbs_nds', $subjdetails
    );
    $subject = str_replace('&nbsp;', ' ', $subject);

    $body  = get_string($new_entry ? 'mail_body_new_entry' : 'mail_body_changed_entry', 'block_mrbs_nds') . ":\n\n";
    $viewurl = new moodle_url('/blocks/mrbs_nds/web/view_entry.php', ['id' => $new_id]);
    $body .= $viewurl->out(false);
    if ($rep_type > 0) {
        $body .= '&series=1';
    }
    $body .= "\n";

    if (defined('MAIL_DETAILS') && MAIL_DETAILS) {
        $body .= "\n" . get_string('namebooker', 'block_mrbs_nds') . ': '
                 . compareEntries($name, $mail_previous['namebooker'] ?? '', $new_entry) . "\n";
        $body .= get_string('description') . ': '
                 . compareEntries($description, $mail_previous['description'] ?? '', $new_entry) . "\n";
        $body .= get_string('createdby', 'block_mrbs_nds') . ': '
                 . compareEntries($fullname, $mail_previous['fullname'] ?? '', $new_entry) . "\n";
    }

    $result   = true;
    $frommail = defined('MAIL_FROM') ? MAIL_FROM : '';
    $fromuser = $frommail ? get_user_by_email($frommail) : false;

    if (!$fromuser) {
        return false;
    }

    foreach ($recipientlist as $recip) {
        $recipuser = get_user_by_email($recip);
        if ($recipuser) {
            if (!email_to_user($recipuser, $fromuser, $subject, $body)) {
                debugging('block_mrbs_nds: failed to send email to ' . $recip, DEBUG_DEVELOPER);
                $result = false;
            }
        }
    }

    return $result;
}

function notifyAdminOnDelete(array $mail_previous): bool {
    global $typel, $enable_periods, $DB;

    $recipientlist = [];

    if (defined('MAIL_ADMIN_ON_BOOKINGS') && MAIL_ADMIN_ON_BOOKINGS && defined('MAIL_RECIPIENTS')) {
        $recipientlist[] = MAIL_RECIPIENTS;
    }
    if (defined('MAIL_AREA_ADMIN_ON_BOOKINGS') && MAIL_AREA_ADMIN_ON_BOOKINGS
            && !empty($mail_previous['area_admin_email'])) {
        $recipientlist[] = $mail_previous['area_admin_email'];
    }
    if (defined('MAIL_ROOM_ADMIN_ON_BOOKINGS') && MAIL_ROOM_ADMIN_ON_BOOKINGS
            && !empty($mail_previous['room_admin_email'])) {
        $recipientlist[] = $mail_previous['room_admin_email'];
    }

    if (empty($recipientlist)) {
        $uid   = $mail_previous['createdby'] ?? 0;
        $email = $uid ? $DB->get_field('user', 'email', ['id' => $uid]) : false;
        if ($email) {
            $recipientlist[] = $email;
        }
    }

    $recipientlist = array_unique(array_filter($recipientlist));
    if (empty($recipientlist)) {
        return false;
    }

    $subjdetails                 = new stdClass();
    $subjdetails->date           = unHtmlEntities($mail_previous['start_date'] ?? '');
    $subjdetails->user           = $mail_previous['fullname'] ?? '';
    $subjdetails->name           = $mail_previous['namebooker'] ?? '';
    $subjdetails->room           = $mail_previous['room_name'] ?? '';
    $subjdetails->entry_type     = $typel[$mail_previous['type'] ?? ''] ?? '';

    $subject = get_string('mail_subject_delete', 'block_mrbs_nds', $subjdetails);
    $subject = str_replace('&nbsp;', ' ', $subject);

    $body = get_string('mail_body_del_entry', 'block_mrbs_nds') . ":\n\n";
    $body .= get_string('namebooker', 'block_mrbs_nds') . ': ' . ($mail_previous['namebooker'] ?? '') . "\n";
    $body .= get_string('description') . ': ' . ($mail_previous['description'] ?? '') . "\n";
    $body .= get_string('createdby', 'block_mrbs_nds') . ': ' . ($mail_previous['fullname'] ?? '') . "\n";

    $frommail = defined('MAIL_FROM') ? MAIL_FROM : '';
    $fromuser = $frommail ? get_user_by_email($frommail) : false;
    if (!$fromuser) {
        return false;
    }

    $result = true;
    foreach ($recipientlist as $recip) {
        $recipuser = get_user_by_email($recip);
        if ($recipuser) {
            if (!email_to_user($recipuser, $fromuser, $subject, $body)) {
                $result = false;
            }
        }
    }

    return $result;
}

function getPreviousEntryData(int $id, int $series): array {
    global $DB, $enable_periods, $weekstarts;

    $sql = "SELECT e.name, e.description, e.create_by,
                   r.room_name, a.area_name, e.type, e.room_id, e.repeat_id,
                   e.timestamp,
                   (e.end_time - e.start_time) AS tbl_e_duration,
                   e.start_time AS tbl_e_start_time,
                   e.end_time   AS tbl_e_end_time,
                   a.area_admin_email, r.room_admin_email";

    if ($series) {
        $sql .= ", re.rep_type, re.rep_opt, re.rep_num_weeks,
                   (re.end_time - re.start_time) AS tbl_r_duration,
                   re.start_time AS tbl_r_start_time,
                   re.end_time   AS tbl_r_end_time,
                   re.end_date   AS tbl_r_end_date";
    }

    $sql .= " FROM {block_mrbs_nds_entry} e
              JOIN {block_mrbs_nds_room} r  ON r.id = e.room_id
              JOIN {block_mrbs_nds_area} a  ON a.id = r.area_id";

    if ($series) {
        $sql .= " JOIN {block_mrbs_nds_repeat} re ON re.id = e.repeat_id";
    }

    $sql .= " WHERE e.id = ?";
    if ($series) {
        $sql .= " AND e.repeat_id = re.id";
    }

    $details = $DB->get_record_sql($sql, [$id], MUST_EXIST);

    $userinfos = $DB->get_record('user', ['id' => $details->create_by]);
    $fullname  = $userinfos ? fullname($userinfos) : '';

    $mail_previous = [
        'namebooker'      => $details->name,
        'description'     => $details->description,
        'createdby'       => $details->create_by,
        'fullname'        => $fullname,
        'room_name'       => $details->room_name,
        'area_name'       => $details->area_name,
        'type'            => $details->type,
        'room_id'         => $details->room_id,
        'repeat_id'       => $details->repeat_id,
        'updated'         => getMailTimeDateString($details->timestamp),
        'area_admin_email' => $details->area_admin_email,
        'room_admin_email' => $details->room_admin_email,
    ];

    if ($enable_periods) {
        if (!$series) {
            [$mail_previous['start_period'], $mail_previous['start_date']] =
                getMailPeriodDateString($details->tbl_e_start_time);
            [$mail_previous['end_period'], $mail_previous['end_date']] =
                getMailPeriodDateString($details->tbl_e_end_time, -1);
            $mail_previous['duration'] = $details->tbl_e_duration
                - cross_dst($details->tbl_e_start_time, $details->tbl_e_end_time);
        } else {
            [$mail_previous['start_period'], $mail_previous['start_date']] =
                getMailPeriodDateString($details->tbl_r_start_time);
            [$mail_previous['end_period'], $mail_previous['end_date']] =
                getMailPeriodDateString($details->tbl_r_end_time);
            $mail_previous['rep_end_date'] = getMailTimeDateString($details->tbl_r_end_date, false);
            $mail_previous['duration']     = $details->tbl_r_duration
                - cross_dst($details->tbl_r_start_time, $details->tbl_r_end_time);
            $mail_previous = _fill_rep_opt($mail_previous, $details, $weekstarts);
        }
        toPeriodString($mail_previous['start_period'], $mail_previous['duration'], $mail_previous['dur_units']);
    } else {
        if (!$series) {
            $mail_previous['start_date'] = getMailTimeDateString($details->tbl_e_start_time);
            $mail_previous['end_date']   = getMailTimeDateString($details->tbl_e_end_time);
            $mail_previous['duration']   = $details->tbl_e_duration
                - cross_dst($details->tbl_e_start_time, $details->tbl_e_end_time);
        } else {
            $mail_previous['start_date']   = getMailTimeDateString($details->tbl_r_start_time);
            $mail_previous['end_date']     = getMailTimeDateString($details->tbl_r_end_time);
            $mail_previous['rep_end_date'] = getMailTimeDateString($details->tbl_r_end_date, false);
            $mail_previous['duration']     = $details->tbl_r_duration
                - cross_dst($details->tbl_r_start_time, $details->tbl_r_end_time);
            $mail_previous = _fill_rep_opt($mail_previous, $details, $weekstarts);
        }
        toTimeString($mail_previous['duration'], $mail_previous['dur_units']);
    }

    $mail_previous['rep_type'] = $series ? ($details->rep_type ?? 0) : 0;

    return $mail_previous;
}

/** @internal Helper: decode rep_opt bitmask string into human-readable day names. */
function _fill_rep_opt(array $mail_previous, stdClass $details, int $weekstarts): array {
    $rep_day = [false, false, false, false, false, false, false];
    if (in_array($details->rep_type ?? 0, [2, 6])) {
        for ($i = 0; $i < 7; $i++) {
            $rep_day[$i] = isset($details->rep_opt[$i]) && $details->rep_opt[$i] !== '0';
        }
        $mail_previous['rep_num_weeks'] = ($details->rep_type == 6)
            ? $details->rep_num_weeks : '';
    }
    $opt = '';
    for ($i = 0; $i < 7; $i++) {
        $wday = ($i + $weekstarts) % 7;
        if ($rep_day[$wday]) {
            $opt .= day_name($wday) . ' ';
        }
    }
    $mail_previous['rep_opt'] = trim($opt);
    return $mail_previous;
}
