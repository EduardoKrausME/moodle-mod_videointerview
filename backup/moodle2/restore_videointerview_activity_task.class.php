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
 * restore_videointerview_activity_task.class.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore task for Video Interview.
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/videointerview/backup/moodle2/restore_videointerview_stepslib.php');

/**
 * Video Interview restore task.
 */
class restore_videointerview_activity_task extends restore_activity_task {
    /**
     * No activity-specific restore settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Adds the activity restore structure step.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_videointerview_activity_structure_step('videointerview_structure', 'videointerview.xml'));
    }

    /**
     * Defines content fields decoded during restore.
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('videointerview', ['intro'], 'videointerview'),
            new restore_decode_content('videointerview_questions', ['prompt'], 'videointerview_question'),
            new restore_decode_content('videointerview_attempts', ['teacherfeedback'], 'videointerview_attempt'),
            new restore_decode_content('videointerview_responses', ['textresponse'], 'videointerview_response'),
        ];
    }

    /**
     * Defines URL decoding rules.
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('VIDEOINTERVIEWVIEWBYID', '/mod/videointerview/view.php?id=$1', 'course_module'),
            new restore_decode_rule('VIDEOINTERVIEWINDEX', '/mod/videointerview/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Defines restore log rules.
     */
    public static function define_restore_log_rules(): array {
        return [];
    }
}
