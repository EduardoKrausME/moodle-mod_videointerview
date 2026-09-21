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
 * grade_form.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videointerview\form;

use moodleform;

/**
 * Teacher grading form.
 */
class grade_form extends moodleform {
    /**
     * Defines fields.
     */
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata;
        $mform->addElement('hidden', 'cmid', $custom['cmid']);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'attemptid', $custom['attemptid']);
        $mform->setType('attemptid', PARAM_INT);
        foreach ($custom['criteria'] as $criterion) {
            $mform->addElement('text', 'score_' . $criterion->id,
                format_string($criterion->name) . ' (0–' . format_float($criterion->maxscore, 2) . ')', ['size' => 8]);
            $mform->setType('score_' . $criterion->id, PARAM_FLOAT);
            $mform->addElement('textarea', 'feedback_' . $criterion->id,
                get_string('feedback', 'videointerview') . ': ' . format_string($criterion->name), ['rows' => 2, 'cols' => 70]);
            $mform->setType('feedback_' . $criterion->id, PARAM_TEXT);
        }
        $mform->addElement('editor', 'teacherfeedback_editor', get_string('feedback', 'videointerview'), null,
            ['maxfiles' => 0, 'noclean' => false]);
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validates criterion scores.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach ($this->_customdata['criteria'] as $criterion) {
            $key = 'score_' . $criterion->id;
            if (!array_key_exists($key, $data) || $data[$key] === '') {
                $errors[$key] = get_string('required');
                continue;
            }
            if ((float)$data[$key] < 0 || (float)$data[$key] > (float)$criterion->maxscore) {
                $errors[$key] = get_string('invaliddata', 'error');
            }
        }
        return $errors;
    }
}
