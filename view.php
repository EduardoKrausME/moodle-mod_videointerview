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
 * view.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Main activity view.
 */
require('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:view', $context);

$PAGE->set_url('/mod/videointerview/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading($course->fullname);

$questioncount = $DB->count_records('videointerview_questions', ['videointerviewid' => $activity->id]);
$current = \mod_videointerview\interview_manager::get_current_attempt($activity->id, $USER->id);
$latest = \mod_videointerview\interview_manager::get_latest_attempt($activity->id, $USER->id);
$canstart = $questioncount > 0 && \mod_videointerview\interview_manager::can_start_attempt($activity, $USER->id);

$data = [
    'title' => format_string($activity->name),
    'intro' => format_module_intro('videointerview', $activity, $cm->id),
    'noquestions' => $questioncount === 0,
    'hascurrent' => (bool)$current,
    'currentattempt' => $current ? $current->attemptno : 0,
    'continueurl' => $current ? (new moodle_url('/mod/videointerview/start.php',
        ['id' => $cm->id, 'sesskey' => sesskey()]))->out(false) : '',
    'canstart' => !$current && $canstart,
    'starturl' => (new moodle_url('/mod/videointerview/start.php', ['id' => $cm->id, 'sesskey' => sesskey()]))->out(false),
    'limitreached' => !$current && !$canstart && $questioncount > 0,
    'haslatest' => (bool)$latest,
    'latestattempt' => $latest ? $latest->attemptno : 0,
    'lateststatus' => $latest ? get_string('attemptstatus_' . $latest->status, 'videointerview') : '',
    'latestgrade' => $latest && $latest->grade !== null ?
        format_float($latest->grade, 2) . ' / ' . format_float($activity->grade, 2) : '',
    'reviewurl' => $latest ? (new moodle_url('/mod/videointerview/review.php',
        ['id' => $cm->id, 'attemptid' => $latest->id]))->out(false) : '',
    'canmanage' => has_capability('mod/videointerview:manage', $context),
    'manageurl' => (new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]))->out(false),
    'canreport' => has_capability('mod/videointerview:viewreport', $context),
    'reporturl' => (new moodle_url('/mod/videointerview/report.php', ['id' => $cm->id]))->out(false),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videointerview/view', $data);
echo $OUTPUT->footer();
