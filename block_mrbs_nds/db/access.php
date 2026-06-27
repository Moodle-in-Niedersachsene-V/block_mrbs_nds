<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Capability definitions for block_mrbs_nds.
 *
 * Phase 1 fix: removed duplicate myaddinstance / addinstance entries that
 * existed in the original block_mrbs_rlp access.php (second definition
 * silently overwrote the first).
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Allow user to add block to their dashboard.
    'block/mrbs_nds:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],

    // Allow editing teacher / manager to add block to a course page.
    'block/mrbs_nds:addinstance' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],

    // View the booking calendar (read-only).
    'block/mrbs_nds:viewmrbs' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Create and edit bookings.
    'block/mrbs_nds:editmrbs' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Administer areas, rooms and all bookings.
    'block/mrbs_nds:administermrbs' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View all timetables (not just own).
    'block/mrbs_nds:viewalltt' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator'  => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Force a booking even if there is a clash.
    'block/mrbs_nds:forcebook' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Allow double-booking (two entries in the same slot).
    'block/mrbs_nds:doublebook' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Restrict user to creating unconfirmed bookings only
    // (unless they are the room administrator for that room).
    'block/mrbs_nds:editmrbs_unconfirmed' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Bypass the max_advance_days restriction.
    'block/mrbs_nds:ignoremaxadvancedays' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'block/mrbs_nds:administermrbs',
    ],
];
