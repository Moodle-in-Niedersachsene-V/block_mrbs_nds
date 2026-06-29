<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Block class for MRBS NDS.
 *
 * Phase 1+2 changes vs. block_mrbs_rlp:
 * - Removed deprecated cron() method → classes/task/cron_task.php
 * - get_config component: 'block/mrbs_rlp' → 'block_mrbs_nds'
 * - Capability prefix updated to block/mrbs_nds
 * - Output uses pix_icon() instead of raw <img>
 * - s() used on all output
 */
class block_mrbs_nds extends block_base {

    public function init(): void {
        $this->title = self::get_block_title();
        $this->content_type = BLOCK_TYPE_TEXT;
    }

    /**
     * Returns the block title with optional site suffix from admin settings.
     * e.g. "MRBS Raumbuchung NDS" or just "MRBS Raumbuchung"
     */
    public static function get_block_title(): string {
        $base   = get_string('blockname', 'block_mrbs_nds');
        $suffix = trim((string) get_config('block_mrbs_nds', 'site_suffix'));
        return $suffix !== '' ? $base . ' ' . $suffix : $base;
    }

    public function has_config(): bool {
        return true;
    }

    public function applicable_formats(): array {
        return ['all' => true];
    }

    public function get_content(): ?stdClass {
        global $CFG, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        // Phase 1 fix: underscore-separated component name (not 'block/mrbs_nds').
        $cfg     = get_config('block_mrbs_nds');
        $context = context_system::instance();

        $canview  = has_capability('block/mrbs_nds:viewmrbs',      $context);
        $canedit  = has_capability('block/mrbs_nds:editmrbs',      $context);
        $canadmin = has_capability('block/mrbs_nds:administermrbs', $context);

        if (!$canview && !$canedit && !$canadmin) {
            return null;
        }

        $serverpath = !empty($cfg->serverpath)
            ? $cfg->serverpath
            : $CFG->wwwroot . '/blocks/mrbs_nds/web';

        $label  = get_string('accessmrbs_nds', 'block_mrbs_nds');
        $title  = self::get_block_title();
        $icon   = $OUTPUT->pix_icon('web', '', 'block_mrbs_nds', ['height' => '16', 'width' => '16']);
        $target = !empty($cfg->newwindow) ? ' target="_blank" rel="noopener noreferrer"' : '';

        // Note: moodle_url::out() produces a correctly escaped URL.
        // s() must NOT be applied to a full URL as it HTML-encodes slashes and breaks the href.
        $indexurl = (new moodle_url($serverpath . '/index.php'))->out(false);

        $this->content         = new stdClass();
        $this->content->text   = '<a href="' . $indexurl . '"' . $target . '>'
                                 . $icon . '&nbsp;' . s($label) . '</a>';
        $this->content->footer = '';

        return $this->content;
    }
}
