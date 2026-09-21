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
 * submit.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Final interview submission.
 */
require('../../config.php');
$id = required_param('id', PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
require_sesskey();
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
foreach (\mod_videointerview\interview_manager::get_applicable_questions($activity->id, $attempt->id) as $question) {
    if (!$question->required) {
        continue;
    }
    $response = $DB->get_record('videointerview_responses', ['attemptid' => $attempt->id, 'questionid' => $question->id]);
    if (!$response || !\mod_videointerview\interview_manager::response_is_answered($response)) {
        redirect(new moodle_url('/mod/videointerview/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]),
            get_string('responsemissing', 'videointerview'), null, \core\output\notification::NOTIFY_ERROR);
    }
}
$attempt->status = 'submitted';
$attempt->timesubmitted = time();
$attempt->timemodified = time();
$DB->update_record('videointerview_attempts', $attempt);
$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
}
redirect(new moodle_url('/mod/videointerview/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]));
