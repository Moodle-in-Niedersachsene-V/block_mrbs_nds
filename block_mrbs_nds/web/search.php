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

$day = optional_param('day', 0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$today_d = optional_param('today_day', 0, PARAM_INT);
$today_m = optional_param('today_month', 0, PARAM_INT);
$today_y = optional_param('today_year', 0, PARAM_INT);
$area = optional_param('area', 0, PARAM_INT);
$advanced = optional_param('advanced', 0, PARAM_BOOL);
$search_str = optional_param('search_str', '', PARAM_TEXT);
$search_room = optional_param('search_room', 0, PARAM_INT);
$search_type = optional_param('search_type', '', PARAM_ALPHA);
$total = optional_param('total', 0, PARAM_INT);
$search_pos = optional_param('search_pos', 0, PARAM_INT);

//If we dont know the right date then make it up
if (($day == 0) or ($month == 0) or ($year == 0)) {
    $day = date("d");
    $month = date("m");
    $year = date("Y");
}

$thisurl = new moodle_url('/blocks/mrbs_nds/web/search.php', ['day' => $day, 'month' => $month, 'year' => $year]);
if ($area) {
    $thisurl->param('area', $area);
} else {
    $area = get_default_area();
}
if ($advanced) {
    $thisurl->param('advanced', $advanced);
}
if ($search_str) {
    $thisurl->param('search_str', $search_str);
}
if ($search_room) {
    $thisurl->param('search_room', $search_room);
}
if ($search_type !== '') {
    $thisurl->param('search_type', $search_type);
}
if ($search_pos) {
    $thisurl->param('search_pos', $search_pos);    
}
$PAGE->set_url($thisurl);
require_login();

print_header_mrbs_nds($day, $month, $year, $area);

if ($advanced || !$search_str) {
    echo '<h2 class="mrbs-page-title">' . s(get_string('advanced_search', 'block_mrbs_nds')) . '</h2>';

    $searchurl = new moodle_url('/blocks/mrbs_nds/web/search.php');
    echo '<form method="get" action="' . $searchurl->out(false) . '" class="mrbs-search-grid">';

    echo '<div>';
    echo '<div class="form-row mb-2">';
    echo '<label class="form-label" for="id_search_str">' . s(get_string('search_for', 'block_mrbs_nds')) . '</label>';
    echo '<input type="text" id="id_search_str" class="form-control" name="search_str" value="' . s($search_str) . '">';
    echo '</div>';

    echo '<div class="form-row mb-2">';
    echo '<label class="form-label" for="id_room">' . s(get_string('rooms', 'block_mrbs_nds')) . '</label>';
    echo '<select id="id_room" class="form-control" name="search_room">';
    echo '<option value="0">' . s(get_string('allrooms', 'block_mrbs_nds')) . '</option>';
    $allrooms = $DB->get_records('block_mrbs_rlp_room', null, 'room_name');
    foreach ($allrooms as $dbroom) {
        $sel = ($search_room == $dbroom->id) ? ' selected' : '';
        echo '<option value="' . (int)$dbroom->id . '"' . $sel . '>' . s($dbroom->room_name) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="form-row mb-2">';
    echo '<label class="form-label" for="id_type">' . s(get_string('type', 'block_mrbs_nds')) . '</label>';
    echo '<select id="id_type" class="form-control" name="search_type">';
    echo '<option value="">' . s(get_string('alltypes', 'block_mrbs_nds')) . '</option>';
    foreach ($typel as $tc => $tl) {
        if (empty($tl)) {
            continue;
        }
        $sel = ($search_type === $tc) ? ' selected' : '';
        echo '<option value="' . s($tc) . '"' . $sel . '>' . s($tl) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    echo '<div>';
    echo '<div class="form-row mb-2">';
    echo '<label class="form-label">' . s(get_string('from', 'block_mrbs_nds')) . '</label>';
    echo '<div class="d-flex gap-1">';
    genDateSelector('', $day, $month, $year);
    echo '</div>';
    echo '</div>';
    echo '<div class="form-row mb-2">';
    echo '<label class="form-label">' . s(get_string('to', 'block_mrbs_nds')) . '</label>';
    echo '<div class="d-flex gap-1">';
    $end_d = $today_d ?: (int)date('d', strtotime('+30 days', mktime(0,0,0,$month ?: (int)date('m'), $day ?: (int)date('d'), $year ?: (int)date('Y'))));
    $end_m = $today_m ?: (int)date('m', strtotime('+30 days', mktime(0,0,0,$month ?: (int)date('m'), $day ?: (int)date('d'), $year ?: (int)date('Y'))));
    $end_y = $today_y ?: (int)date('Y', strtotime('+30 days', mktime(0,0,0,$month ?: (int)date('m'), $day ?: (int)date('d'), $year ?: (int)date('Y'))));
    genDateSelector('today_', $end_d, $end_m, $end_y);
    echo '</div>';
    echo '<div class="form-hint">' . s(get_string('search_period_hint', 'block_mrbs_nds')) . '</div>';
    echo '</div>';
    echo '<div class="text-end mt-2">';
    echo '<button type="submit" class="btn btn-primary">' . s(get_string('search')) . '</button>';
    echo '</div>';
    echo '</div>';

    echo '</form>';

    require_once __DIR__ . '/trailer.php';
    exit;
}


// A search needs at least one criterion: text, room, or type.
if (!$search_str && !$search_room && $search_type === '') {
    echo '<div class="alert alert-warning">' . s(get_string('invalid_search', 'block_mrbs_nds')) . '</div>';
    require_once __DIR__ . "/trailer.php";
    exit;
}

// now is used so that we only display entries newer than the current time
$result_title = $search_str !== ''
    ? s(get_string('search_results', 'block_mrbs_nds')) . ' &bdquo;' . s($search_str) . '&ldquo;'
    : s(get_string('search_results', 'block_mrbs_nds'));
echo '<h3 class="mrbs-page-title">' . $result_title . '</h3>';

$now = mktime(0, 0, 0, $month, $day, $year);

// End of search window: defaults to 30 days after $now if not explicitly set.
$search_end = ($today_d && $today_m && $today_y)
    ? mktime(23, 59, 59, $today_m, $today_d, $today_y)
    : strtotime('+30 days', $now);

// This is the main part of the query predicate, used in both queries:
// Search by booker name (via user table), entry name, or description.
// create_by is now a numeric user.id, so we join to {user} for name search.
// The text clause is only added if a search term was actually entered.
// Columns are aliased correctly from the start (no string-replace post-processing,
// which previously corrupted "u.firstname"/"u.lastname" into "u.firste.name").
//
// IMPORTANT: $DB->sql_like() only builds the "field LIKE ?" SQL fragment — it does
// NOT add % wildcards to the bound value. Without wrapping the term in %...%, the
// match is an exact equality, so substring searches like "Test" never find entries
// where "Test" is only part of the description. The wildcards must be added here.
$params = [];
if ($search_str !== '') {
    $like_str = '%' . $DB->sql_like_escape($search_str) . '%';
    $sql_pred = "( " . $DB->sql_like("u.firstname", '?', false)
            . " OR " . $DB->sql_like("u.lastname", '?', false)
            . " OR " . $DB->sql_like("e.name", '?', false)
            . " OR " . $DB->sql_like("e.description", '?', false)
            . ") AND e.end_time > ? AND e.start_time < ?";
    $params = [$like_str, $like_str, $like_str, $like_str, $now, $search_end];
} else {
    $sql_pred = "e.end_time > ? AND e.start_time < ?";
    $params = [$now, $search_end];
}

// Optional filter: restrict to a specific room.
if ($search_room > 0) {
    $sql_pred .= " AND e.room_id = ?";
    $params[] = $search_room;
}

// Optional filter: restrict to a specific booking type.
if ($search_type !== '') {
    $sql_pred .= " AND e.type = ?";
    $params[] = $search_type;
}

$base_from = "FROM {block_mrbs_rlp_entry} e
              JOIN {block_mrbs_rlp_room} r ON e.room_id = r.id
              LEFT JOIN {user} u ON u.id = e.create_by";

// The first time the search is called, we get the total
// number of matches.  This is passed along to subsequent
// searches so that we don't have to run it for each page.
if (!$total) {
    $count_sql = "SELECT COUNT(*) $base_from WHERE $sql_pred";
    $total = (int) $DB->count_records_sql($count_sql, $params);
    $thisurl->param('total', $total);
}

if ($total <= 0) {
    echo '<div class="alert alert-info">' . s(get_string('nothingtodisplay')) . '</div>';
    require_once __DIR__ . "/trailer.php";
    exit;
}

if ($search_pos <= 0) {
    $search_pos = 0;
} elseif ($search_pos >= $total) {
    $search_pos = $total - ($total % $search["count"]);
}

// Now we set up the "real" query using LIMIT to just get the stuff we want.
$sql = "SELECT e.id, e.create_by, e.name, e.description, e.start_time, r.area_id, r.room_name
        $base_from
        WHERE $sql_pred
        ORDER BY e.start_time asc ";

// this is a flag to tell us not to display a "Next" link
$result = $DB->get_records_sql($sql, $params, $search_pos, $search['count']);
$num_records = count($result);

$has_prev = $search_pos > 0;
$has_next = $search_pos < ($total - $search["count"]);

if ($has_prev || $has_next) {
    echo '<p class="fw-semibold">' . s(get_string('records', 'block_mrbs_nds')) . (int)($search_pos + 1) . s(get_string('through', 'block_mrbs_nds')) . (int)($search_pos + $num_records) . s(get_string('of', 'block_mrbs_nds')) . (int)$total . '</p>';

    // display a "Previous" button if necessary
    if ($has_prev) {
        $pos = max(0, $search_pos - $search["count"]);
        echo '<a href="' . $thisurl->out(true, ['search_pos' => $pos]) . '">';
    }

    echo '<span class="fw-semibold">' . s(get_string('previous')) . '</span>';

    if ($has_prev) {
        echo "</A>";
    }

    // print a separator for Next and Previous
    echo(" | ");

    // display a "Previous" button if necessary
    if ($has_next) {
        $pos = max(0, $search_pos + $search["count"]);
        echo '<a href="' . $thisurl->out(true, ['search_pos' => $pos]) . '">'; 
    }

    echo '<span class="fw-semibold">' . s(get_string('next')) . '</span>';

    if ($has_next) {
        echo "</A>";
    }
}

?>
<div class="mrbs-table-wrap">
<table class="mrbs-table">
    <tr>
        <th><?php echo s(get_string('entry', 'block_mrbs_nds')) ?></th>
        <th><?php echo s(get_string('createdby', 'block_mrbs_nds')) ?></th>
        <th><?php echo s(get_string('namebooker', 'block_mrbs_nds')) ?></th>
        <th><?php echo s(get_string('room', 'block_mrbs_nds')) ?></th>
        <th><?php echo s(get_string('description')) ?></th>
        <th><?php echo s(get_string('start_date', 'block_mrbs_nds')) ?></th>
    </tr>
    <?php
    foreach ($result as $entry) {

        $userinfos = getUserinfos($entry->create_by);
        if ($userinfos) {
            $erstellt_von = $userinfos->firstname . ' ' . $userinfos->lastname;
        } else {
            $erstellt_von = (string) $entry->create_by;
        }

        $viewurl = new moodle_url('/blocks/mrbs_nds/web/view_entry.php', ['id' => $entry->id]);
        echo '<tr>';
        echo '<td><a href="' . $viewurl->out(false) . '">' . s(get_string('view')) . '</a></td>';
        echo '<td>' . s($erstellt_von) . '</td>';
        echo '<td>' . s($entry->name) . '</td>';
        echo '<td>' . s($entry->room_name) . '</td>';
        echo '<td>' . s($entry->description) . '</td>';

        // Link to the day view for this entry's date.
        $link = getdate($entry->start_time);
        $dayurl = new moodle_url('/blocks/mrbs_nds/web/day.php',
            ['day' => $link['mday'], 'month' => $link['mon'], 'year' => $link['year'],
             'area' => $entry->area_id]);

        if (empty($enable_periods)) {
            $link_str = time_date_string($entry->start_time);
        } else {
            list(, $link_str) = period_date_string($entry->start_time);
        }
        echo '<td><a href="' . $dayurl->out(false) . '">' . $link_str . '</a></td>';
        echo '</tr>';
    }
    ?>
</table>
</div>
<?php
    require_once __DIR__ . "/trailer.php";
