<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * backup_videointerview_activity_task.class.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup task for Video Interview.
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/videointerview/backup/moodle2/backup_videointerview_stepslib.php');

/**
 * Video Interview backup task.
 */
class backup_videointerview_activity_task extends backup_activity_task {
    /**
     * No activity-specific backup settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Adds the activity structure step.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_videointerview_activity_structure_step('videointerview_structure', 'videointerview.xml'));
    }

    /**
     * Encodes links to this activity.
     */
    public static function encode_content_links($content): string {
        global $CFG;
        $base = preg_quote($CFG->wwwroot . '/mod/videointerview', '#');
        $content = preg_replace("#({$base}/index.php\\?id=)([0-9]+)#", '$@VIDEOINTERVIEWINDEX*$2@$', $content);
        $content = preg_replace("#({$base}/view.php\\?id=)([0-9]+)#", '$@VIDEOINTERVIEWVIEWBYID*$2@$', $content);
        return $content;
    }
}
