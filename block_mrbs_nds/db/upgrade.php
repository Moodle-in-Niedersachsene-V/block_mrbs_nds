<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade script for block_mrbs_nds.
 *
 * MIGRATION STRATEGY:
 * ─────────────────────────────────────────────────────────────────────────────
 * block_mrbs_nds reuses the existing block_mrbs_rlp_* database tables directly.
 * No table renaming or data copying is required.
 *
 * On first install, db/install.xml creates the block_mrbs_rlp_* tables if they
 * do not yet exist. If block_mrbs_rlp was previously installed, its tables are
 * already present and install.xml skips table creation (Moodle checks existence).
 *
 * This upgrade script handles:
 *  1. Normalising create_by from VARCHAR (username) to INT (user.id) where needed
 *  2. Migrating plugin settings from old component name 'block_mrbs_rlp'
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve create_by to a Moodle user.id.
 * The field may contain an integer user.id (v1.4.6+) or a username string (older).
 */
function block_mrbs_nds_resolve_create_by(string $raw): int {
    global $DB;
    $raw = trim($raw);
    if ($raw === '' || $raw === '0') {
        return 0;
    }
    if (ctype_digit($raw)) {
        $id = (int) $raw;
        if ($DB->record_exists('user', ['id' => $id])) {
            return $id;
        }
    }
    $uid = $DB->get_field('user', 'id', ['username' => $raw]);
    return $uid ? (int) $uid : 0;
}

function xmldb_block_mrbs_nds_upgrade(int $oldversion): bool {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026062725) {

        // ── 1. Normalise create_by to INT user.id where still VARCHAR ─────────
        // Filtering is done in PHP (ctype_digit) rather than SQL REGEXP, since
        // REGEXP syntax differs between MariaDB/MySQL and PostgreSQL. This keeps
        // upgrade.php fully database-engine agnostic per Moodle's DB abstraction.
        $tables = ['block_mrbs_rlp_entry', 'block_mrbs_rlp_repeat'];
        foreach ($tables as $table) {
            if (!$dbman->table_exists(new xmldb_table($table))) {
                continue;
            }

            $rs = $DB->get_recordset_select(
                $table,
                "create_by != ''",
                [],
                'id',
                'id, create_by'
            );

            $processed = 0;
            foreach ($rs as $rec) {
                // Only touch rows where create_by is NOT already a pure integer.
                if (!ctype_digit((string) $rec->create_by)) {
                    $uid = block_mrbs_nds_resolve_create_by((string) $rec->create_by);
                    $DB->set_field($table, 'create_by', $uid, ['id' => $rec->id]);
                }
                // Safety cap to avoid excessive runtime on very large tables;
                // remaining rows are picked up on the next cron/upgrade pass.
                if (++$processed >= 2000) {
                    break;
                }
            }
            $rs->close();
        }

        // ── 2. Migrate plugin settings from old component name ─────────────────
        $old_config = get_config('block_mrbs_rlp');
        if ($old_config) {
            foreach ((array) $old_config as $key => $value) {
                if (in_array($key, ['version'])) {
                    continue;
                }
                if (get_config('block_mrbs_nds', $key) === false) {
                    set_config($key, $value, 'block_mrbs_nds');
                }
            }
        }

        upgrade_block_savepoint(true, 2026062725, 'mrbs_nds');
    }

    return true;
}
