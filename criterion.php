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
 * criterion.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Criterion editor.
 */
require('../../config.php');
require_once("{$CFG->libdir}/formslib.php");

$id = required_param('id', PARAM_INT);
$criterionid = optional_param('criterionid', 0, PARAM_INT);
$action = optional_param('action', 'edit', PARAM_ALPHA);
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('videointerview', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:manage', $context);
$criterion = $criterionid ? $DB->get_record('videointerview_criteria',
    ['id' => $criterionid, 'videointerviewid' => $activity->id], '*', MUST_EXIST) : null;
if ($action === 'delete' && $criterion) {
    require_sesskey();
    $DB->delete_records('videointerview_grades', ['criterionid' => $criterion->id]);
    $DB->delete_records('videointerview_criteria', ['id' => $criterion->id]);
    redirect(new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]));
}
$mform = new \mod_videointerview\form\criterion_form(null, ['cmid' => $cm->id, 'criterionid' => $criterionid]);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]));
}
if ($data = $mform->get_data()) {
    $now = time();
    $record = $criterion ?: new stdClass();
    $record->videointerviewid = $activity->id;
    if (!$criterion) {
        $record->sortorder = 10 +
            (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), 0) FROM {videointerview_criteria} WHERE videointerviewid = :id',
                ['id' => $activity->id]);
        $record->timecreated = $now;
    }
    $record->name = $data->name;
    $record->description = $data->description;
    $record->maxscore = $data->maxscore;
    $record->weight = $data->weight;
    $record->timemodified = $now;
    if ($criterion) {
        $DB->update_record('videointerview_criteria', $record);
    } else {
        $DB->insert_record('videointerview_criteria', $record);
    }
    redirect(new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]));
}
if ($criterion) {
    $mform->set_data($criterion);
}
$PAGE->set_url('/mod/videointerview/criterion.php', ['id' => $cm->id, 'criterionid' => $criterionid]);
$PAGE->set_title(get_string($criterion ? 'editcriterion' : 'addcriterion', 'videointerview'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($criterion ? 'editcriterion' : 'addcriterion', 'videointerview'));
$mform->display();
echo $OUTPUT->footer();
