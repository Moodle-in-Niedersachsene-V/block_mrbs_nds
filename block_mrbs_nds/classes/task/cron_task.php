<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_mrbs_nds\task;

/**
 * Scheduled task for block_mrbs_nds.
 *
 * Phase 1 fix: replaces the deprecated block::cron() method.
 * Runs the import.php logic (calendar sync / cleanup) every 5 minutes,
 * matching the original $plugin->cron = 300.
 */
class cron_task extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('crontask', 'block_mrbs_nds');
    }

    public function execute(): void {
        global $CFG;

        $import = $CFG->dirroot . '/blocks/mrbs_nds/import.php';
        if (file_exists($import)) {
            require_once($import);
        } else {
            mtrace('block_mrbs_nds: import.php not found, skipping cron task.');
        }
    }
}
