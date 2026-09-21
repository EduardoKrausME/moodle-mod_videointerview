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
 * answer.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Student answer page.
 */

use mod_videointerview\interview_manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$questionid = required_param('questionid', PARAM_INT);
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:view', $context);
$attempt = $DB->get_record('videointerview_attempts', ['id' => $attemptid, 'videointerviewid' => $activity->id], '*', MUST_EXIST);
if ((int)$attempt->userid !== (int)$USER->id || $attempt->status !== 'inprogress') {
    throw new moodle_exception('invalidattempt', 'videointerview');
}
$question = $DB->get_record('videointerview_questions',
    ['id' => $questionid, 'videointerviewid' => $activity->id], '*', MUST_EXIST);
if (!interview_manager::question_is_applicable($question, $attempt->id)) {
    throw new moodle_exception('invalidquestion', 'videointerview');
}
$response = interview_manager::get_or_create_response($attempt->id, $question->id);
if (interview_manager::response_is_answered($response) && empty($activity->allowreview)) {
    $next = interview_manager::get_next_question($activity->id, $attempt->id);
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
$allowedtypes = array_filter(explode(',', $question->responsetypes));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $selected = optional_param('responsetype', 'text', PARAM_ALPHA);
    if (!in_array($selected, $allowedtypes, true)) {
        $error = get_string('invaliddata', 'error');
    }
    $expired = $question->timelimit > 0 && $response->timeanswered <= 0 && time() > ($response->timestarted + $question->timelimit);
    if (!$error && $expired) {
        $error = get_string('timeexpired', 'videointerview');
    }
    $storedtype = $selected === 'recordvideo' ? 'video' : $selected;
    $text = optional_param('textresponse', '', PARAM_RAW);
    $hasnewfile = !empty($_FILES['mediafile']) && (int)$_FILES['mediafile']['error'] === UPLOAD_ERR_OK;
    $existingmedia = false;
    if (in_array($storedtype, ['audio', 'video'], true)) {
        $area = $storedtype === 'audio' ? 'responseaudio' : 'responsevideo';
        $existingmedia = (bool)get_file_storage()->get_area_files($context->id,
            'mod_videointerview', $area, $response->id, 'id', false);
    }
    if (!$error && $storedtype === 'text' && $question->required && trim(strip_tags($text)) === '') {
        $error = get_string('responsemissing', 'videointerview');
    }
    if (!$error && in_array($storedtype, ['audio', 'video'], true) && $question->required && !$hasnewfile && !$existingmedia) {
        $error = get_string('responsemissing', 'videointerview');
    }
    if (!$error && $hasnewfile) {
        $tmp = $_FILES['mediafile']['tmp_name'];
        $mimetype = mime_content_type($tmp) ?: (string)$_FILES['mediafile']['type'];
        $valid = $storedtype === 'audio' ? str_starts_with($mimetype, 'audio/') : str_starts_with($mimetype, 'video/');
        if (!$valid) {
            $error = get_string('invaliddata', 'error');
        } else if ((int)$_FILES['mediafile']['size'] > get_max_upload_file_size()) {
            $error = get_string('uploadedfiletoobig', 'error');
        }
    }
    if (!$error) {
        $now = time();
        $response->responsetype = $storedtype;
        $response->textresponse = $storedtype === 'text' ? $text : '';
        $response->textformat = FORMAT_HTML;
        $response->timeanswered = $now;
        $response->durationseconds = max(0, $now - (int)$response->timestarted);
        $response->timemodified = $now;
        $DB->update_record('videointerview_responses', $response);
        if ($storedtype === 'text') {
            get_file_storage()->delete_area_files($context->id, 'mod_videointerview', 'responseaudio', $response->id);
            get_file_storage()->delete_area_files($context->id, 'mod_videointerview', 'responsevideo', $response->id);
        } else if ($hasnewfile) {
            $area = $storedtype === 'audio' ? 'responseaudio' : 'responsevideo';
            get_file_storage()->delete_area_files($context->id, 'mod_videointerview', 'responseaudio', $response->id);
            get_file_storage()->delete_area_files($context->id, 'mod_videointerview', 'responsevideo', $response->id);
            $filename = clean_param($_FILES['mediafile']['name'], PARAM_FILE);
            if ($filename === '') {
                $filename = $storedtype . '-' . $response->id . ($storedtype === 'audio' ? '.webm' : '.webm');
            }
            get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'mod_videointerview',
                'filearea' => $area,
                'itemid' => $response->id,
                'filepath' => '/',
                'filename' => $filename,
            ], $_FILES['mediafile']['tmp_name']);
        }
        $attempt->timemodified = $now;
        $DB->update_record('videointerview_attempts', $attempt);
        $next = interview_manager::get_next_question($activity->id, $attempt->id);
        if ($next) {
            redirect(new moodle_url('/mod/videointerview/answer.php',
                ['id' => $cm->id, 'attemptid' => $attempt->id, 'questionid' => $next->id]));
        }
        if (empty($activity->allowreview)) {
            redirect(new moodle_url('/mod/videointerview/submit.php', [
                'id' => $cm->id,
                'attemptid' => $attempt->id,
                'sesskey' => sesskey(),
            ]));
        }
        redirect(new moodle_url('/mod/videointerview/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]));
    }
}

$player = \mod_videointerview\video_renderer::render($question, $context, $attempt->id);
$options = [];
foreach ($allowedtypes as $type) {
    $key = $type === 'recordvideo' ? 'responsetype_recordvideo' : 'responsetype_' . $type;
    $options[] = [
        'value' => $type,
        'label' => get_string($key, 'videointerview'),
        'selected' => ($response->responsetype === $type || ($type === 'recordvideo' && $response->responsetype === 'video')),
    ];
}
$remaining = 0;
if ($question->timelimit > 0 && $response->timeanswered <= 0) {
    $remaining = max(0, ($response->timestarted + $question->timelimit) - time());
}
$data = [
    'title' => format_string($question->title),
    'prompthtml' => format_text($question->prompt, $question->promptformat, ['context' => $context]),
    'playerhtml' => $player,
    'hasplayer' => $player !== '',
    'formaction' => (new moodle_url('/mod/videointerview/answer.php',
        ['id' => $cm->id, 'attemptid' => $attempt->id, 'questionid' => $question->id]))->out(false),
    'sesskey' => sesskey(),
    'types' => $options,
    'textresponse' => $response->responsetype === 'text' ? (string)$response->textresponse : '',
    'error' => $error,
    'haserror' => $error !== '',
    'timed' => $question->timelimit > 0 && $response->timeanswered <= 0,
    'remaining' => $remaining,
    'required' => (bool)$question->required,
    'canreview' => !empty($activity->allowreview),
    'reviewurl' => (new moodle_url('/mod/videointerview/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]))->out(false),
];
$PAGE->set_url('/mod/videointerview/answer.php', ['id' => $cm->id, 'attemptid' => $attempt->id, 'questionid' => $question->id]);
$PAGE->set_title(format_string($question->title));
$PAGE->set_heading($course->fullname);
$PAGE->requires->strings_for_js(['recordingunsupported', 'recordingready'], 'videointerview');
$PAGE->requires->js_call_amd('mod_videointerview/interview', 'init', [$cm->id]);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videointerview/answer', $data);
echo $OUTPUT->footer();
