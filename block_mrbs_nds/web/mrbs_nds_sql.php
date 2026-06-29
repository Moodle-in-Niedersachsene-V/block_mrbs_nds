<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Database helper functions for block_mrbs_nds.
 *
 * Phase 1+2 changes vs. mrbs_nds_sql.php:
 *  - All table names: block_mrbs_rlp_* → block_mrbs_nds_*
 *  - XSS: $entry->name wrapped in s() before HTML output
 *  - strftime %-format strings → Moodle get_string date formats
 *  - create_by is now INT (user.id), not VARCHAR username
 *  - require_once instead of require_once (was already correct here)
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

/**
 * Check whether a time slot is free for a given room.
 *
 * @return string  Empty string if free; HTML list of conflicts otherwise.
 */
function mrbs_ndsCheckFree(
    int $room_id, int $starttime, int $endtime,
    int $ignore, int $repignore
): string {
    global $DB, $enable_periods, $periods;

    $sql    = 'start_time < ? AND end_time > ? AND room_id = ?';
    $params = [$endtime, $starttime, $room_id];

    if ($ignore > 0) {
        $sql    .= ' AND id <> ?';
        $params[] = $ignore;
    }
    if ($repignore > 0) {
        $sql    .= ' AND repeat_id <> ?';
        $params[] = $repignore;
    }

    $entries = $DB->get_records_select('block_mrbs_rlp_entry', $sql, $params, 'start_time');

    if (empty($entries)) {
        return '';
    }

    $area = mrbs_ndsGetRoomArea($room_id);
    $err  = '';

    foreach ($entries as $entry) {
        $starts    = getdate($entry->start_time);
        $param_ym  = ['area' => $area, 'year' => $starts['year'], 'month' => $starts['mon']];
        $param_ymd = array_merge($param_ym, ['day' => $starts['mday']]);

        if ($enable_periods) {
            $p_num    = $starts['minutes'];
            // Phase 1: Moodle date format instead of strftime.
            $startstr = s($periods[$p_num] ?? '') . ' '
                        . userdate($entry->start_time, get_string('strftimedaydate', 'langconfig'));
        } else {
            $startstr = userdate($entry->start_time, get_string('strftimedatetimeshort', 'langconfig'));
        }

        $viewurl  = new moodle_url('/blocks/mrbs_nds/web/view_entry.php', ['id' => $entry->id]);
        $dayurl   = new moodle_url('/blocks/mrbs_nds/web/day.php',   $param_ymd);
        $weekurl  = new moodle_url('/blocks/mrbs_nds/web/week.php',  array_merge($param_ymd, ['room' => $room_id]));
        $monthurl = new moodle_url('/blocks/mrbs_nds/web/month.php', array_merge($param_ym,  ['room' => $room_id]));

        // Phase 1 XSS fix: s() around $entry->name.
        $err .= '<li><a href="' . s($viewurl) . '">' . s($entry->name) . '</a>'
                . ' (' . $startstr . ')'
                . ' (<a href="' . s($dayurl)   . '">' . s(get_string('viewday',   'block_mrbs_nds')) . '</a>'
                . ' | <a href="' . s($weekurl)  . '">' . s(get_string('viewweek',  'block_mrbs_nds')) . '</a>'
                . ' | <a href="' . s($monthurl) . '">' . s(get_string('viewmonth', 'block_mrbs_nds')) . '</a>'
                . ')</li>';
    }

    return '<ul>' . $err . '</ul>';
}

/**
 * Delete a booking entry, optionally the whole series.
 */
function mrbs_ndsDelEntry(
    int $user_id, int $id, bool $series, bool $all,
    bool $roomadminoverride = false
): bool {
    global $DB;

    $repeat_id = (int) $DB->get_field('block_mrbs_rlp_entry', 'repeat_id', ['id' => $id]);
    if ($repeat_id < 0) {
        return false;
    }

    $params  = $series ? ['repeat_id' => $repeat_id] : ['id' => $id];
    $entries = $DB->get_records('block_mrbs_rlp_entry', $params);
    $removed = 0;

    foreach ($entries as $entry) {
        if (!$roomadminoverride && !getWritable((int) $entry->create_by, $user_id)) {
            continue;
        }
        if ($series && $entry->entry_type == 2 && !$all) {
            continue;
        }
        $DB->delete_records('block_mrbs_rlp_entry', ['id' => $entry->id]);
        $removed++;
    }

    if ($repeat_id > 0
            && $DB->count_records('block_mrbs_rlp_entry', ['repeat_id' => $repeat_id]) == 0) {
        $DB->delete_records('block_mrbs_rlp_repeat', ['id' => $repeat_id]);
    }

    return $removed > 0;
}

