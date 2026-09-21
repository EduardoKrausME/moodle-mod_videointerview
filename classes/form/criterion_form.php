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
 * criterion_form.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videointerview\form;

use moodleform;

/**
 * Form used to create and edit grading criteria.
 */
class criterion_form extends moodleform {
    /**
     * Defines fields.
     */
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata;
        $mform->addElement('hidden', 'cmid', $custom['cmid']);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'criterionid', $custom['criterionid'] ?? 0);
        $mform->setType('criterionid', PARAM_INT);
        $mform->addElement('text', 'name', get_string('criterionname', 'videointerview'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('textarea', 'description',
            get_string('criteriondescription', 'videointerview'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('text', 'maxscore',
            get_string('maxscore', 'videointerview'), ['size' => 8]);
        $mform->setType('maxscore', PARAM_FLOAT);
        $mform->setDefault('maxscore', 10);
        $mform->addRule('maxscore', null, 'required', null, 'client');
        $mform->addElement('text', 'weight', get_string('weight', 'videointerview'), ['size' => 8]);
        $mform->setType('weight', PARAM_FLOAT);
        $mform->setDefault('weight', 1);
        $mform->addRule('weight', null, 'required', null, 'client');
        $this->add_action_buttons();
    }

    /**
     * Validates values.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((float)($data['maxscore'] ?? 0) <= 0) {
            $errors['maxscore'] = get_string('invaliddata', 'error');
        }
        if ((float)($data['weight'] ?? 0) < 0) {
            $errors['weight'] = get_string('invaliddata', 'error');
        }
        return $errors;
    }
}
