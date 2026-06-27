<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Configuration loader for block_mrbs_nds web pages.
 *
 * Phase 1 fix: get_config('block_mrbs_nds') → get_config('block_mrbs_nds').
 * All settings are read from the Moodle configuration store.
 * No local settings file needed.
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// Phase 1: underscore-separated component name.
$cfg_mrbs_nds = get_config('block_mrbs_nds');

// ── Site identification ───────────────────────────────────────────────────────
$mrbs_nds_admin       = $cfg_mrbs_nds->admin       ?? '';
$mrbs_nds_admin_email = $cfg_mrbs_nds->admin_email ?? '';
$mrbs_nds_company     = $SITE->fullname;
$mrbs_nds_company_url = $CFG->wwwroot;
$url_base             = $cfg_mrbs_nds->serverpath   ?? ($CFG->wwwroot . '/blocks/mrbs_nds/web');

// ── Calendar settings ─────────────────────────────────────────────────────────
$enable_periods = (bool) ($cfg_mrbs_nds->enable_periods ?? false);

if (!$enable_periods) {
    $resolution           = (int) ($cfg_mrbs_nds->resolution          ?? 1800);
    $morningstarts        = (int) ($cfg_mrbs_nds->morningstarts        ?? 8);
    $eveningends          = (int) ($cfg_mrbs_nds->eveningends          ?? 18);
    $morningstarts_minutes = (int) ($cfg_mrbs_nds->morningstarts_min  ?? 0);
    $eveningends_minutes  = (int) ($cfg_mrbs_nds->eveningends_min     ?? 0);
}

// ── Periods ───────────────────────────────────────────────────────────────────
$periods = [];
if (empty($cfg_mrbs_nds->periods)) {
    for ($i = 1; $i <= 12; $i++) {
        $periods[] = 'Period&nbsp;' . $i;
    }
} else {
    foreach (explode("\n", $cfg_mrbs_nds->periods) as $pd) {
        $pd = trim($pd);
        if ($pd !== '') {
            $periods[] = $pd;
        }
    }
}

// ── Week / date settings ──────────────────────────────────────────────────────
$weekstarts           = (int)  ($cfg_mrbs_nds->weekstarts           ?? 1);   // 0=Sun,1=Mon
$twentyfourhour_format = (bool) ($cfg_mrbs_nds->twentyfourhour_format ?? true);
$dateformat           = (int)  ($cfg_mrbs_nds->dateformat           ?? 0);
$view_week_number     = (bool) ($cfg_mrbs_nds->view_week_number     ?? false);
$default_view         = $cfg_mrbs_nds->default_view ?? 'day';
$default_room         = (int)  ($cfg_mrbs_nds->default_room         ?? 0);
$max_advance_days     = (int)  ($cfg_mrbs_nds->max_advance_days     ?? -1);

// ── Repeat settings ───────────────────────────────────────────────────────────
$max_rep_entrys       = (int)  ($cfg_mrbs_nds->max_rep_entrys       ?? 365);

// ── Display settings ─────────────────────────────────────────────────────────
$javascript_cursor    = (bool)   ($cfg_mrbs_nds->javascript_cursor              ?? false);
$area_list_format     =           $cfg_mrbs_nds->area_list_format               ?? 'select';
$view_week_number     = (bool)   ($cfg_mrbs_nds->view_week_number               ?? false);
$times_right_side     =           $cfg_mrbs_nds->times_right_side               ?? false;
$show_plus_link       = (bool)   ($cfg_mrbs_nds->show_plus_link                 ?? false);
$highlight_method     =           $cfg_mrbs_nds->highlight_method               ?? 'class';
$monthly_view_entries_details = (bool) ($cfg_mrbs_nds->monthly_view_entries_details ?? false);
$search               = ['count' => (int) ($cfg_mrbs_nds->search_count          ?? 20)];
$default_report_days  = (int)    ($cfg_mrbs_nds->default_report_days            ?? 365);
$search_str           = '';

// ── Mail settings ─────────────────────────────────────────────────────────────
// Define mail constants only if not already defined (avoids redefinition errors
// when config.inc.php is included more than once).
$mail_settings = [
    'MAIL_FROM'                  => $cfg_mrbs_nds->mail_from                   ?? '',
    'MAIL_RECIPIENTS'            => $cfg_mrbs_nds->mail_recipients             ?? '',
    'MAIL_ADMIN_ON_BOOKINGS'     => (bool) ($cfg_mrbs_nds->mail_admin_on_bookings    ?? false),
    'MAIL_AREA_ADMIN_ON_BOOKINGS' => (bool) ($cfg_mrbs_nds->mail_area_admin_on_bookings ?? false),
    'MAIL_ROOM_ADMIN_ON_BOOKINGS' => (bool) ($cfg_mrbs_nds->mail_room_admin_on_bookings ?? false),
    'MAIL_BOOKER'                => (bool) ($cfg_mrbs_nds->mail_booker                ?? false),
    'MAIL_ADMIN_ON_DELETE'       => (bool) ($cfg_mrbs_nds->mail_admin_on_delete       ?? false),
    'MAIL_DETAILS'               => (bool) ($cfg_mrbs_nds->mail_details               ?? true),
];
foreach ($mail_settings as $const => $val) {
    if (!defined($const)) {
        define($const, $val);
    }
}

// ── Booking type labels ───────────────────────────────────────────────────────
// Types A–J from admin settings; fallback defaults used until admin saves settings.
$type_defaults = [
    'a' => 'Hausaufgaben',   'b' => 'Klassenarbeiten', 'c' => 'EDV-Unterricht',
    'd' => 'Fachunterricht', 'e' => 'Extern',          'f' => 'Projektunterricht',
    'g' => 'Differenzierung','h' => 'AG',               'i' => 'Intern',
    'j' => 'Vertretung',
];
$typel = [];
foreach ($type_defaults as $lc => $default) {
    $key = 'entry_type_' . $lc;
    $val = (!empty($cfg_mrbs_nds->$key)) ? $cfg_mrbs_nds->$key : $default;
    $typel[strtoupper($lc)] = $val;
}
if (!empty($cfg_mrbs_nds->enable_periods)) {
    $typel['K'] = get_string('importedbooking',      'block_mrbs_nds');
    $typel['L'] = get_string('importedbookingmoved', 'block_mrbs_nds');
}
$typel['U'] = get_string('unconfirmedbooking', 'block_mrbs_nds');

// Auth config (simplified – dynamic file inclusion removed in Phase 1).
$auth = ['type' => 'nds', 'session' => 'php'];
