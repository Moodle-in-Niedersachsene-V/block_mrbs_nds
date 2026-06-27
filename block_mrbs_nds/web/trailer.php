<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page footer / date-navigation trailer.
 *
 * Phase 1+2 changes:
 *  - All strftime %-format strings replaced with Moodle lang-string formats
 *  - All block_mrbs_rlp_* → block_mrbs_nds_*
 *  - All lang-string component: block_mrbs_rlp → block_mrbs_nds
 *  - <P><HR> → <hr class="my-2"> (Bootstrap 5 / HTML5)
 *  - <BR> → <br>
 *  - <b class="active"> → <strong class="active">
 *  - require_once instead of require_once (already was here)
 *  - Phase 2: missing require_login / capability check added via header
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

if ($pview != 1) {

    $year  = isset($year)  ? (int) $year  : (int) date('Y');
    $month = isset($month) ? (int) $month : (int) date('m');
    $day   = isset($day)   ? (int) $day   : (int) date('d');

    $params = empty($area) ? [] : ['area' => (int) $area];

    echo '<hr class="my-2">';
    echo '<div class="mrbs-nav-trailer small">';

    // ── Day links ─────────────────────────────────────────────────────────────
    echo '<strong>' . s(get_string('viewday', 'block_mrbs_nds')) . ':</strong> ';
    for ($i = -6; $i <= 7; $i++) {
        $ctime  = mktime(0, 0, 0, $month, $day + $i, $year);
        // Phase 1: Moodle date format instead of strftime '%b %d' / '%d %b'.
        $fmt    = empty($dateformat)
            ? get_string('strftimedayshort', 'langconfig')  // e.g. "Jan 5"
            : get_string('strftimedayshort',   'langconfig'); // e.g. "5 Jan"
        $str    = userdate($ctime, $fmt);
        $cyear  = (int) date('Y', $ctime);
        $cmonth = (int) date('m', $ctime);
        $cday   = (int) date('d', $ctime);

        if ($i !== -6) {
            echo ' | ';
        }
        $url = new moodle_url('/blocks/mrbs_nds/web/day.php',
            array_merge(['year' => $cyear, 'month' => $cmonth, 'day' => $cday], $params));
        if ($i === 0) {
            echo '<strong class="active">[ ';
        }
        echo '<a href="' . s($url) . '">' . s($str) . '</a>';
        if ($i === 0) {
            echo ' ]</strong>';
        }
    }

    // ── Week links ────────────────────────────────────────────────────────────
    echo '<br><strong>' . s(get_string('viewweek', 'block_mrbs_nds')) . ':</strong> ';

    if (!empty($room)) {
        $params['room'] = is_object($room) ? (int) $room->id : (int) $room;
    }

    $ctime    = mktime(0, 0, 0, $month, $day, $year);
    $skipback = ((int) date('w', $ctime) - $weekstarts + 7) % 7;

    for ($i = -4; $i <= 4; $i++) {
        $ctime  = mktime(0, 0, 0, $month, $day + 7 * $i - $skipback, $year);
        $cweek  = (int) date('W', $ctime);
        $cday   = (int) date('d', $ctime);
        $cmonth = (int) date('m', $ctime);
        $cyear  = (int) date('Y', $ctime);

        if ($i !== -4) {
            echo ' | ';
        }

        // Phase 1: strftime '%b %d' → Moodle format.
        if (!empty($view_week_number)) {
            $str = (string) $cweek;
        } else {
            $fmt = empty($dateformat)
                ? get_string('strftimedayshort', 'langconfig')
                : get_string('strftimedayshort', 'langconfig');
            $str = userdate($ctime, $fmt);
        }

        $url = new moodle_url('/blocks/mrbs_nds/web/week.php',
            array_merge(['year' => $cyear, 'month' => $cmonth, 'day' => $cday], $params));
        if ($i === 0) {
            echo '<strong class="active">[ ';
        }
        echo '<a href="' . s($url) . '">' . s($str) . '</a>';
        if ($i === 0) {
            echo ' ]</strong>';
        }
    }

    // ── Month links ───────────────────────────────────────────────────────────
    echo '<br><strong>' . s(get_string('viewmonth', 'block_mrbs_nds')) . ':</strong> ';
    for ($i = -2; $i <= 6; $i++) {
        $ctime  = mktime(0, 0, 0, $month + $i, 1, $year);
        // Phase 1: '%b %Y' → Moodle format.
        $str    = userdate($ctime, get_string('strftimemonthyear', 'langconfig'));
        $cmonth = (int) date('m', $ctime);
        $cyear  = (int) date('Y', $ctime);

        if ($i !== -2) {
            echo ' | ';
        }
        $url = new moodle_url('/blocks/mrbs_nds/web/month.php',
            array_merge(['year' => $cyear, 'month' => $cmonth], $params));
        if ($i === 0) {
            echo '<strong class="active">[ ';
        }
        echo '<a href="' . s($url) . '">' . s($str) . '</a>';
        if ($i === 0) {
            echo ' ]</strong>';
        }
    }

    echo '</div>';

    echo '<hr class="my-2">';
    $thisurl = new moodle_url($PAGE->url, ['pview' => 1]);
    echo '<p class="text-center"><a href="' . s($thisurl) . '">'
         . s(get_string('ppreview', 'block_mrbs_nds')) . '</a></p>';
}

echo '</div>'; // Close 'mrbs_nds_container'.

echo $OUTPUT->footer();
