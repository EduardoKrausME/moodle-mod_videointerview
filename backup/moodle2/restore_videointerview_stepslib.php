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
 * restore_videointerview_stepslib.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores Video Interview activity data.
 */
class restore_videointerview_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines restore paths.
     */
    protected function define_structure(): array {
        $paths = [];
        $paths[] = new restore_path_element('videointerview', '/activity/videointerview');
        $paths[] = new restore_path_element('videointerview_question', '/activity/videointerview/questions/question');
        $paths[] = new restore_path_element('videointerview_criterion', '/activity/videointerview/criteria/criterion');
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('videointerview_attempt',
                '/activity/videointerview/attempts/attempt');
            $paths[] = new restore_path_element('videointerview_response',
                '/activity/videointerview/attempts/attempt/responses/response');
            $paths[] = new restore_path_element('videointerview_progress',
                '/activity/videointerview/attempts/attempt/progresses/progress');
            $paths[] = new restore_path_element('videointerview_grade',
                '/activity/videointerview/attempts/attempt/grades/grade');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the main activity.
     */
    protected function process_videointerview($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record('videointerview', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('videointerview', $oldid, $newitemid, true);
    }

    /**
     * Restores a question.
     */
    protected function process_videointerview_question($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videointerviewid = $this->get_new_parentid('videointerview');
        if (!empty($data->conditionquestionid)) {
            $mapped = $this->get_mappingid('videointerview_question', $data->conditionquestionid);
            $data->conditionquestionid = $mapped ?: null;
            if (!$mapped) {
                $data->conditionoperator = 'always';
            }
        }
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videointerview_questions', $data);
        $this->set_mapping('videointerview_question', $oldid, $newid, true);
    }

    /**
     * Restores a criterion.
     */
    protected function process_videointerview_criterion($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videointerviewid = $this->get_new_parentid('videointerview');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videointerview_criteria', $data);
        $this->set_mapping('videointerview_criterion', $oldid, $newid);
    }

    /**
     * Restores a user attempt.
     */
    protected function process_videointerview_attempt($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->videointerviewid = $this->get_new_parentid('videointerview');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timestarted = $this->apply_date_offset($data->timestarted);
        $data->timesubmitted = $data->timesubmitted ? $this->apply_date_offset($data->timesubmitted) : 0;
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videointerview_attempts', $data);
        $this->set_mapping('videointerview_attempt', $oldid, $newid);
    }

    /**
     * Restores a response.
     */
    protected function process_videointerview_response($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->attemptid = $this->get_new_parentid('videointerview_attempt');
        $data->questionid = $this->get_mappingid('videointerview_question', $data->questionid);
        $data->timestarted = $this->apply_date_offset($data->timestarted);
        $data->timeanswered = $data->timeanswered ? $this->apply_date_offset($data->timeanswered) : 0;
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record('videointerview_responses', $data);
        $this->set_mapping('videointerview_response', $oldid, $newid, true);
    }

    /**
     * Restores viewing progress.
     */
    protected function process_videointerview_progress($data): void {
        global $DB;
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('videointerview_attempt');
        $data->questionid = $this->get_mappingid('videointerview_question', $data->questionid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $DB->insert_record('videointerview_progress', $data);
    }

    /**
     * Restores criterion grade.
     */
    protected function process_videointerview_grade($data): void {
        global $DB;
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('videointerview_attempt');
        $data->criterionid = $this->get_mappingid('videointerview_criterion', $data->criterionid);
        $data->graderid = $this->get_mappingid('user', $data->graderid);
        $data->timegraded = $this->apply_date_offset($data->timegraded);
        $DB->insert_record('videointerview_grades', $data);
    }

    /**
     * Restores related files.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_videointerview', 'questionvideo', 'videointerview_question');
        $this->add_related_files('mod_videointerview', 'responseaudio', 'videointerview_response');
        $this->add_related_files('mod_videointerview', 'responsevideo', 'videointerview_response');
    }
}
