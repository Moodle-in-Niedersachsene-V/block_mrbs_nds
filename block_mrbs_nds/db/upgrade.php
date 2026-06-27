<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade / migration script for block_mrbs_nds.
 *
 * MIGRATION STRATEGY (from block_mrbs_rlp → block_mrbs_nds):
 * ─────────────────────────────────────────────────────────────
 * When Moodle first installs block_mrbs_nds (version 2026062701) it runs
 * install.xml to create the new block_mrbs_nds_* tables.  This upgrade
 * function then checks whether the old block_mrbs_rlp_* tables still exist
 * and, if so, copies all their data into the new tables and renames the old
 * tables so they are preserved as a fallback (block_mrbs_rlp_*_migrated).
 *
 * Key differences between old and new schema:
 *  • Table prefix: block_mrbs_rlp_* → block_mrbs_nds_*
 *  • entry.create_by / repeat.create_by: was VARCHAR(80) username (partially
 *    migrated to user.id in v1.4.6); we normalise to INT user.id here.
 *  • entry.name: was VARCHAR(80); widened to VARCHAR(255).
 *  • New indexes on entry (start_time, end_time, room_id, create_by).
 *  • Foreign-key definitions added (declarative only; enforced by Moodle DDL).
 *
 * IMPORTANT: This script NEVER drops the original rlp tables until the admin
 * explicitly confirms migration via the admin panel (admin.php cleanup step).
 * Until then they are renamed with the suffix _migrated so that a rollback to
 * the old plugin is still possible.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve create_by to a Moodle user.id.
 *
 * The field may contain:
 *  a) an integer user.id  (v1.4.6+)
 *  b) a username string   (older versions)
 *
 * Returns the integer user.id, or 0 if the user cannot be found.
 */
function block_mrbs_nds_resolve_create_by(string $raw): int {
    global $DB;

    $raw = trim($raw);
    if ($raw === '' || $raw === '0') {
        return 0;
    }

    // Already numeric → treat as user.id.
    if (ctype_digit($raw)) {
        $id = (int) $raw;
        if ($DB->record_exists('user', ['id' => $id])) {
            return $id;
        }
    }

    // Fall back to username lookup.
    $uid = $DB->get_field('user', 'id', ['username' => $raw]);
    return $uid ? (int) $uid : 0;
}

function xmldb_block_mrbs_nds_upgrade(int $oldversion): bool {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    // ── Step 2026062701: initial migration from block_mrbs_rlp ───────────────
    if ($oldversion < 2026062701) {

        $old_tables = ['area', 'room', 'entry', 'repeat'];
        $all_old_exist = true;
        foreach ($old_tables as $t) {
            if (!$dbman->table_exists(new xmldb_table('block_mrbs_rlp_' . $t))) {
                $all_old_exist = false;
                break;
            }
        }

        if ($all_old_exist) {

            // ── 1. Migrate AREAS ─────────────────────────────────────────────
            $areas = $DB->get_records('block_mrbs_rlp_area');
            foreach ($areas as $old) {
                // Only insert if not already present (idempotent).
                if (!$DB->record_exists('block_mrbs_nds_area', ['id' => $old->id])) {
                    $new = new stdClass();
                    $new->id               = $old->id;
                    $new->area_name        = $old->area_name;
                    $new->area_admin_email = $old->area_admin_email ?? '';
                    $DB->import_record('block_mrbs_nds_area', $new);
                }
            }

            // ── 2. Migrate ROOMS ─────────────────────────────────────────────
            $rooms = $DB->get_records('block_mrbs_rlp_room');
            foreach ($rooms as $old) {
                if (!$DB->record_exists('block_mrbs_nds_room', ['id' => $old->id])) {
                    $new = new stdClass();
                    $new->id               = $old->id;
                    $new->area_id          = $old->area_id;
                    $new->room_name        = $old->room_name;
                    $new->description      = $old->description ?? '';
                    $new->capacity         = $old->capacity;
                    $new->room_admin_email = $old->room_admin_email ?? '';
                    $new->booking_users    = $old->booking_users ?? '';
                    $DB->import_record('block_mrbs_nds_room', $new);
                }
            }

            // ── 3. Migrate REPEATS ───────────────────────────────────────────
            $repeats = $DB->get_records('block_mrbs_rlp_repeat');
            foreach ($repeats as $old) {
                if (!$DB->record_exists('block_mrbs_nds_repeat', ['id' => $old->id])) {
                    $new = new stdClass();
                    $new->id           = $old->id;
                    $new->start_time   = $old->start_time;
                    $new->end_time     = $old->end_time;
                    $new->rep_type     = $old->rep_type;
                    $new->end_date     = $old->end_date;
                    $new->rep_opt      = $old->rep_opt ?? '0';
                    $new->room_id      = $old->room_id;
                    $new->timestamp    = $old->timestamp;
                    $new->create_by    = block_mrbs_nds_resolve_create_by((string) $old->create_by);
                    $new->name         = $old->name;
                    $new->type         = $old->type;
                    $new->description  = $old->description ?? '';
                    $new->rep_num_weeks = $old->rep_num_weeks ?? null;
                    $DB->import_record('block_mrbs_nds_repeat', $new);
                }
            }

            // ── 4. Migrate ENTRIES ───────────────────────────────────────────
            $entries = $DB->get_records('block_mrbs_rlp_entry');
            foreach ($entries as $old) {
                if (!$DB->record_exists('block_mrbs_nds_entry', ['id' => $old->id])) {
                    $new = new stdClass();
                    $new->id          = $old->id;
                    $new->start_time  = $old->start_time;
                    $new->end_time    = $old->end_time;
                    $new->entry_type  = $old->entry_type;
                    $new->repeat_id   = $old->repeat_id;
                    $new->room_id     = $old->room_id;
                    $new->timestamp   = $old->timestamp;
                    $new->create_by   = block_mrbs_nds_resolve_create_by((string) $old->create_by);
                    $new->name        = $old->name;
                    $new->type        = $old->type;
                    $new->description = $old->description ?? '';
                    $new->roomchange  = $old->roomchange ?? 0;
                    $DB->import_record('block_mrbs_nds_entry', $new);
                }
            }

            // ── 5. Rename old tables (preserve as fallback, do NOT drop) ─────
            // Only rename if the _migrated version doesn't already exist.
            foreach ($old_tables as $t) {
                $old_table      = new xmldb_table('block_mrbs_rlp_' . $t);
                $migrated_table = new xmldb_table('block_mrbs_rlp_' . $t . '_migrated');
                if ($dbman->table_exists($old_table) && !$dbman->table_exists($migrated_table)) {
                    $dbman->rename_table($old_table, 'block_mrbs_rlp_' . $t . '_migrated');
                }
            }

            // ── 6. Migrate plugin settings from old component name ────────────
            $old_config = get_config('block_mrbs_rlp');
            if ($old_config) {
                foreach ((array) $old_config as $key => $value) {
                    // Skip Moodle-internal keys.
                    if (in_array($key, ['version'])) {
                        continue;
                    }
                    // Only write if not yet set in new component.
                    if (get_config('block_mrbs_nds', $key) === false) {
                        set_config($key, $value, 'block_mrbs_nds');
                    }
                }
            }
        }

        upgrade_block_savepoint(true, 2026062701, 'mrbs_nds');
    }

    return true;
}
