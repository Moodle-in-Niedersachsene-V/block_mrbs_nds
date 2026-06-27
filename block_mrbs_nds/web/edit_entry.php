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
global $PAGE, $DB;
require_once __DIR__ . "/config.inc.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/mrbs_nds_auth.php";

global $twentyfourhour_format, $morningstarts;

$day = optional_param('day', 0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$area = optional_param('area', 0, PARAM_INT);
$edit_type = optional_param('edit_type', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$room = optional_param('room', 0, PARAM_INT);
$hour = optional_param('hour', '', PARAM_INT);
$minute = optional_param('minute', '', PARAM_INT);
$force = optional_param('force', false, PARAM_BOOL);
$period = optional_param('period', 0, PARAM_INT);
$all_day = optional_param('all_day', false, PARAM_BOOL);

//If we dont know the right date then make it up
if (($day == 0) or ($month == 0) or ($year == 0)) {
    $day = date("d");
    $month = date("m");
    $year = date("Y");
}

$thisurl = new moodle_url('/blocks/mrbs_nds/web/edit_entry.php', ['day' => $day, 'month' => $month, 'year' => $year]);

if ($area) {
    $thisurl->param('area', $area);
} else {
    $area = get_default_area();
}
if ($id) {
    $thisurl->param('id', $id);
}
if ($force) {
    $thisurl->param('force', $force);
}
if ($room) {
    $thisurl->param('room', $room);
}
if (!empty($edit_type)) {
    $thisurl->param('edit_type', $edit_type);
}
if (!empty($hour)) {
    $thisurl->param('hour', $hour);
}
if (!empty($minute)) {
    $thisurl->param('minute', $minute);
}

$PAGE->set_url($thisurl);
require_login();

if (!getAuthorised(1)) {
    showAccessDenied($day, $month, $year, $area);
    exit;
}

if (!isset($HTTP_REFERER)) {
    if (isset($_SERVER['HTTP_REFERER'])) {
        $HTTP_REFERER = $_SERVER['HTTP_REFERER'];
    } else {
        $HTTP_REFERER = "";
    }
}

// This page will either add or modify a booking
// We need to know:
//  Name of booker
//  Description of meeting
//  Date (option select box for day, month, year)
//  Time
//  Duration
//  Internal/External
// Firstly we need to know if this is a new booking or modifying an old one
// and if it's a modification we need to get all the old data from the db.
// If we had $id passed in then it's a modification.
if ($id > 0) {
    $entry = $DB->get_record('block_mrbs_nds_entry', ['id' => $id], '*', MUST_EXIST);
    // Note: Removed stripslashes() calls from name and description. Previous
    // versions of MRBS mistakenly had the backslash-escapes in the actual database
    // records because of an extra addslashes going on. Fix your database and
    // leave this code alone, please.
    $name = $entry->name;
    $create_by = $entry->create_by;
    $description = $entry->description;
    $start_time = $entry->start_time;
    $start_day = date('d', $entry->start_time);
    $start_month = date('m', $entry->start_time);
    $start_year = date('Y', $entry->start_time);
    $start_hour = date('H', $entry->start_time);
    $start_min = date('i', $entry->start_time);
    $end_time = $entry->end_time;
    $duration = $entry->end_time - $entry->start_time - cross_dst($entry->start_time, $entry->end_time);
    $type = $entry->type;
    $room_id = $entry->room_id;
    $last_change = userdate($entry->timestamp, get_string('strftimedatetimeshort', 'langconfig'));
    $userinfos = getUserinfos($entry->create_by);
	$fullname = getFullname();
    //put this here so that a move can be coded into the get data
    if (!empty($room)) {
        $room_id = $room;
    }
    $entry_type = $entry->entry_type;
    $rep_id = $entry->repeat_id;

    if ($entry_type >= 1) {
        $repeat = $DB->get_record('block_mrbs_nds_repeat', ['id' => $rep_id], '*', MUST_EXIST);
        $rep_type = $repeat->rep_type;

        if ($edit_type == "series") {
            $start_day = (int) date('d', $repeat->start_time);
            $start_month = (int) date('m', $repeat->start_time);
            $start_year = (int) date('Y', $repeat->start_time);

            $rep_end_day = (int) date('d', $repeat->end_date);
            $rep_end_month = (int) date('m', $repeat->end_date);
            $rep_end_year = (int) date('Y', $repeat->end_date);

            switch ($rep_type) {
                case 2:
                case 6:
                    $rep_day[0] = $repeat->rep_opt[0] != "0";
                    $rep_day[1] = $repeat->rep_opt[1] != "0";
                    $rep_day[2] = $repeat->rep_opt[2] != "0";
                    $rep_day[3] = $repeat->rep_opt[3] != "0";
                    $rep_day[4] = $repeat->rep_opt[4] != "0";
                    $rep_day[5] = $repeat->rep_opt[5] != "0";
                    $rep_day[6] = $repeat->rep_opt[6] != "0";

                    if ($rep_type == 6) {
                        $rep_num_weeks = $repeat->rep_num_weeks;
                    }

                    break;

                default:
                    $rep_day = [0, 0, 0, 0, 0, 0, 0];
            }
        } else {
            $rep_type = $repeat->rep_type;
            $rep_end_date = userdate($repeat->end_date, get_string('strftimedaydate', 'langconfig'));
            $rep_opt = $repeat->rep_opt;
        }
    }
} else { // It is a new booking. The data comes from whichever button the user clicked
    $edit_type = "series";
    $name = getUserName();
    $create_by = getUserID();	
    $fullname    = getFullname();
    $description = '';
    $start_day = $day;
    $start_month = $month;
    $start_year = $year;
    // Avoid notices for $hour and $minute if periods is enabled
    $start_hour = $hour;
    $start_min = $minute;
    $duration = ($enable_periods ? 60 : 60 * 60);
    $type = "I";
    $room_id = $room;
    $start_time = mktime(12, $period, 00, $start_month, $start_day, $start_year);
    $end_time = $start_time;
    $rep_id = 0;
    $rep_type = 0;
    $rep_end_day = $day;
    $rep_end_month = $month;
    $rep_end_year = $year;
    $rep_day = [0, 0, 0, 0, 0, 0, 0];
}

// These next 4 if statements handle the situation where
// this page has been accessed directly and no arguments have
// been passed to it.
// If we have not been provided with a room_id

if ($room_id == 0) {
    $dbroom = $DB->get_records('block_mrbs_nds_room', null, 'room_name', 'id', 0, 1);
    if ($dbroom) {
        $dbroom = reset($dbroom);
        $room_id = $dbroom->id;
    }
}

// If we have not been provided with starting time
if (empty($start_hour) && $morningstarts < 10) {
    $start_hour = "0$morningstarts";
}

if (empty($start_hour)) {
    $start_hour = "$morningstarts";
}

if (empty($start_min)) {
    $start_min = "00";
}

// Remove "Undefined variable" notice
if (empty($rep_num_weeks)) {
    $rep_num_weeks = "";
}

// Initialise $dur_units before passing as reference (PHP 8 requires typed vars).
$dur_units = '';
$enable_periods ? toPeriodString($start_min, $duration, $dur_units) : toTimeString($duration, $dur_units);

//now that we know all the data to fill the form with we start drawing it

$context = context_system::instance();

$roomadmin = false;
if (!getWritable($create_by, getUserID())) {
    if (has_capability('block/mrbs_nds:editmrbs_unconfirmed', $context, null, false)) {
        if ($room_id) {
            $dbroom = $DB->get_record('block_mrbs_nds_room', ['id' => $room_id]);
            if ($dbroom->room_admin_email == $USER->email) {
                $roomadmin = true;
            }
        }
    }

    if (!$roomadmin) {
        showAccessDenied($day, $month, $year, $area);
        exit;
    }
}

$PAGE->requires->js('/blocks/mrbs_nds/web/updatefreerooms.js', true);

print_header_mrbs_nds($day, $month, $year, $area);
?>
<script>
window.onload = updateFreeRooms();

<?php
echo 'var currentroom=' . $room_id . ';';
if (has_capability("block/mrbs_nds:forcebook", $context)) {
    echo 'var canforcebook=true;';
} else {
    echo 'var canforcebook=false;';
}
?>
// do a little form verifying
    function validate_and_submit()
    {
        // null strings and spaces only strings not allowed
        if (/(^$)|(^\s+$)/.test(document.forms["main"].name.value)) {
            alert("<?php echo get_string('you_have_not_entered', 'block_mrbs_nds') . '\n' . get_string('name') ?>");
            return false;
        }
        // null strings and spaces only strings not allowed
        if (/(^$)|(^\s+$)/.test(document.forms["main"].description.value)) {
            alert("<?php echo get_string('you_have_not_entered', 'block_mrbs_nds') . '\n' . get_string('description') ?>");
            return false;
        }
<?php if (!$enable_periods) {
    ?>

            h = parseInt(document.forms["main"].hour.value);
            m = parseInt(document.forms["main"].minute.value);

            if (h > 23 || m > 59) {
                alert("<?php echo get_string('you_have_not_entered', 'block_mrbs_nds') . '\n' . get_string('valid_time_of_day', 'block_mrbs_nds') ?>");
                return false;
            }
<?php
} ?>

        // check form element exist before trying to access it
        if (document.forms["main"].id)
            i1 = parseInt(document.forms["main"].id.value);
        else
            i1 = 0;

        i2 = parseInt(document.forms["main"].rep_id.value);
        if (document.forms["main"].rep_num_weeks) {
            n = parseInt(document.forms["main"].rep_num_weeks.value);
        }
        if ((!i1 || (i1 && i2)) && document.forms["main"].rep_type && document.forms["main"].rep_type[6].checked && (!n || n < 2)) {
            alert("<?php echo get_string('you_have_not_entered', 'block_mrbs_nds') . '\n' . get_string('useful_n-weekly_value', 'block_mrbs_nds') ?>");
            return false;
        }

        // check that a room(s) has been selected
        // this is needed as edit_entry_handler does not check that a room(s)
        // has been chosen
        if (document.forms["main"].elements['rooms[]'].selectedIndex == -1) {
            alert("<?php echo get_string('you_have_not_selected', 'block_mrbs_nds') . '\n' . get_string('valid_room', 'block_mrbs_nds') ?>");
            return false;
        }

        // Form submit can take some times, especially if mails are enabled and
        // there are more than one recipient. To avoid users doing weird things
        // like clicking more than one time on submit button, we hide it as soon
        // it is clicked.
        document.forms["main"].save_button.disabled = "true";

        // would be nice to also check date to not allow Feb 31, etc...
        document.forms["main"].submit();

        return true;
    }

    function OnAllDayClick() { // Executed when the user clicks on the all_day checkbox.
        allday = document.getElementById('all_day');
        form = document.forms["main"];
        if (allday.checked) { // If checking the box...
<?php if (!$enable_periods) {
        ?>
                form.hour.value = "00";
                form.minute.value = "00";
<?php
    } ?>
            if (form.dur_units.value != "days") { // Don't change it if the user already did.
                form.duration.value = "1";
                form.dur_units.value = "days";
            }
        }
        updateFreeRooms()
    }
</script>

<div id="page-content">
  <div id="region-main-box">
    <section id="region-main">
    <div role="main">
    
      <h2><?php echo $id ? ($edit_type == "series" ? get_string('editseries', 'block_mrbs_nds') : get_string('editentry', 'block_mrbs_nds')) : get_string('addentry', 'block_mrbs_nds'); ?></h2>

<form name="main" action="edit_entry_handler.php" method="get">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <div id="fcontainer clearfix">

        <?php if ($edit_type != 'series' && $rep_id) {
        ?>
            <b><?php
                        $editseriesurl = new moodle_url('/blocks/mrbs_nds/web/edit_entry.php', ['id' => $id, 'edit_type' => 'series']);
        echo get_string('editingserieswarning', 'block_mrbs_nds');
        echo html_writer::link($editseriesurl, get_string('editseries', 'block_mrbs_nds')); ?>
                    </b>
        <?php
    } ?>

      <div id="fitem_id_name" class="form-group row fitem">
          <div class="col-md-3">
              <label class="col-form-label d-inline" for="id_name"><?php echo get_string('namebooker', 'block_mrbs_nds') ?></label>
          </div>
          <div class="col-md-9 form-inline felement" data-fieldtype="text">
              <input type="text" class="form-control" name="name" id="id_name" value="<?php echo htmlspecialchars($fullname, ENT_NOQUOTES) ?>" size="20" autocomplete="name">
              <div class="form-control-feedback invalid-feedback" id="id_error_name"></div>
          </div>
      </div>
         
      <div id="fitem_id_description" class="form-group row fitem">         
          <div class="col-md-3">
              <label class="col-form-label d-inline" for="id_description"><?php echo get_string('fulldescription', 'block_mrbs_nds') ?></label>
          </div>
          <div class="col-md-9 form-inline felement" data-fieldtype="text">
              <textarea class="form-control" name="description" id="id_description" rows="8" cols="40"><?php
                    echo htmlspecialchars($description);
                    ?></textarea>
              <div class="form-control-feedback invalid-feedback" id="id_error_description"></div>
          </div>          
      </div>    
      
      <div id="fitem_id_date" class="form-group row fitem">         
          <div class="col-md-3">
              <label class="col-form-label d-inline" for="id_date"><?php echo get_string('date') ?></label>
          </div>
          <div class="col-md-9 form-inline felement" data-fieldtype="date_time_selector">
            <?php genDateSelector("", $start_day, $start_month, $start_year, true) ?>
                <script>ChangeOptionDays(document.main, '');</script>
          </div>
      </div>  

        <?php if (!$enable_periods) {
                        ?>
      <div id="fitem_id_time" class="form-group row fitem">         
          <div class="col-md-3">
              <label class="col-form-label d-inline" for="id_time"><?php echo get_string('time') ?></label>
          </div>
          <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input name="hour" class="form-control" size=2 value="<?php
              if (!$twentyfourhour_format && ($start_hour > 12)) {
                  echo($start_hour - 12);
              } else {
                  echo $start_hour;
              } ?>" maxlength=2 onchange="updateFreeRooms()">:<input class="form-control" name="minute" size=2 value="<?php echo $start_min; ?>" maxlength=2 onchange="updateFreeRooms()">
              <?php
              if (!$twentyfourhour_format) {
                  $checked = ($start_hour < 12) ? "checked" : "";
                  echo "<input name=\"ampm\" type=\"radio\" value=\"am\" $checked>" . strtolower(date('A', mktime(1,0,0,1,1,2000)));
                  $checked = ($start_hour >= 12) ? "checked" : "";
                  echo "<input name=\"ampm\" type=\"radio\" value=\"pm\" $checked>" . strtolower(date('A', mktime(13,0,0,1,1,2000)));
              } ?>
          </div>
      </div>         
                         

        <?php
                    } else {
                        ?>
                        
        <div id="fitem_id_period" class="form-group row fitem">         
          <div class="col-md-3">
              <label class="col-form-label d-inline" for="id_period"><?php echo get_string('period', 'block_mrbs_nds') ?></label>
          </div>
          <div class="col-md-9 form-inline felement" data-fieldtype="text">
              <select class="custom-select" name="period" id="id_period" onchange="updateFreeRooms()">
                  <?php
                  foreach ($periods as $p_num => $p_val) {
                      echo "<option value=$p_num";
                      if ((isset($period) && $period == $p_num) || $p_num == $start_min) {
                          echo " selected";
                      }
                      echo ">$p_val";
                  } ?>
              </select>
          </div>
       </div>
       
      <div id="fitem_id_duration" class="form-group row fitem">         
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_duration"><?php echo get_string('duration', 'block_mrbs_nds'); ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
          <input class="form-control" style="line-height:24.4px;" name="duration" id="id_duration" size="7" value="<?php echo $duration; ?>" onchange="updateFreeRooms()">
                <select class="custom-select" name="dur_units" onchange="updateFreeRooms()">
                    <?php
                    if ($enable_periods) {
                        $units = ["periods", "days"];
                    } else {
                        $units = ["minutes", "hours", "days", "weeks"];
                    }

/*                         while (list(, $unit) = each($units)) {
                            echo "<option value=$unit";
                            if ($dur_units == get_string($unit, 'block_mrbs_nds')) {
                                echo " selected";
                            }
                            echo " onchange=\"updateFreeRooms()\">" . get_string($unit, 'block_mrbs_nds');
                        }  */
						foreach($units as $unit) {
                            echo "<option value=$unit";
                            if ($dur_units == get_string($unit, 'block_mrbs_nds')) {
                                echo " selected";
                            }
                            echo " onchange=\"updateFreeRooms()\">" . get_string($unit, 'block_mrbs_nds');
                        } 
						
						?>
                </select>
                <label>&nbsp;<input class="form-check-input" name="all_day" type="checkbox" value="yes" id="all_day" <?php if ($all_day) {
                            echo 'checked ';
                        } ?>onclick="OnAllDayClick()"> <?php
                echo get_string('all_day', 'block_mrbs_nds');
                        if ($all_day) {
                            echo '<body onload = "OnAllDayClick()"></body>';
                        } ?>      
                </label>
                  
        </div>
      </div>      
                       
        <?php
                    }
        
        // Determine the area id of the room in question first
        // room_id may be 0 for a new entry – fall back to the default area.
        $area_id = $room_id
            ? $DB->get_field('block_mrbs_nds_room', 'area_id', ['id' => $room_id], IGNORE_MISSING)
            : null;
        if (!$area_id) {
            $area_id = get_default_area();
        }
        // determine if there is more than one area
        $areas = $DB->get_records('block_mrbs_nds_area', null, 'area_name');
        // if there is more than one area then give the option
        // to choose areas.

        if (count($areas) > 1) {
            ?>
            <div id='fitem_id_areas' class='form-group row fitem'>
              <div class='col-md-3'>
                <label class='col-form-label d-inline' for='id_areas'><?php echo get_string('areas', 'block_mrbs_nds'); ?></label>
              </div>
              <div class='col-md-9 form-inline felement' data-fieldtype="text">
                <select class="custom-select" name="areas" onchange="updateFreeRooms()">
                    <?php
                    // get list of areas
                    foreach ($areas as $dbarea) {
                        $selected = "";
                        if ($dbarea->id == $area_id) {
                            $selected = "selected";
                        }
                        print "<option $selected value='". $dbarea->id . "'>" . $dbarea->area_name . "</option>";
                    } ?>
                </select>
              </div>
          </div>
            
            <?php
        } // if $num_areas
        ?>    
        
        
        <div id='fitem_id_rooms' class='form-group row fitem'>
        <div class='col-md-3'>
          <label class='col-form-label d-inline' for='id_rooms'><?php echo get_string('rooms', 'block_mrbs_nds'); ?></label>
        </div>
        <div class='col-md-9 form-inline felement' data-fieldtype="text">
          <select class="custom-select" name="rooms[]" multiple="yes">
            <?php
            // select the rooms in the area determined above
            //$sql = "select id, room_name from $tbl_room where area_id=$area_id order by room_name";
            $rooms = $DB->get_records('block_mrbs_nds_room', ['area_id' => $area_id], 'room_name');
            
            $i = 0;
            foreach ($rooms as $dbroom) {
                if (!allowed_to_book($USER, $dbroom)) {
                    continue;
                }
                $selected = "";
                if ($dbroom->id == $room_id) {
                    $selected = "selected";
                }
                echo "<option $selected value=\"" . $dbroom->id . "\">" . s($dbroom->room_name) . " (" . s($dbroom->description) . " Capacity:$dbroom->capacity)";
                // store room names for emails
                $room_names[$i] = $dbroom->room_name;
                $i++;
            }
            ?> 
          </select>
          <label><?php echo get_string('ctrl_click', 'block_mrbs_nds') ?></label>
        </div>
      </div>    
      
      <div id='fitem_id_rooms' class='form-group row fitem'>
        <div class='col-md-3'>
          <label class='col-form-label d-inline' for='id_rooms'><?php echo get_string('dontshowoccupied', 'block_mrbs_nds') ?></label>
        </div>   
        
        <div class='col-md-9 form-inline felement' data-fieldtype="text">
            <input name="nooccupied" id="nooccupied" type="checkbox" checked="checked" onclick="updateFreeRooms()" />
        </div>
      </div>  
      
      <div id='fitem_id_type' class='form-group row fitem'>
        <div class='col-md-3'>
          <label class='col-form-label d-inline' for='id_type'><?php echo get_string('type', 'block_mrbs_nds') ?></label>
        </div>   
        
        <div class='col-md-9 form-inline felement' data-fieldtype="text">
            <select class='custom-select' id="id_type" name="type">
                    <?php
//if this is an imported booking, forcably mark it as edited so that changes are not overridden on next import
                    if (($type == 'K') or ($type == 'L')) {
                        echo '<option value="L" selected >' . $typel['L'] . '</option>\n';
                    } else {
                        $unconfirmed = false;
                        $unconfirmedonly = false;
                        if (has_capability('block/mrbs_nds:editmrbs_unconfirmed', $context, null, false)) {
                            $unconfirmed = true;
                        }
                        if (authGetUserLevel(getUserName()) < 2 && $unconfirmed) {
                            if ($USER->email != $rooms[$room_id]->room_admin_email) {
                                $type = 'U';
                                $unconfirmedonly = true;
                            }
                        }
                        if (!$unconfirmedonly) {
                            for ($c = "A"; $c <= "J"; $c++) {
                                if (!empty($typel[$c])) {
                                    echo "<option value=$c" . ($type == $c ? " selected" : "") . ">$typel[$c]\n";
                                }
                            }
                        }
                        if ($unconfirmed) {
                            echo '<option value="U" ' . ($type == 'U' ? 'selected="selected"' : '') . ' >' . $typel['U'] . '</option>\n';
                        }
                    }
                    ?></select>
        </div>
      </div>         
      
                <?php
                if (has_capability("block/mrbs_nds:forcebook", $context)) {
                    ?>

        <div id='fitem_id_forciblybook2' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_forciblybook2'><?php echo get_string('forciblybook2', 'block_mrbs_nds') ?></label>
          </div>   
          
          <div class='col-md-9 form-inline felement' data-fieldtype="text">
             <input id="mrbs_rlpforcebook" class="form-check-input" type="checkbox" name="forcebook" value="true"
             <?php
                if ($force) {
                    echo ' checked="checked"';
                }
                    echo ' onclick="document.getElementById(\'nooccupied\').checked=!this.checked; updateFreeRooms();">'; ?>
          </div>        
        </div>
        
        <?php
                }
             
         if ($edit_type == "series") {
             ?>
         
        <div id='fitem_id_radiorepeat' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_radiorepeat'><?php echo get_string('rep_type', 'block_mrbs_nds') ?></label>
          </div>   
          
          <div class='col-md-9 form-inline felement' data-fieldtype="text">    
          <?php
          for ($i = 0; $i < 7; $i++) {
              echo '<div class="form-check form-check-inline">';
              echo "<input class='form-check-input' id=\"radiorepeat" . $i . "\" name=\"rep_type\" type=\"radio\" value=\"" . $i . "\"";

              if ($i == $rep_type) {
                  echo " checked";
              }

              echo '><label class="form-check-label" for="radiorepeat' . $i . '">' . get_string('rep_type_' . $i, 'block_mrbs_nds') . "</label>";
              echo '</div>';
          } ?>                  
          </div>
        </div>   
        
        
        <div id='fitem_id_rep_end_date' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_rep_end_date'><?php echo get_string('rep_end_date', 'block_mrbs_nds') ?></label>
          </div>   
          
          <div class='col-md-9 form-inline felement' data-fieldtype="text">   
                  <?php genDateSelector("rep_end_", $rep_end_day, $rep_end_month, $rep_end_year) ?>
          </div>        
        </div>    
        
        <div id='fitem_id_rep_rep_day' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_rep_rep_day'><?php echo get_string('rep_rep_day', 'block_mrbs_nds') ?>
            <em><?php echo get_string('rep_for_weekly', 'block_mrbs_nds') ?></em></label>
          </div>   
          
          <div class='col-md-9 form-inline felement' data-fieldtype="text">   
               
                    <?php
// Display day name checkboxes according to language and preferred weekday start.
                    for ($i = 0; $i < 7; $i++) {
                        echo '<div class="form-check form-check-inline">';
                        $wday = ($i + $weekstarts) % 7;
                        echo "<input class='form-check-input' id=\"chkrepeatday" . $i . "\" name=\"rep_day[$wday]\" type=checkbox";
                        if ($rep_day[$wday]) {
                            echo " checked";
                        }
                        echo '><label for="chkrepeatday' . $i . '">' . day_name($wday) . "</label>\n";
                        echo '</div>';
                    } ?>
          </div>        
        </div> 

            <?php
         } else {
             $key = "rep_type_" . (isset($rep_type) ? $rep_type : "0"); ?>  
        
        <div id='fitem_id_rep_type' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_rep_type'><?php echo get_string('rep_type', 'block_mrbs_nds') ?>
            <em><?php echo get_string('rep_for_weekly', 'block_mrbs_nds') ?></em></label>
          </div>           
                 
          <div class='col-md-9 form-inline felement' data-fieldtype="text">  
             <?php echo get_string($key, 'block_mrbs_nds') ?>
          </div>
        </div>       
         
            <?php
            if (isset($rep_type) && ($rep_type != 0)) {
                $opt = "";
                if ($rep_type == 2) {
                    // Display day names according to language and preferred weekday start.
                    for ($i = 0; $i < 7; $i++) {
                        $wday = ($i + $weekstarts) % 7;
                        if ($rep_opt[$wday]) {
                            $opt .= day_name($wday) . " ";
                        }
                    }
                }
                if ($opt) {
                    ?>
        <div id='fitem_id_rep_rep_day' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_rep_rep_day'><?php echo get_string('rep_rep_day', 'block_mrbs_nds') ?></label>
          </div>           
                 
          <div class='col-md-9 form-inline felement' data-fieldtype="text">  
             <?php echo $opt ?>
          </div>
        </div>                      

                    <?php
                } ?>
                 <div id='fitem_id_rep_end_date' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_rep_end_date'><?php echo get_string('rep_end_date', 'block_mrbs_nds') ?></label>
          </div>           
                 
          <div class='col-md-9 form-inline felement' data-fieldtype="text">  
             <?php echo $rep_end_date ?>
          </div>
        </div>         
                <?php
            }
         }
        /* we display the rep_num_weeks box only if:
          - this is a new entry ($id is not set)
          xor
          - we are editing an existing repeating entry ($rep_type is set and
          $rep_type != 0 and $edit_type == "series" )
         */
        if ((($id == 0)) xor (isset($rep_type) && ($rep_type != 0) && ("series" == $edit_type))) {
            ?>
         <div id='fitem_id_rep_num_weeks' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_rep_num_weeks'><?php echo get_string('rep_num_weeks', 'block_mrbs_nds') ?>&nbsp;<em><?php echo get_string('rep_for_nweekly', 'block_mrbs_nds') ?></em></label>
          </div>           
                 
          <div class='col-md-9 form-inline felement' data-fieldtype="text">  
             <input type="text" class="form-control" name="rep_num_weeks" value="<?php echo $rep_num_weeks ?>">
          </div>
        </div>          
        <?php
        } ?>

        <?php if ($id != 0) {
            ?>

         <div id='fitem_id_rep_num_weeks' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_rep_num_weeks'><?php echo get_string('roomchange', 'block_mrbs_nds') ?></label>
          </div>           
                 
          <div class='col-md-9 form-inline felement' data-fieldtype="text">  
             <input type="checkbox" class='form-check-input' checked="checked" name="roomchange" id="mrbs_rlproomchange" />
          </div>
        </div>  
        
         <div id='fitem_id_createdby' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='idcreatedby'><?php echo get_string('createdby', 'block_mrbs_nds') ?></label>
          </div>           
                 
          <div class='col-md-9 form-inline felement' data-fieldtype="text">  
             <?php echo $fullname ?>
          </div>
        </div>           
   
         <div id='fitem_id_last_change' class='form-group row fitem'>
          <div class='col-md-3'>
            <label class='col-form-label d-inline' for='id_last_change'><?php echo get_string('last_change', 'block_mrbs_nds') ?></label>
          </div>           
                 
          <div class='col-md-9 form-inline felement' data-fieldtype="text">  
             <?php echo $last_change ?>
          </div>
        </div>                      
                                              
        <?php
        } ?>
        
         <div id='fitem_id_last_change' class='form-group row fitem'>
          <div class='col-md-3'>    
          </div>           
                 
          <div class='col-md-9 felement' data-fieldtype="button">  
             <button type="button" class="btn btn-primary" name="save_button" onclick="validate_and_submit()"><?php echo get_string('savechanges') ?></button>
             
            <?php
                if ($id) { //always be able to delete entry and if part of a series then add option to delete entire series.
                    $delurl = new moodle_url('/blocks/mrbs_nds/web/del_entry.php', ['id' => $id, 'series' => 0, 'sesskey' => sesskey()]); ?>           
                    <button type="button" class="btn btn-danger" onclick="if(confirm('<?= get_string('confirmdel', 'block_mrbs_nds') ?>')){document.location='<?= $delurl ?>';}" /><?= get_string('deleteentry', 'block_mrbs_nds') ?></button>
                    <?php
                    if ($rep_id) {
                        $delurl = new moodle_url('/blocks/mrbs_nds/web/del_entry.php', ['id' => $id, 'series' => 1, 'sesskey' => sesskey(),
                            'day' => $day, 'month' => $month, 'year' => $year]); ?>    
                        <button type="button" class="btn btn-warning" onclick="if(confirm('<?= get_string('confirmdel', 'block_mrbs_nds') ?>')){document.location='<?= $delurl ?>';}" /><?= get_string('deleteseries', 'block_mrbs_nds') ?></button>    
                    <?php
                    }
                }
                ?>             
          </div>
        </div>       

</div>

    <input type=hidden name="returl" value="<?php echo s($HTTP_REFERER) ?>">
    <input type=hidden name="room_id" value="<?php echo $room_id ?>">
    <input type=hidden name="create_by" value="<?php echo $create_by ?>">
    <input type=hidden name="rep_id"    value="<?php echo $rep_id ?>">
    <input type=hidden name="edit_type" value="<?php echo $edit_type ?>">
    <?php
    if (isset($id)) {
        echo "<input type='hidden' name='id' value='" . $id . "'>\n";
    }
    ?>

</form>

    </section>
    </div>
  </div>
    
    

<?php include "trailer.php" ?>
