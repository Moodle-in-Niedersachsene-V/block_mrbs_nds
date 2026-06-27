<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Delete a booking entry (single or whole series).
 *
 * Phase 1+2 changes:
 *  - require_once instead of include
 *  - Table names: block_mrbs_rlp_* → block_mrbs_nds_*
 *  - Capability: block/mrbs_rlp → block/mrbs_nds
 *  - Phase 1: strftime %-format → Moodle date format
 *  - Phase 1: create_by now INT (user.id)
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
global $PAGE, $DB, $USER;

require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/mrbs_nds_auth.php');
require_once(__DIR__ . '/mrbs_nds_sql.php');

$id     = required_param('id',     PARAM_INT);
$series = optional_param('series', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/blocks/mrbs_nds/web/del_entry.php', ['id' => $id]));
require_login();
require_sesskey();

$context = context_system::instance();
$PAGE->set_context($context);

$info = mrbs_ndsGetEntryInfo($id);

if (!$info) {
    redirect(new moodle_url('/blocks/mrbs_nds/web/day.php'));
}

// Phase 1: strftime '%d/%m/%Y' → Moodle date format.
$fmt   = get_string('strftimedatefullshort', 'langconfig');
$day   = (int) date('d', $info->start_time);
$month = (int) date('m', $info->start_time);
$year  = (int) date('Y', $info->start_time);

// Use PHP date() instead of userdate() for integer extraction (no strftime needed).
$day   = (int) date('d', $info->start_time);
$month = (int) date('m', $info->start_time);
$year  = (int) date('Y', $info->start_time);
$area  = mrbs_ndsGetRoomArea($info->room_id);

$desturl = new moodle_url('/blocks/mrbs_nds/web/day.php',
    ['day' => $day, 'month' => $month, 'year' => $year, 'area' => $area]);

if (!getAuthorised(1)) {
    showAccessDenied($day, $month, $year, $area);
    exit();
}

// Check if user is room admin.
$roomadmin = false;
if (has_capability('block/mrbs_nds:editmrbs_unconfirmed', $context, null, false)) {
    $adminemail = $DB->get_field('block_mrbs_nds_room', 'room_admin_email', ['id' => $info->room_id]);
    if ($adminemail === $USER->email) {
        $roomadmin = true;
    }
}

if (defined('MAIL_ADMIN_ON_DELETE') && MAIL_ADMIN_ON_DELETE) {
    $mail_previous = getPreviousEntryData($id, $series);
}

$result = mrbs_ndsDelEntry(getUserID(), $id, (bool) $series, true, $roomadmin);

if ($result && !empty($mail_previous)) {
    notifyAdminOnDelete($mail_previous);
}

redirect($desturl);
