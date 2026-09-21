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
 * question.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Question editor.
 */
require('../../config.php');
require_once("{$CFG->libdir}/formslib.php");

$id = required_param('id', PARAM_INT);
$questionid = optional_param('questionid', 0, PARAM_INT);
$action = optional_param('action', 'edit', PARAM_ALPHA);
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:manage', $context);
$question = $questionid ? $DB->get_record('videointerview_questions',
    ['id' => $questionid, 'videointerviewid' => $activity->id], '*', MUST_EXIST) : null;

if ($action === 'delete' && $question) {
    require_sesskey();
    $DB->execute("UPDATE {videointerview_questions}
                       SET conditionquestionid = NULL, conditionoperator = :always
                     WHERE conditionquestionid = :questionid", [
        'always' => 'always',
        'questionid' => $question->id,
    ]);
    $DB->delete_records('videointerview_progress', ['questionid' => $question->id]);
    $responses = $DB->get_records('videointerview_responses', ['questionid' => $question->id]);
    foreach ($responses as $response) {
        get_file_storage()->delete_area_files($context->id, 'mod_videointerview', 'responseaudio', $response->id);
        get_file_storage()->delete_area_files($context->id, 'mod_videointerview', 'responsevideo', $response->id);
    }
    $DB->delete_records('videointerview_responses', ['questionid' => $question->id]);
    get_file_storage()->delete_area_files($context->id, 'mod_videointerview', 'questionvideo', $question->id);
    $DB->delete_records('videointerview_questions', ['id' => $question->id]);
    redirect(new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]));
}

$conditionquestions = [];
$allquestions = $DB->get_records('videointerview_questions', ['videointerviewid' => $activity->id], 'position ASC, id ASC');
foreach ($allquestions as $candidate) {
    // Conditions may only depend on questions that appear earlier in the interview.
    if (!$question || (int)$candidate->position < (int)$question->position) {
        $conditionquestions[$candidate->id] = format_string($candidate->title);
    }
}
$mform = new \mod_videointerview\form\question_form(null, [
    'cmid' => $cm->id,
    'questionid' => $questionid,
    'conditionquestions' => $conditionquestions,
]);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]));
}
if ($data = $mform->get_data()) {
    $now = time();
    $record = $question ?: new stdClass();
    $record->videointerviewid = $activity->id;
    if (!$question) {
        $record->position = 10 +
            (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(position), 0) FROM {videointerview_questions} WHERE videointerviewid = :id',
                ['id' => $activity->id]);
        $record->timecreated = $now;
    }
    $record->title = $data->title;
    $record->prompt = $data->prompt_editor['text'];
    $record->promptformat = $data->prompt_editor['format'];
    $record->videosource = $data->videosource;
    $record->videourl = $data->videosource === 'upload' ? '' : trim((string)$data->videourl);
    $record->responsetypes = implode(',', array_values((array)$data->responsetypes));
    $record->timelimit = (int)$data->timelimit;
    $record->required = (int)$data->required;
    $record->conditionquestionid = !empty($data->conditionquestionid) ? (int)$data->conditionquestionid : null;
    $record->conditionoperator = $record->conditionquestionid ? $data->conditionoperator : 'always';
    $record->conditionvalue = (string)$data->conditionvalue;
    $record->timemodified = $now;
    if ($question) {
        $DB->update_record('videointerview_questions', $record);
    } else {
        $record->id = $DB->insert_record('videointerview_questions', $record);
    }
    if ($data->videosource === 'upload') {
        file_save_draft_area_files($data->videofile, $context->id, 'mod_videointerview', 'questionvideo', $record->id, [
            'subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video'],
        ]);
    } else {
        get_file_storage()->delete_area_files($context->id, 'mod_videointerview', 'questionvideo', $record->id);
    }
    redirect(new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]));
}

if ($question) {
    $draftid = file_get_submitted_draft_itemid('videofile');
    file_prepare_draft_area($draftid, $context->id, 'mod_videointerview', 'questionvideo', $question->id,
        ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video']]);
    $question->videofile = $draftid;
    $question->responsetypes = array_filter(explode(',', $question->responsetypes));
    $question->prompt_editor = ['text' => $question->prompt, 'format' => $question->promptformat];
    $mform->set_data($question);
}
$PAGE->set_url('/mod/videointerview/question.php', ['id' => $cm->id, 'questionid' => $questionid]);
$PAGE->set_title(get_string($question ? 'editquestion' : 'addquestion', 'videointerview'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($question ? 'editquestion' : 'addquestion', 'videointerview'));
$mform->display();
echo $OUTPUT->footer();
