<?php

// This file is part of the MRBS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php'); //for Moodle integration
global $PAGE, $DB, $OUTPUT;
require_once __DIR__ . "/config.inc.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/mrbs_nds_auth.php";

// Passed in when starting to edit
$day = optional_param('day', 0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$room = optional_param('room', 0, PARAM_INT);
$area = optional_param('area', 0, PARAM_INT);

// Editing general
$change_done = optional_param('change_done', 0, PARAM_BOOL);

// Editing room
$room_name = optional_param('room_name', '', PARAM_TEXT);
$description = optional_param('description', '', PARAM_TEXT);
$capacity = optional_param('capacity', 0, PARAM_INT);
$room_admin_email = optional_param('room_admin_email', '', PARAM_TEXT);
$booking_users = optional_param('booking_users', '', PARAM_TEXT);
$change_room = optional_param('change_room', false, PARAM_TEXT);

// Editing area
$area_name = optional_param('area_name', '', PARAM_TEXT);
$area_admin_email = optional_param('area_admin_email', '', PARAM_TEXT);
$change_area = optional_param('change_area', false, PARAM_TEXT);

// $room_admin_email = optional_param('room_admin_email', 0, PARAM_BOOL); //not sure if this is from config or passed from URL -ab.
//If we dont know the right date then make it up
if (($day == 0) or ($month == 0) or ($year == 0)) {
    $day = date("d");
    $month = date("m");
    $year = date("Y");
}

$thisurl = new moodle_url('/blocks/mrbs_nds/web/edit_area_room.php', ['day' => $day, 'month' => $month, 'year' => $year, 'sesskey' => sesskey()]);
if ($room) {
    $thisurl->param('room', $room);
}
if ($area) {
    $thisurl->param('area', $area);
}

$PAGE->set_url($thisurl);
require_login();

if (!getAuthorised(2)) {
    showAccessDenied($day, $month, $year, $area);
    exit();
}
require_sesskey();

// Done changing area or room information?
if (($change_done)) {
    if (!empty($room)) { // Get the area the room is in
        $area = $DB->get_field('block_mrbs_nds_room', 'area_id', ['id' => $room]);
    }
    $adminurl = new moodle_url('/blocks/mrbs_nds/web/admin.php', ['day' => $day, 'month' => $month, 'year' => $year, 'area' => $area]);
    redirect($adminurl);
    exit();
}

print_header_mrbs_nds($day, $month, $year, isset($area) ? $area : "");

echo $OUTPUT->heading(get_string('editroomarea', 'block_mrbs_nds'), 2);

if ($room > 0) {
    $valid_email = true;
    if (!empty($room_admin_email)) {
        $emails = explode(',', $room_admin_email);
        foreach ($emails as $email) {
            // if no email address is entered, this is OK, even if isValidInetAddress
            // does not return TRUE
            if (!get_user_by_email(trim($email))) {
                $valid_email = false;
                echo $OUTPUT->box(get_string('no_user_with_email', 'block_mrbs_nds', $email));
            }
        }
    }
    $valid_email2 = true;
    if (!empty($booking_users)) {
        $booking_emails = explode(',', $booking_users);
        foreach ($booking_emails as $email) {
            if (!get_user_by_email(trim($email))) {
                $valid_email2 = false;
                echo $OUTPUT->box(get_string('no_user_with_email', 'block_mrbs_nds', $email));
            }
        }
    }

    if ($change_room && $valid_email && $valid_email2) {
        $updroom = new stdClass;
        $updroom->id = $room;
        $updroom->room_name = substr(trim($room_name), 0, 25);
        $updroom->description = $description;
        $updroom->capacity = $capacity;
        $updroom->room_admin_email = $room_admin_email;
        $updroom->booking_users = $booking_users;

        $DB->update_record('block_mrbs_nds_room', $updroom);
    }

    $dbroom = $DB->get_record('block_mrbs_nds_room', ['id' => $room], '*', MUST_EXIST); ?>    
    <form action="<?= $thisurl->out_omit_querystring() ?>" method="post">
    <input type="hidden" name="room" value="<?= $dbroom->id ?>">
    <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
    
    <div id="fitem_id_room_name" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_room_name"><?= get_string('name') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input type="text" class="form-control" name="room_name" id="id_room_name" value="<?= s($dbroom->room_name) ?>" maxlength="255" size="20">
        </div>
    </div>    
    
    <div id="fitem_id_room_name" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_room_name"><?= get_string('description') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input type="text" class="form-control" name="description" id="id_description" value="<?= s($dbroom->description) ?>" maxlength="255" size="20">
        </div>
    </div>     
    
    <div id="fitem_id_capacity" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_capacity"><?= get_string('capacity', 'block_mrbs_nds') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input type="text" class="form-control" name="capacity" id="id_capacity" value="<?= $dbroom->capacity ?>" size="20">
        </div>
    </div>    
    
    <div id="fitem_id_room_admin_email" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_room_admin_email"><?= get_string('room_admin_email', 'block_mrbs_nds') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input type="text" class="form-control" name="room_admin_email" id="id_room_admin_email" value="<?= s($dbroom->room_admin_email) ?>" maxlength="255" size="20">
        </div>
    </div>             
    
    <?php
    if (!$valid_email) {
        echo "<STRONG>" . get_string('emailmustbereal') . "<STRONG>";
    } ?>
    
    <div id="fitem_id_booking_users" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_booking_users"><?= get_string('booking_users', 'block_mrbs_nds') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <textarea class="form-control" name="booking_users" id="id_booking_users" cols="25" rows="3"><?= s($dbroom->booking_users) ?></textarea>
        </div>
    </div>     
    
    <?php
    if (!$valid_email2) {
        echo "<STRONG>" . get_string('emailmustbereal') . "<STRONG>";
    } ?>
    
    <div id='fitem_id_last_change' class='form-group row fitem'>
      <div class='col-md-3'></div>         
      <div class='col-md-9 felement' data-fieldtype="button"> 
            <button class="btn btn-success" type="submit" name="change_room" value="1"><?= get_string('savechanges') ?></button>
            <button class="btn btn-info" type="submit" name="change_done" value="1"><?= get_string('backadmin', 'block_mrbs_nds') ?></button>
      </div>
    </div>    

    </form>
    
    <?php
}

if ($area) {
    $emails = explode(',', $area_admin_email);
    $valid_email = true;
    foreach ($emails as $email) {
        // if no email address is entered, this is OK, even if isValidInetAddress
        // does not return TRUE
        $email = trim($email);
        if (!get_user_by_email($email) && ('' != $area_admin_email)) {
            $valid_email = false;
            echo $OUTPUT->box(get_string('no_user_with_email', 'block_mrbs_nds', $email));
        }
    }

    if ($change_area && $valid_email) {
        $updarea = new stdClass;
        $updarea->id = $area;
        $updarea->area_name = $area_name;
        $updarea->area_admin_email = $area_admin_email;
        $DB->update_record('block_mrbs_nds_area', $updarea);
    }

    $dbarea = $DB->get_record('block_mrbs_nds_area', ['id' => $area], '*', MUST_EXIST); ?>
    
    <form action="<?= $thisurl->out_omit_querystring() ?>" method="post">
    <input type="hidden" name="area" value="<?= $dbarea->id ?>">
    <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
    
    <div id="fitem_id_area_name" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_area_name"><?= get_string('name') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input type="text" class="form-control" name="area_name" id="id_area_name" value="<?= s($dbarea->area_name) ?>" maxlength="255" size="20">
        </div>
    </div>    
    
    <div id="fitem_id_area_admin_email" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_area_admin_email"><?= get_string('area_admin_email', 'block_mrbs_nds') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input type="text" class="form-control" name="area_admin_email" id="id_area_admin_email" value="<?= s($dbarea->area_admin_email) ?>" maxlength="255" size="20">
        </div>
    </div>             
    
    <?php
    if (!$valid_email) {
        echo "<STRONG>" . get_string('emailmustbereal') . "<STRONG>";
    } ?>
    
    <div id='fitem_id_last_change' class='form-group row fitem'>
      <div class='col-md-3'></div>         
      <div class='col-md-9 felement' data-fieldtype="button"> 
            <button class="btn btn-success" type="submit" name="change_area" value="<?= get_string('savechanges') ?>"><?= get_string('savechanges') ?></button>
            <button class="btn btn-info" type="submit" name="change_done" value="1"><?= get_string('backadmin', 'block_mrbs_nds') ?></button>
      </div>
    </div>  
    <?php
}

require_once __DIR__ . "/trailer.php";
