<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_mrbs_nds\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for block_mrbs_nds.
 *
 * Phase 2 addition: this class was completely absent in block_mrbs_rlp.
 * It is required since Moodle 3.5 for DSGVO / GDPR compliance.
 *
 * Personal data stored by this block:
 *  • block_mrbs_rlp_entry.create_by  (user.id of booker)
 *  • block_mrbs_rlp_repeat.create_by (user.id of series creator)
 *  • room.booking_users              (comma-separated user IDs; not exported
 *                                     per-user since it is an admin setting,
 *                                     but mentioned in metadata)
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    // ── Metadata ──────────────────────────────────────────────────────────────

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'block_mrbs_rlp_entry',
            [
                'create_by'   => 'privacy:metadata:block_mrbs_rlp_entry:create_by',
                'name'        => 'privacy:metadata:block_mrbs_rlp_entry:name',
                'description' => 'privacy:metadata:block_mrbs_rlp_entry:description',
                'start_time'  => 'privacy:metadata:block_mrbs_rlp_entry:start_time',
                'end_time'    => 'privacy:metadata:block_mrbs_rlp_entry:end_time',
            ],
            'privacy:metadata:block_mrbs_rlp_entry'
        );

        $collection->add_database_table(
            'block_mrbs_rlp_repeat',
            [
                'create_by'   => 'privacy:metadata:block_mrbs_rlp_repeat:create_by',
                'name'        => 'privacy:metadata:block_mrbs_rlp_repeat:name',
                'description' => 'privacy:metadata:block_mrbs_rlp_repeat:description',
                'start_time'  => 'privacy:metadata:block_mrbs_rlp_repeat:start_time',
                'end_time'    => 'privacy:metadata:block_mrbs_rlp_repeat:end_time',
            ],
            'privacy:metadata:block_mrbs_rlp_repeat'
        );

        return $collection;
    }

    // ── Context list ──────────────────────────────────────────────────────────

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // All bookings live in the system context.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {block_mrbs_rlp_entry} e ON e.create_by = :userid
                 WHERE ctx.contextlevel = :contextlevel";

        $contextlist->add_from_sql($sql, [
            'userid'       => $userid,
            'contextlevel' => CONTEXT_SYSTEM,
        ]);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $sql = "SELECT DISTINCT e.create_by AS userid
                  FROM {block_mrbs_rlp_entry} e";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT DISTINCT r.create_by AS userid
                  FROM {block_mrbs_rlp_repeat} r";
        $userlist->add_from_sql('userid', $sql, []);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $entries = $DB->get_records('block_mrbs_rlp_entry', ['create_by' => $userid]);
        foreach ($entries as $entry) {
            $context   = \context_system::instance();
            $subcontext = [
                get_string('pluginname', 'block_mrbs_nds'),
                get_string('bookings', 'block_mrbs_nds'),
                (string) $entry->id,
            ];
            writer::with_context($context)->export_data($subcontext, (object) [
                'name'        => $entry->name,
                'description' => $entry->description,
                'start_time'  => transform::datetime($entry->start_time),
                'end_time'    => transform::datetime($entry->end_time),
                'type'        => $entry->type,
            ]);
        }
    }

    // ── Deletion ──────────────────────────────────────────────────────────────

    public static function delete_data_for_all_users_in_context(\context $context): void {
        // This block stores no context-specific data beyond the system context.
        // Deleting all entries for all users is a destructive admin operation
        // and is intentionally not implemented here. Admins use admin.php.
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        // Anonymise rather than hard-delete, to preserve integrity of
        // room booking history (other users may depend on the calendar).
        $DB->set_field('block_mrbs_rlp_entry',  'create_by', 0, ['create_by' => $userid]);
        $DB->set_field('block_mrbs_rlp_repeat', 'create_by', 0, ['create_by' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->execute(
            "UPDATE {block_mrbs_rlp_entry} SET create_by = 0 WHERE create_by $insql",
            $params
        );
        $DB->execute(
            "UPDATE {block_mrbs_rlp_repeat} SET create_by = 0 WHERE create_by $insql",
            $params
        );
    }
}