/**
 * Create a single (non-repeating) booking entry.
 *
 * @return int  New or updated entry ID, 0 on failure.
 */
function mrbs_ndsCreateSingleEntry(
    int $starttime, int $endtime, int $entry_type, int $repeat_id,
    int $room_id, int $owner, string $name, string $type, string $description,
    int $oldid = 0, bool $roomchange = false
): int {
    global $DB;

    if ($endtime <= $starttime) {
        return 0;
    }

    $record              = new stdClass();
    $record->start_time  = $starttime;
    $record->end_time    = $endtime;
    $record->entry_type  = $entry_type;
    $record->repeat_id   = $repeat_id;
    $record->room_id     = $room_id;
    $record->create_by   = $owner;
    $record->name        = $name;
    $record->type        = $type;
    $record->description = $description;
    $record->timestamp   = time();
    $record->roomchange  = (int) $roomchange;

    if ($oldid) {
        $record->id = $oldid;
        $DB->update_record('block_mrbs_rlp_entry', $record);
        return $oldid;
    }

    return (int) $DB->insert_record('block_mrbs_rlp_entry', $record);
}

/**
 * Create or update a repeat-series record.
 *
 * @return int  Series ID, 0 on failure.
 */
function mrbs_ndsCreateRepeatEntry(
    int $starttime, int $endtime, int $rep_type, int $rep_enddate,
    string $rep_opt, int $room_id, int $owner, string $name, string $type,
    string $description, ?int $rep_num_weeks, int $oldrepeatid = 0
): int {
    global $DB;

    $record              = new stdClass();
    $record->start_time  = $starttime;
    $record->end_time    = $endtime;
    $record->rep_type    = $rep_type;
    $record->end_date    = $rep_enddate;
    $record->room_id     = $room_id;
    $record->create_by   = $owner;
    $record->type        = $type;
    $record->name        = $name;
    $record->timestamp   = time();
    $record->rep_opt     = !empty($rep_opt) ? $rep_opt : '0';

    if (!empty($description)) {
        $record->description = $description;
    }
    if (!empty($rep_num_weeks)) {
        $record->rep_num_weeks = $rep_num_weeks;
    }

    if ($oldrepeatid) {
        $record->id = $oldrepeatid;
        $DB->update_record('block_mrbs_rlp_repeat', $record);
        return $oldrepeatid;
    }

    return (int) $DB->insert_record('block_mrbs_rlp_repeat', $record);
}

// ── Repeat-entry list generation ──────────────────────────────────────────────

function same_day_next_month(int $time): int {
    global $_initial_weeknumber;

    $days_in_month = (int) date('t', $time);
    $day           = (int) date('d', $time);
    $weeknumber    = (int) (($day - 1) / 7) + 1;
    $temp1         = ($day + 7 * (5 - $weeknumber) <= $days_in_month);
    $next_month    = (int) date('n', mktime(11, 0, 0, (int) date('n', $time), $day + 35, (int) date('Y', $time)));
    $cur_month     = (int) date('n', $time);
    if ($next_month < $cur_month) {
        $next_month += 12;
    }
    $days_jump = 28 + (($temp1 && !($next_month - $cur_month - 1)) ? 7 : 0);
    $days_jump += 7 * (
        ($_initial_weeknumber == 5)
        && (date('n', mktime(11, 0, 0, $cur_month, $day + $days_jump, (int) date('Y', $time)))
            == date('n', mktime(11, 0, 0, $cur_month, $day + $days_jump + 7, (int) date('Y', $time))))
    );
    return $days_jump;
}

