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
 * Activity settings form.
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity form.
 */
class mod_videointerview_mod_form extends moodleform_mod {
    /**
     * Defines form fields.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('videointerviewname', 'videointerview'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $attemptoptions = [0 => get_string('unlimited', 'videointerview')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptoptions[$i] = (string)$i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'videointerview'), $attemptoptions);
        $mform->setDefault('maxattempts', 1);
        $mform->addElement('selectyesno', 'allowreview', get_string('allowreview', 'videointerview'));
        $mform->setDefault('allowreview', 1);

        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds custom completion rules.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $field = $this->get_suffixed_name('completionsubmit');
        $mform->addElement('checkbox', $field, get_string('completionsubmit', 'videointerview'));
        $mform->setDefault($field, 1);
        return [$field];
    }

    /**
     * Returns whether completion rule is enabled.
     */
    public function completion_rule_enabled($data): bool {
        $field = $this->get_suffixed_name('completionsubmit');
        return !empty($data[$field]);
    }

    /**
     * Prepares custom completion fields.
     */
    public function data_preprocessing(&$defaultvalues): void {
        if (array_key_exists('completionsubmit', $defaultvalues)) {
            $defaultvalues[$this->get_suffixed_name('completionsubmit')] = $defaultvalues['completionsubmit'];
        }
    }
}
