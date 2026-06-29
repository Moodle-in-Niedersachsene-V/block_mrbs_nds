<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Add a new area or room.
 *
 * Phase 1+2 changes:
 *  - require_once instead of require/include
 *  - Table names: block_mrbs_rlp_* → block_mrbs_nds_*
 *  - Capability: block/mrbs_rlp → block/mrbs_nds
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
global $CFG, $PAGE, $DB;

require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/mrbs_nds_auth.php');

$type        = required_param('type',        PARAM_ALPHA);
$name        = required_param('name',        PARAM_TEXT);
$description = optional_param('description', '', PARAM_TEXT);
$capacity    = optional_param('capacity',    0,  PARAM_INT);
$area        = optional_param('area',        0,  PARAM_INT);

$thisurl = new moodle_url('/blocks/mrbs_nds/web/add.php', ['type' => $type, 'name' => $name]);
if (!empty($description)) {
    $thisurl->param('description', $description);
}
if ($capacity) {
    $thisurl->param('capacity', $capacity);
}
if ($area) {
    $thisurl->param('area', $area);
}
$PAGE->set_url($thisurl);

require_login();
if (!getAuthorised(2)) {
    showAccessDenied(0, 0, 0, $area);
    exit();
}
require_sesskey();

if ($type === 'area') {
    $newarea            = new stdClass();
    $newarea->area_name = $name;
    $area = (int) $DB->insert_record('block_mrbs_rlp_area', $newarea);
}

if ($type === 'room') {
    $newroom              = new stdClass();
    $newroom->room_name   = $name;
    $newroom->description = $description;
    $newroom->capacity    = $capacity;
    $newroom->area_id     = $area;
    $DB->insert_record('block_mrbs_rlp_room', $newroom);
}

redirect(new moodle_url('/blocks/mrbs_nds/web/admin.php', ['area' => $area]));
