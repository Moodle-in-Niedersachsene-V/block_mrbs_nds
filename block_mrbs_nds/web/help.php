<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Help / about page.
 *
 * Phase 1+2:
 *  - Plugin component: block_mrbs_rlp → block_mrbs_nds
 *  - require_login() enforced
 *  - s() on all variable output
 *  - require_once instead of include
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(__DIR__ . '/config.inc.php');
require_once(__DIR__ . '/functions.php');
global $USER, $CFG, $PAGE;

$day   = optional_param('day',   0, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$year  = optional_param('year',  0, PARAM_INT);
$area  = optional_param('area',  0, PARAM_INT);

if (!$day || !$month || !$year) {
    $day   = (int) date('d');
    $month = (int) date('m');
    $year  = (int) date('Y');
}

$thisurl = new moodle_url('/blocks/mrbs_nds/web/help.php',
    ['day' => $day, 'month' => $month, 'year' => $year]);
if ($area > 0) {
    $thisurl->param('area', $area);
} else {
    $area = get_default_area();
}
$PAGE->set_url($thisurl);

require_login();

$pluginmanager = core_plugin_manager::instance();
$plugin        = $pluginmanager->get_plugin_info('block_mrbs_nds');

print_header_mrbs_nds($day, $month, $year, $area);

echo '<h3>' . s(get_string('about_mrbs_nds', 'block_mrbs_nds')) . '</h3>';
echo '<p><strong>' . s(get_string('mrbs_nds', 'block_mrbs_nds')) . '</strong>: '
     . s($plugin ? $plugin->release : '2.0.0') . '</p>';

echo '<p><strong>PHP</strong>: ' . s(phpversion()) . '</p>';

echo '<h3>' . s(get_string('help')) . '</h3>';
echo '<p>' . s(get_string('please_contact', 'block_mrbs_nds'))
     . ' <a href="mailto:' . s($mrbs_nds_admin_email) . '">' . s($mrbs_nds_admin) . '</a> '
     . s(get_string('for_any_questions', 'block_mrbs_nds')) . '</p>';

$lang = current_language();
$faq  = $CFG->dirroot . '/blocks/mrbs_nds/lang/' . $lang . '/help/site_faq.html';
if (!file_exists($faq)) {
    $faq = $CFG->dirroot . '/blocks/mrbs_nds/lang/en/help/site_faq.html';
}
if (file_exists($faq)) {
    // FAQ is static admin-controlled HTML; readfile is safe here.
    readfile($faq);
}

require_once __DIR__ . '/trailer.php';
