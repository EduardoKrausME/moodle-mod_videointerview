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
 * update_progress.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videointerview\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * AJAX service for question video progress.
 */
class update_progress extends external_api {
    /**
     * Parameter definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'questionid' => new external_value(PARAM_INT, 'Question id'),
            'position' => new external_value(PARAM_FLOAT, 'Current position'),
            'duration' => new external_value(PARAM_FLOAT, 'Video duration'),
        ]);
    }

    /**
     * Stores progress.
     */
    public static function execute(int $cmid, int $attemptid, int $questionid, float $position, float $duration): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('cmid', 'attemptid', 'questionid', 'position', 'duration'));
        $cm = get_coursemodule_from_id('videointerview', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videointerview:view', $context);
        $attempt = $DB->get_record('videointerview_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        if ((int)$attempt->userid !== (int)$USER->id || (int)$attempt->videointerviewid !== (int)$cm->instance) {
            throw new \moodle_exception('invalidattempt', 'videointerview');
        }
        $question = $DB->get_record('videointerview_questions', ['id' => $params['questionid']], '*', MUST_EXIST);
        if ((int)$question->videointerviewid !== (int)$cm->instance) {
            throw new \moodle_exception('invalidquestion', 'videointerview');
        }
        $duration = max(0.0, $params['duration']);
        $position = max(0.0, min($duration > 0 ? $duration : $params['position'], $params['position']));
        $record = $DB->get_record('videointerview_progress', [
            'attemptid' => $attempt->id,
            'questionid' => $question->id,
        ]);
        $now = time();
        if (!$record) {
            $record = (object)[
                'attemptid' => $attempt->id,
                'questionid' => $question->id,
                'duration' => $duration,
                'lastposition' => $position,
                'maxposition' => $position,
                'percent' => $duration > 0 ? min(100, $position / $duration * 100) : 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('videointerview_progress', $record);
        } else {
            $record->duration = max((float)$record->duration, $duration);
            $record->lastposition = $position;
            $record->maxposition = max((float)$record->maxposition, $position);
            $record->percent = $record->duration > 0 ? min(100, $record->maxposition / $record->duration * 100) : 0;
            $record->timemodified = $now;
            $DB->update_record('videointerview_progress', $record);
        }
        return ['percent' => (float)$record->percent];
    }

    /**
     * Return definition.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percent' => new external_value(PARAM_FLOAT, 'Maximum viewing percentage'),
        ]);
    }
}
