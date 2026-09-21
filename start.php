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
 * start.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Starts or resumes an interview attempt.
 */
require('../../config.php');
$id = required_param('id', PARAM_INT);
require_sesskey();
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:view', $context);
$attempt = \mod_videointerview\interview_manager::start_attempt($activity, $USER->id);
$question = \mod_videointerview\interview_manager::get_next_question($activity->id, $attempt->id);
if ($question) {
    redirect(new moodle_url('/mod/videointerview/answer.php',
        ['id' => $cm->id, 'attemptid' => $attempt->id, 'questionid' => $question->id]));
}
redirect(new moodle_url('/mod/videointerview/review.php', ['id' => $cm->id, 'attemptid' => $attempt->id]));
