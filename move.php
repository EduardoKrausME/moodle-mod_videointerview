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
 * move.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Moves a question in the sequence.
 */
require('../../config.php');
$id = required_param('id', PARAM_INT);
$questionid = required_param('questionid', PARAM_INT);
$direction = required_param('direction', PARAM_ALPHA);
require_sesskey();
$cm = get_coursemodule_from_id('videointerview', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videointerview:manage', $context);
$question = $DB->get_record('videointerview_questions',
    ['id' => $questionid, 'videointerviewid' => $cm->instance], '*', MUST_EXIST);
$op = $direction === 'up' ? '<' : '>';
$order = $direction === 'up' ? 'position DESC, id DESC' : 'position ASC, id ASC';
$sql = "SELECT * FROM {videointerview_questions} WHERE videointerviewid = :id AND position {$op} :position ORDER BY {$order}";
$others = $DB->get_records_sql($sql, ['id' => $cm->instance, 'position' => $question->position], 0, 1);
if ($others) {
    $other = reset($others);
    $old = $question->position;
    $question->position = $other->position;
    $other->position = $old;
    $DB->update_record('videointerview_questions', $question);
    $DB->update_record('videointerview_questions', $other);
}
redirect(new moodle_url('/mod/videointerview/manage.php', ['id' => $cm->id]));