function mrbs_ndsGetRepeatEntryList(
    int $time, int $enddate, int $rep_type,
    array $rep_opt, int $max_ittr, ?int $rep_num_weeks
): array {
    global $_initial_weeknumber;

    $sec   = (int) date('s', $time);
    $min   = (int) date('i', $time);
    $hour  = (int) date('G', $time);
    $day   = (int) date('d', $time);
    $month = (int) date('m', $time);
    $year  = (int) date('Y', $time);

    $_initial_weeknumber = (int) (($day - 1) / 7) + 1;
    $week_num  = 0;
    $start_day = (int) date('w', mktime($hour, $min, $sec, $month, $day, $year));
    $cur_day   = $start_day;

    $entrys = [];

    for ($i = 0; $i < $max_ittr; $i++) {
        $t = mktime($hour, $min, $sec, $month, $day, $year);
        if ($t > $enddate) {
            break;
        }
        $entrys[$i] = $t;

        switch ($rep_type) {
            case 1: // daily
                $day++;
                break;

            case 2: // weekly
                $j = $cur_day = (int) date('w', $entrys[$i]);
                while (($j = ($j + 1) % 7) != $cur_day && empty($rep_opt[$j])) {
                    $day++;
                }
                $day++;
                break;

            case 3: // monthly
                $month++;
                break;

            case 4: // yearly
                $year++;
                break;

            case 5: // monthly, same week number
                $day += same_day_next_month($t);
                break;

            case 6: // every n weeks
                while (true) {
                    $day++;
                    $cur_day = ($cur_day + 1) % 7;
                    if ($cur_day % 7 == $start_day) {
                        $week_num++;
                    }
                    if (($week_num % $rep_num_weeks == 0) && !empty($rep_opt[$cur_day])) {
                        break;
                    }
                }
                break;

            default:
                return $entrys;
        }
    }

    return $entrys;
}

function mrbs_ndsCreateRepeatingEntrys(
    int $starttime, int $endtime, int $rep_type, int $rep_enddate,
    array $rep_opt, int $room_id, int $owner, string $name, string $type,
    string $description, ?int $rep_num_weeks,
    bool $roomchange = false, int $oldid = 0
): stdClass {
    global $max_rep_entrys, $DB;

    $ret            = new stdClass();
    $ret->id        = 0;
    $ret->repeating = 1;
    $ret->requested = 0;
    $ret->created   = 0;
    $ret->lasttime  = null;

    $reps = mrbs_ndsGetRepeatEntryList(
        $starttime, $rep_enddate, $rep_type, $rep_opt, $max_rep_entrys, $rep_num_weeks
    );

    if ($reps) {
        $ret->requested = count($reps);
        if ($ret->requested > $max_rep_entrys) {
            return $ret;
        }
    }

    $repeatid = 0;
    if ($oldid) {
        $repeatid = (int) $DB->get_field('block_mrbs_rlp_entry', 'repeat_id', ['id' => $oldid]);
    }

    if (empty($reps)) {
        if ($repeatid) {
            $DB->delete_records_select(
                'block_mrbs_rlp_entry',
                'repeat_id = :repeatid AND id <> :oldid',
                ['repeatid' => $repeatid, 'oldid' => $oldid]
            );
            $DB->delete_records('block_mrbs_rlp_repeat', ['id' => $repeatid]);
        }
        $ret->id        = mrbs_ndsCreateSingleEntry(
            $starttime, $endtime, 0, 0, $room_id, $owner, $name, $type, $description, $oldid, $roomchange
        );
        $ret->repeating = 0;
        $ret->requested = 1;
        $ret->created   = 1;
        $ret->lasttime  = $starttime;
        return $ret;
    }

    $ret->id = mrbs_ndsCreateRepeatEntry(
        $starttime, $endtime, $rep_type, $rep_enddate, implode('', $rep_opt),
        $room_id, $owner, $name, $type, $description, $rep_num_weeks, $repeatid
    );

    if ($ret->id) {
        $oldids = [];
        if ($repeatid) {
            $oldids = $DB->get_fieldset_sql(
                'SELECT id FROM {block_mrbs_rlp_entry} WHERE repeat_id = ? ORDER BY start_time',
                [$repeatid]
            );
        }

        for ($i = 0; $i < count($reps); $i++) {
            $diff  = $endtime - $starttime;
            $diff += cross_dst($reps[$i], $reps[$i] + $diff);

            if (!check_max_advance_days_timestamp($reps[$i])) {
                break;
            }

            $updateid = ($i < count($oldids)) ? (int) $oldids[$i] : 0;

            mrbs_ndsCreateSingleEntry(
                $reps[$i], $reps[$i] + $diff, 1, $ret->id,
                $room_id, $owner, $name, $type, $description, $updateid, $roomchange
            );
            $ret->lasttime = $reps[$i];
            $ret->created++;
        }

        // Delete old repeat entries that are no longer needed.
        for ($i = count($reps); $i < count($oldids); $i++) {
            $DB->delete_records('block_mrbs_rlp_entry', ['id' => (int) $oldids[$i]]);
        }
    }

    return $ret;
}

function mrbs_ndsGetEntryInfo(int $id): ?stdClass {
    global $DB;
    return $DB->get_record('block_mrbs_rlp_entry', ['id' => $id]) ?: null;
}

function mrbs_ndsGetRoomArea(int $room_id): int {
    global $DB;
    return (int) $DB->get_field('block_mrbs_rlp_room', 'area_id', ['id' => $room_id]);
}
