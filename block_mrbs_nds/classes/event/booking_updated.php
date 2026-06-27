<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * booking_updated event for block_mrbs_nds.
 * @package block_mrbs_nds
 */

namespace block_mrbs_nds\event;

defined('MOODLE_INTERNAL') || die();

class booking_updated extends \core\event\base {

    protected function init() {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'block_mrbs_nds_entry';
    }

    public static function get_name() {
        return get_string('eventbookingupdated', 'block_mrbs_nds');
    }

    public function get_description() {
        return "User with id '{$this->userid}' updated a booking in '{$this->other['room']}'"
             . " for '{$this->other['name']}'.";
    }

    public function get_url() {
        return new \moodle_url('/blocks/mrbs_nds/web/view_entry.php', ['id' => $this->objectid]);
    }

    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['name'])) {
            throw new \coding_exception("Must specify 'name' in other[].");
        }
        if (!isset($this->other['room'])) {
            throw new \coding_exception("Must specify 'room' in other[].");
        }
    }
}
