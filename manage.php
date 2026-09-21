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
 * manage.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Teacher management page.
 */
require('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:manage', $context);

$PAGE->set_url('/mod/videointerview/manage.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading($course->fullname);

$questions = $DB->get_records('videointerview_questions', ['videointerviewid' => $activity->id], 'position ASC, id ASC');
$criteria = $DB->get_records('videointerview_criteria', ['videointerviewid' => $activity->id], 'sortorder ASC, id ASC');
$questionitems = [];
$count = count($questions);
$i = 0;
foreach ($questions as $question) {
    $i++;
    $types = array_filter(explode(',', $question->responsetypes));
    $typenames = [];
    foreach ($types as $type) {
        $key = $type === 'recordvideo' ? 'responsetype_recordvideo' : 'responsetype_' . $type;
        $typenames[] = get_string($key, 'videointerview');
    }
    $questionitems[] = [
        'title' => format_string($question->title),
        'position' => $i,
        'types' => implode(', ', $typenames),
        'timelimit' => $question->timelimit ? format_time($question->timelimit) : get_string('none'),
        'editurl' => (new moodle_url('/mod/videointerview/question.php',
            ['id' => $cm->id, 'questionid' => $question->id]))->out(false),
        'deleteurl' => (new moodle_url('/mod/videointerview/question.php',
            ['id' => $cm->id, 'questionid' => $question->id, 'action' => 'delete', 'sesskey' => sesskey()]))->out(false),
        'upurl' => $i > 1 ? (new moodle_url('/mod/videointerview/move.php',
            ['id' => $cm->id, 'questionid' => $question->id, 'direction' => 'up', 'sesskey' => sesskey()]))->out(false) : '',
        'downurl' => $i < $count ? (new moodle_url('/mod/videointerview/move.php',
            ['id' => $cm->id, 'questionid' => $question->id, 'direction' => 'down', 'sesskey' => sesskey()]))->out(false) : '',
    ];
}
$criterionitems = [];
foreach ($criteria as $criterion) {
    $criterionitems[] = [
        'name' => format_string($criterion->name),
        'maxscore' => format_float($criterion->maxscore, 2),
        'weight' => format_float($criterion->weight, 2),
        'editurl' => (new moodle_url('/mod/videointerview/criterion.php',
            ['id' => $cm->id, 'criterionid' => $criterion->id]))->out(false),
        'deleteurl' => (new moodle_url('/mod/videointerview/criterion.php',
            ['id' => $cm->id, 'criterionid' => $criterion->id, 'action' => 'delete', 'sesskey' => sesskey()]))->out(false),
    ];
}
$data = [
    'title' => format_string($activity->name),
    'questions' => $questionitems,
    'hasquestions' => !empty($questionitems),
    'criteria' => $criterionitems,
    'hascriteria' => !empty($criterionitems),
    'addquestionurl' => (new moodle_url('/mod/videointerview/question.php', ['id' => $cm->id]))->out(false),
    'addcriterionurl' => (new moodle_url('/mod/videointerview/criterion.php', ['id' => $cm->id]))->out(false),
    'viewurl' => (new moodle_url('/mod/videointerview/view.php', ['id' => $cm->id]))->out(false),
    'reporturl' => (new moodle_url('/mod/videointerview/report.php', ['id' => $cm->id]))->out(false),
];
$PAGE->requires->js_call_amd('mod_videointerview/interview', 'initConfirm');
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videointerview/manage', $data);
echo $OUTPUT->footer();
