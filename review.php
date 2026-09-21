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
 * review.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Review page for an interview attempt.
 */
require('../../config.php');
$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:view', $context);
$attempt = $DB->get_record('videointerview_attempts', ['id' => $attemptid, 'videointerviewid' => $activity->id], '*', MUST_EXIST);
$teacher = has_capability('mod/videointerview:viewreport', $context) || has_capability('mod/videointerview:grade', $context);
if (!$teacher && $attempt->status === 'inprogress' && empty($activity->allowreview)) {
    $next = \mod_videointerview\interview_manager::get_next_question($activity->id, $attempt->id);
    if ($next) {
        redirect(new moodle_url('/mod/videointerview/answer.php', [
            'id' => $cm->id,
            'attemptid' => $attempt->id,
            'questionid' => $next->id,
        ]));
    }
    redirect(new moodle_url('/mod/videointerview/submit.php', [
        'id' => $cm->id,
        'attemptid' => $attempt->id,
        'sesskey' => sesskey(),
    ]));
}
if ((int)$attempt->userid !== (int)$USER->id && !$teacher) {
    throw new moodle_exception('nopermissions', 'error');
}
$questions = \mod_videointerview\interview_manager::get_applicable_questions($activity->id, $attempt->id);
$items = [];
$missingrequired = false;
foreach ($questions as $question) {
    $response = $DB->get_record('videointerview_responses', ['attemptid' => $attempt->id, 'questionid' => $question->id]);
    $answered = $response && \mod_videointerview\interview_manager::response_is_answered($response);
    if ($question->required && !$answered) {
        $missingrequired = true;
    }
    $items[] = [
        'title' => format_string($question->title),
        'answerhtml' => \mod_videointerview\interview_manager::response_summary($response ?: null, $context),
        'answered' => $answered,
        'required' => (bool)$question->required,
        'canedit' => !$teacher && $attempt->status === 'inprogress' && !empty($activity->allowreview),
        'editurl' => (new moodle_url('/mod/videointerview/answer.php',
            ['id' => $cm->id, 'attemptid' => $attempt->id, 'questionid' => $question->id]))->out(false),
    ];
}
$data = [
    'title' => get_string('reviewresponses', 'videointerview'),
    'attempt' => $attempt->attemptno,
    'questions' => $items,
    'cansubmit' => !$teacher && $attempt->status === 'inprogress' && !$missingrequired,
    'missingrequired' => !$teacher && $attempt->status === 'inprogress' && $missingrequired,
    'submiturl' => (new moodle_url('/mod/videointerview/submit.php',
        ['id' => $cm->id, 'attemptid' => $attempt->id, 'sesskey' => sesskey()]))->out(false),
    'backurl' => (new moodle_url('/mod/videointerview/view.php',
        ['id' => $cm->id]))->out(false),
    'grade' => $attempt->grade !== null ? format_float($attempt->grade, 2) . ' / ' . format_float($activity->grade, 2) : '',
    'hasgrade' => $attempt->grade !== null,
    'feedbackhtml' => $attempt->teacherfeedback ? format_text($attempt->teacherfeedback, $attempt->feedbackformat,
        ['context' => $context]) : '',
    'hasfeedback' => !empty($attempt->teacherfeedback),
];
$PAGE->set_url('/mod/videointerview/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]);
$PAGE->set_title(get_string('reviewresponses', 'videointerview'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('mod_videointerview/interview', 'initConfirm');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videointerview/review', $data);
echo $OUTPUT->footer();
