<?php
// This file is part of the MRBS NDS block for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Page footer.
 * Phase 3: Trailer-Navigation entfernt (ersetzt durch neue Tab-Nav oben).
 * Farblegende als kompakte Pillen-Legende.
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

echo '</div>'; // Close mrbs_nds_container.

echo $OUTPUT->footer();
