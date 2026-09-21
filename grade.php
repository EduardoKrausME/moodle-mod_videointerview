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
 * grade.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Teacher grading page.
 */
require('../../config.php');
require_once("{$CFG->libdir}/formslib.php");
$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:grade', $context);
$attempt = $DB->get_record('videointerview_attempts', ['id' => $attemptid, 'videointerviewid' => $activity->id], '*', MUST_EXIST);
$user = core_user::get_user($attempt->userid, '*', MUST_EXIST);
$criteria = $DB->get_records('videointerview_criteria', ['videointerviewid' => $activity->id], 'sortorder ASC, id ASC');
if (!$criteria) {
    \mod_videointerview\interview_manager::ensure_default_criteria($activity->id);
    $criteria = $DB->get_records('videointerview_criteria', ['videointerviewid' => $activity->id], 'sortorder ASC, id ASC');
}
$mform = new \mod_videointerview\form\grade_form(null, [
    'cmid' => $cm->id,
    'attemptid' => $attempt->id,
    'criteria' => $criteria,
]);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/videointerview/report.php', ['id' => $cm->id]));
}
if ($data = $mform->get_data()) {
    $now = time();
    foreach ($criteria as $criterion) {
        $existing = $DB->get_record('videointerview_grades', ['attemptid' => $attempt->id, 'criterionid' => $criterion->id]);
        $record = $existing ?: new stdClass();
        $record->attemptid = $attempt->id;
        $record->criterionid = $criterion->id;
        $record->score = (float)$data->{'score_' . $criterion->id};
        $record->feedback = (string)$data->{'feedback_' . $criterion->id};
        $record->graderid = $USER->id;
        $record->timegraded = $now;
        if ($existing) {
            $DB->update_record('videointerview_grades', $record);
        } else {
            $DB->insert_record('videointerview_grades', $record);
        }
    }
    $attempt->teacherfeedback = $data->teacherfeedback_editor['text'];
    $attempt->feedbackformat = $data->teacherfeedback_editor['format'];
    $attempt->timemodified = $now;
    $DB->update_record('videointerview_attempts', $attempt);
    \mod_videointerview\interview_manager::recalculate_grade($activity, $attempt->id);
    redirect(new moodle_url('/mod/videointerview/report.php', ['id' => $cm->id]));
}
$existinggrades = $DB->get_records('videointerview_grades', ['attemptid' => $attempt->id]);
$defaults = ['teacherfeedback_editor' => ['text' => $attempt->teacherfeedback, 'format' => $attempt->feedbackformat]];
foreach ($existinggrades as $grade) {
    $defaults['score_' . $grade->criterionid] = $grade->score;
    $defaults['feedback_' . $grade->criterionid] = $grade->feedback;
}
$mform->set_data($defaults);
$questions = \mod_videointerview\interview_manager::get_applicable_questions($activity->id, $attempt->id);
$responseitems = [];
foreach ($questions as $question) {
    $response = $DB->get_record('videointerview_responses', ['attemptid' => $attempt->id, 'questionid' => $question->id]);
    $responseitems[] = [
        'title' => format_string($question->title),
        'answerhtml' => \mod_videointerview\interview_manager::response_summary($response ?: null, $context),
    ];
}
$PAGE->set_url('/mod/videointerview/grade.php', ['id' => $cm->id, 'attemptid' => $attempt->id]);
$PAGE->set_title(get_string('gradeattempt', 'videointerview'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradeattempt', 'videointerview') . ': ' . fullname($user));
echo $OUTPUT->render_from_template('mod_videointerview/grade_responses', ['responses' => $responseitems]);
$mform->display();
echo $OUTPUT->footer();
