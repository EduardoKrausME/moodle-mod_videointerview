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
 * Teacher report.
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:viewreport', $context);
$users = get_enrolled_users($context, 'mod/videointerview:view', 0,
    "u.id,u.firstname,u.lastname,u.email,u.picture,u.imagealt,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename",
    "u.lastname,u.firstname");
$rows = [];
foreach ($users as $user) {
    $attempt = \mod_videointerview\interview_manager::get_latest_attempt($activity->id, $user->id);
    $answered = 0;
    $total = $DB->count_records('videointerview_questions', ['videointerviewid' => $activity->id]);
    if ($attempt) {
        $total = count(\mod_videointerview\interview_manager::get_applicable_questions($activity->id, $attempt->id));
        $responses = $DB->get_records('videointerview_responses', ['attemptid' => $attempt->id]);
        foreach ($responses as $response) {
            if (\mod_videointerview\interview_manager::response_is_answered($response)) {
                $answered++;
            }
        }
    }
    $rows[] = [
        'fullname' => fullname($user),
        'started' => $attempt ? userdate($attempt->timestarted,
            get_string('strftimedatetimeshort', 'langconfig')) : get_string('notstarted', 'videointerview'),
        'answered' => $answered . ' / ' . $total,
        'submitted' => $attempt && $attempt->timesubmitted ?
            userdate($attempt->timesubmitted, get_string('strftimedatetimeshort', 'langconfig')) : '-',
        'evaluation' => $attempt ?
            get_string('attemptstatus_' . $attempt->status, 'videointerview') : get_string('notgraded', 'videointerview'),
        'grade' => $attempt && $attempt->grade !== null ? format_float($attempt->grade, 2) : '-',
        'hasattempt' => (bool)$attempt,
        'reviewurl' => $attempt ?
            (new moodle_url('/mod/videointerview/review.php',
                ['id' => $cm->id, 'attemptid' => $attempt->id]))->out(false) : '',
        'gradeurl' => $attempt && in_array($attempt->status, ['submitted', 'graded'], true) ?
            (new moodle_url('/mod/videointerview/grade.php', ['id' => $cm->id, 'attemptid' => $attempt->id]))->out(false) : '',
        'cangrade' => $attempt && in_array($attempt->status, ['submitted', 'graded'], true) &&
            has_capability('mod/videointerview:grade', $context),
    ];
}
$data = [
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'manageurl' => (new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]))->out(false),
];
$PAGE->set_url('/mod/videointerview/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('report', 'videointerview'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videointerview/report', $data);
echo $OUTPUT->footer();
