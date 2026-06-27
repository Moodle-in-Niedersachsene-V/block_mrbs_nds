<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Auth wrappers for block_mrbs_nds.
 *
 * Phase 1 changes vs. mrbs_nds_auth.php:
 *  - Removed dynamic include "auth_$auth[type].php" (path inclusion risk)
 *  - Removed dynamic include "session_$auth[session].php"
 *  - All capability strings: block/mrbs_rlp → block/mrbs_nds
 *  - authGetUserLevel() accepts int $userid directly (was mixed string/int)
 *  - getUserID() simplified (was doing extra DB lookup of own ID)
 *  - getWritable() now compares integer user IDs
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

/**
 * Returns the MRBS access level for the given Moodle user ID.
 *
 * 0 = view only
 * 1 = create / edit own bookings
 * 2 = full administration
 */
function authGetUserLevel(int $userid): int {
    $context = context_system::instance();

    if (has_capability('block/mrbs_nds:administermrbs', $context, $userid)) {
        return 2;
    }
    if (has_capability('block/mrbs_nds:editmrbs', $context, $userid)) {
        return 1;
    }
    if (has_capability('block/mrbs_nds:editmrbs_unconfirmed', $context, $userid, false)) {
        return 1;
    }
    return 0;
}

/**
 * Phase 1 fix: accepts integer user ID (not mixed string).
 */
function getAuthorised(int $level): bool {
    global $USER;
    return isset($USER->id) && authGetUserLevel((int) $USER->id) >= $level;
}

/**
 * Phase 1 fix: compare integer IDs (not string usernames).
 */
function getWritable(int $creator_id, int $current_user_id): bool {
    if ($creator_id === $current_user_id) {
        return true;
    }
    return authGetUserLevel($current_user_id) >= 2;
}

function getUserID(): int {
    global $USER;
    return (int) ($USER->id ?? 0);
}

function getUserName(): string {
    global $USER;
    return $USER->username ?? '';
}

function getUserinfos(int $userid): ?stdClass {
    global $DB;
    return $DB->get_record('user', ['id' => $userid]) ?: null;
}

function getFullname(): string {
    global $USER;
    return fullname($USER);
}

function showAccessDenied(int $day, int $month, int $year, ?int $area): void {
    global $OUTPUT;
    print_header_mrbs_nds($day, $month, $year, $area);
    echo $OUTPUT->box(
        get_string('accessdenied', 'block_mrbs_nds') . '<br/>'
        . get_string('norights', 'block_mrbs_nds'),
        'generalbox boxaligncenter'
    );
    echo '<br/>';
    echo $OUTPUT->footer();
}
