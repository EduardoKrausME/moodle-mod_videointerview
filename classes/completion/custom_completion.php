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
 * custom_completion.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videointerview\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion rules for Video Interview.
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns state for a custom rule.
     */
    public function get_state(string $rule): int {
        global $DB;
        if ($rule !== 'completionsubmit') {
            throw new \coding_exception('Unknown completion rule: ' . $rule);
        }
        if (empty($this->cm->customdata['customcompletionrules']['completionsubmit'])) {
            return COMPLETION_INCOMPLETE;
        }
        $exists = $DB->record_exists_select('videointerview_attempts',
            'videointerviewid = :id AND userid = :userid AND status IN (:submitted, :graded)', [
                'id' => $this->cm->instance,
                'userid' => $this->userid,
                'submitted' => 'submitted',
                'graded' => 'graded',
            ]);
        return $exists ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Returns the custom completion rules defined by this activity.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsubmit'];
    }

    /**
     * Describes custom completion rule.
     */
    public function get_custom_rule_descriptions(): array {
        return ['completionsubmit' => get_string('completiondetail:submit', 'videointerview')];
    }

    /**
     * Returns custom rule names.
     */
    public function get_sort_order(): array {
        return ['completionsubmit'];
    }
}
