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
 * backup_videointerview_stepslib.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines Video Interview backup data.
 */
class backup_videointerview_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $activity = new backup_nested_element('videointerview', ['id'], [
            'name', 'intro', 'introformat', 'maxattempts', 'allowreview', 'grade', 'completionsubmit',
            'timecreated', 'timemodified',
        ]);
        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'position', 'title', 'prompt', 'promptformat', 'videosource', 'videourl', 'responsetypes', 'timelimit',
            'required', 'conditionquestionid', 'conditionoperator', 'conditionvalue', 'timecreated', 'timemodified',
        ]);
        $criteria = new backup_nested_element('criteria');
        $criterion = new backup_nested_element('criterion', ['id'], [
            'sortorder', 'name', 'description', 'maxscore', 'weight', 'timecreated', 'timemodified',
        ]);
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'attemptno', 'status', 'timestarted', 'timesubmitted', 'timemodified', 'grade',
            'teacherfeedback', 'feedbackformat',
        ]);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'questionid', 'responsetype', 'textresponse', 'textformat', 'timestarted', 'timeanswered',
            'durationseconds', 'timemodified',
        ]);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'questionid', 'duration', 'lastposition', 'maxposition', 'percent', 'timecreated', 'timemodified',
        ]);
        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', ['id'], ['criterionid', 'score', 'feedback', 'graderid', 'timegraded']);

        $activity->add_child($questions);
        $questions->add_child($question);
        $activity->add_child($criteria);
        $criteria->add_child($criterion);
        $activity->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($responses);
        $responses->add_child($response);
        $attempt->add_child($progresses);
        $progresses->add_child($progress);
        $attempt->add_child($grades);
        $grades->add_child($grade);

        $activity->set_source_table('videointerview', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('videointerview_questions', ['videointerviewid' => backup::VAR_ACTIVITYID]);
        $criterion->set_source_table('videointerview_criteria', ['videointerviewid' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $attempt->set_source_table('videointerview_attempts', ['videointerviewid' => backup::VAR_ACTIVITYID]);
            $response->set_source_table('videointerview_responses', ['attemptid' => backup::VAR_PARENTID]);
            $progress->set_source_table('videointerview_progress', ['attemptid' => backup::VAR_PARENTID]);
            $grade->set_source_table('videointerview_grades', ['attemptid' => backup::VAR_PARENTID]);
        }

        $attempt->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'graderid');
        $question->annotate_files('mod_videointerview', 'questionvideo', 'id');
        $response->annotate_files('mod_videointerview', 'responseaudio', 'id');
        $response->annotate_files('mod_videointerview', 'responsevideo', 'id');
        return $this->prepare_activity_structure($activity);
    }
}
