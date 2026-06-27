<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task definitions for block_mrbs_nds.
 *
 * Phase 1 fix: replaces the deprecated cron() method in the block class,
 * which was removed in Moodle 4.0. The cron_task runs every 5 minutes,
 * matching the original $plugin->cron = 300 setting.
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\block_mrbs_nds\task\cron_task',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 0,
    ],
];
