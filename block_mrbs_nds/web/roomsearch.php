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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once __DIR__ . "/config.inc.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/mrbs_nds_auth.php";

global $USER;

if ($CFG->forcelogin) {
    require_login();
}

$day = optional_param('day', 0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$area = optional_param('area', 0, PARAM_INT);

//If we dont know the right date then make it up
if (($day == 0) or ($month == 0) or ($year == 0)) {
    $day = date("d");
    $month = date("m");
    $year = date("Y");
}

$thisurl = new moodle_url('/blocks/mrbs_nds/web/roomsearch.php', ['day' => $day, 'month' => $month, 'year' => $year]);
if ($area > 0) {
    $thisurl->param('area', $area);
} else {
    $area = get_default_area();
}

$PAGE->set_url($thisurl);
require_login();
print_header_mrbs_nds($day, $month, $year, $area);

global $twentyfourhour_format;

$day = optional_param('day', 0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$area = optional_param('area', get_default_area(), PARAM_INT);
$edit_type = optional_param('edit_type', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$room_id = optional_param('room_id', 0, PARAM_INT);
$start_hour = optional_param('start_hour', 0, PARAM_INT);
// $rep_type could use a closer look but I believe this is not passed via URL -ab.
$start_min = optional_param('start_min', 0, PARAM_INT);
$rep_num_weeks = optional_param('rep_num_weeks', 0, PARAM_INT);
$force = optional_param('force', false, PARAM_BOOL);
$duration = optional_param('duration', 1, PARAM_INT);
$all_day = optional_param('all_day', false, PARAM_BOOL);
if ($enable_periods) {
    $default_dur_units = 'periods';
} else {
    $default_dur_units = 'hours';
}

$dur_units = optional_param('dur_units', $default_dur_units, PARAM_TEXT);

//If we dont know the right date then make it up
if (($day == 0) or ($month == 0) or ($year == 0)) {
    $day = date("d");
    $month = date("m");
    $year = date("Y");
}
?>

<script type="text/javascript" src="roomsearch.js"></script>
<script LANGUAGE="JavaScript">
    function OnAllDayClick() // Executed when the user clicks on the all_day checkbox.
    {
        allday = document.getElementById('all_day');
        form = document.forms["main"];
        if (allday.checked) // If checking the box...
        {
<?php if (!$enable_periods) {
    ?>
                form.hour.value = "00";
                form.minute.value = "00";
<?php
} ?>
            if (form.dur_units.value != "days") // Don't change it if the user already did.
            {
                form.duration.value = "1";
                form.dur_units.value = "days";
            }
        }
        RoomSearch()
    }
</script>
<?php
// Set the weekday names for the 'ChangeOptionDays' function
echo '<script type="text/javascript">SetWeekDayNames(';
echo '"' . get_string('mon', 'calendar') . '", ';
echo '"' . get_string('tue', 'calendar') . '", ';
echo '"' . get_string('wed', 'calendar') . '", ';
echo '"' . get_string('thu', 'calendar') . '", ';
echo '"' . get_string('fri', 'calendar') . '", ';
echo '"' . get_string('sat', 'calendar') . '", ';
echo '"' . get_string('sun', 'calendar') . '"';
echo ');</script>';
?>

<div id="page-content">
  <div id="region-main-box">
    <section id="region-main">
    <div role="main">

    <h2><?php echo get_string('search'); ?></h2>

    <form action="" method="post" name="main" >
    
    <!-- Date selectors -->
    <div id="fitem_id_name" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_name"><?= get_string('date') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <?php genDateSelector("", $day, $month, $year, false, true) ?>
            <script>ChangeOptionDays(document.main, '');</script>
        </div>
    </div>    

    <!-- Start time/period selectors -->
    <?php if (!$enable_periods) {
    ?>
    
    <div id="fitem_id_name" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_name"><?= get_string('time') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input type="text" class="form-control" name="hour" size="2" value="<?php
            if (!$twentyfourhour_format && ($start_hour > 12)) {
                echo($start_hour - 12);
            } else {
                echo $start_hour;
            } ?>" maxlength=2 onChange="RoomSearch()">:<input type="text" class="form-control" name="minute" size="2" value="<?php echo $start_min; ?>" maxlength="2" onChange="RoomSearch()">
            <?php
            if (!$twentyfourhour_format) {
                $checked = ($start_hour < 12) ? "checked" : "";
                echo '<input name="ampm" type="radio" value="am" ' . $checked . '>am';
                $checked = ($start_hour >= 12) ? "checked" : "";
                echo '<input name="ampm" type="radio" value="pm" ' . $checked . '>pm';
            } ?>
        </div>
    </div>      

<?php
} else {
                ?>

    <div id="fitem_id_name" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_name"><?= get_string('period', 'block_mrbs_nds') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <select class="custom-select" name="period" onChange="RoomSearch()">
                <?php
                foreach ($periods as $p_num => $p_val) {
                    echo "<option value='".$p_num."'";
                    if ((isset($period) && $period == $p_num) || $p_num == $start_min) {
                        echo " SELECTED";
                    }
                    echo ">$p_val";
                } ?>
            </select>
        </div>
    </div>  
    
    <!-- Duration selectors -->
<?php
            } ?>

    <div id="fitem_id_name" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_name"><?= get_string('duration', 'block_mrbs_nds') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
          <input class="form-control" name="duration" size="7" value="<?php echo $duration; ?>" onChange="RoomSearch()" onKeyUp="RoomSearch()">
          <select class="custom-select" style="line-height:20.5px;" name="dur_units" onChange="RoomSearch()">
              <?php
              if ($enable_periods) {
                  $units = ["periods", "days"];
              } else {
                  $units = ["minutes", "hours", "days", "weeks"];
              }

              while (list(, $unit) = each($units)) {
                  echo "<option value='".$unit."'";
                  if ($dur_units == get_string($unit, 'block_mrbs_nds')) {
                      echo " SELECTED";
                  }
                  echo " onChange=\"RoomSearch()\">" . get_string($unit, 'block_mrbs_nds');
              }
              ?>
          </select>
          <label>&nbsp;<input class="form-check-input" name="all_day" type="checkbox" value="yes" id="all_day" <?php if ($all_day) {
                  echo 'CHECKED ';
              } ?>onClick="OnAllDayClick()"> <?php
          echo get_string('all_day', 'block_mrbs_nds')."</label>";
          if ($all_day) {
              echo '<body onload = "OnAllDayClick()"></body>';
          }
          ?>
        </div>
    </div> 
    
    <div id="fitem_id_name" class="form-group row fitem">
        <div class="col-md-3">
            <label class="col-form-label d-inline" for="id_name"><?= get_string('mincapacity', 'block_mrbs_nds') ?></label>
        </div>
        <div class="col-md-9 form-inline felement" data-fieldtype="text">
            <input class="form-control" name="mincap" size="3" onChange="RoomSearch()" onKeyUp="RoomSearch()">
        </div>
    </div>      

    </form>

    </div>
    </section>
  </div>
</div>  

    <!-- Area to display rooms found -->
    <h2 id="results"></h2>
    
    <table class="table table-bordered table-striped">
      <thead class="thead-dark">
        <tr>
          <th><?= get_string('area', 'block_mrbs_nds') ?></th>
          <th><?= get_string('room', 'block_mrbs_nds') ?></th>
          <th><?= get_string('description') ?></th>
          <th><?= get_string('capacity', 'block_mrbs_nds') ?></th>
        </tr>
      </thead>
    <tbody id="rooms">
    </tbody>
    </table>

    <script>
        var langRoomsFree = '<?php print_string('roomsfree', 'block_mrbs_nds'); ?>';
        var langNoRooms = '<?php print_string('noroomsfound', 'block_mrbs_nds'); ?>';
        window.onload = RoomSearch();
    </script>

<?php include "trailer.php"; ?>
