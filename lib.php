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
 * Core callbacks for Video Interview.
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videointerview\interview_manager;

/**
 * Declares Moodle features supported by this activity.
 *
 * @param string $feature Feature constant.
 * @return bool|null
 */
function videointerview_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_ASSIGNMENT;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Adds an activity instance.
 *
 * @param stdClass $data Submitted activity data.
 * @param mod_videointerview_mod_form|null $mform Form instance.
 * @return int New instance id.
 */
function videointerview_add_instance(stdClass $data, ?mod_videointerview_mod_form $mform = null): int {
    global $DB;
    $now = time();
    $data->timecreated = $now;
    $data->timemodified = $now;
    $id = $DB->insert_record('videointerview', $data);
    $data->id = $id;
    interview_manager::ensure_default_criteria($id);
    videointerview_grade_item_update($data);
    return $id;
}

/**
 * Updates an activity instance.
 *
 * @param stdClass $data Submitted activity data.
 * @param mod_videointerview_mod_form|null $mform Form instance.
 * @return bool
 */
function videointerview_update_instance(stdClass $data, ?mod_videointerview_mod_form $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $result = $DB->update_record('videointerview', $data);
    videointerview_grade_item_update($data);
    videointerview_update_grades($data);
    return $result;
}

/**
 * Deletes an activity instance and its related data.
 *
 * @param int $id Instance id.
 * @return bool
 */
function videointerview_delete_instance(int $id): bool {
    global $DB;
    $activity = $DB->get_record('videointerview', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $cm = get_coursemodule_from_instance('videointerview', $id, $activity->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        get_file_storage()->delete_area_files($context->id, 'mod_videointerview');
    }

    $transaction = $DB->start_delegated_transaction();
    $attemptids = $DB->get_fieldset_select('videointerview_attempts', 'id', 'videointerviewid = :id', ['id' => $id]);
    if ($attemptids) {
        [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attempt');
        $DB->delete_records_select('videointerview_grades', "attemptid {$insql}", $params);
        $DB->delete_records_select('videointerview_progress', "attemptid {$insql}", $params);
        $DB->delete_records_select('videointerview_responses', "attemptid {$insql}", $params);
    }
    $DB->delete_records('videointerview_attempts', ['videointerviewid' => $id]);
    $DB->delete_records('videointerview_criteria', ['videointerviewid' => $id]);
    $DB->delete_records('videointerview_questions', ['videointerviewid' => $id]);
    $DB->delete_records('videointerview', ['id' => $id]);
    $transaction->allow_commit();
    videointerview_grade_item_delete($activity);
    return true;
}

/**
 * Serves protected question and response media files.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context_module $context Module context.
 * @param string $filearea File area.
 * @param array $args File path arguments.
 * @param bool $forcedownload Force download.
 * @param array $options Send-file options.
 * @return bool
 */
function mod_videointerview_pluginfile($course, $cm, $context, string $filearea, array $args,
                                       bool $forcedownload, array $options = []): bool {
    global $DB, $USER;
    if ($context->contextlevel !== CONTEXT_MODULE ||
        !in_array($filearea, ['questionvideo', 'responseaudio', 'responsevideo'], true)) {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/videointerview:view', $context);
    $itemid = (int)array_shift($args);
    if ($filearea === 'questionvideo') {
        $question = $DB->get_record('videointerview_questions', ['id' => $itemid], '*', MUST_EXIST);
        if ((int)$question->videointerviewid !== (int)$cm->instance) {
            return false;
        }
    } else {
        $response = $DB->get_record('videointerview_responses', ['id' => $itemid], '*', MUST_EXIST);
        $attempt = $DB->get_record('videointerview_attempts', ['id' => $response->attemptid], '*', MUST_EXIST);
        if ((int)$attempt->videointerviewid !== (int)$cm->instance) {
            return false;
        }
        if ((int)$attempt->userid !== (int)$USER->id &&
            !has_capability('mod/videointerview:grade', $context) &&
            !has_capability('mod/videointerview:viewreport', $context)) {
            return false;
        }
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file($context->id, 'mod_videointerview', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Returns file areas.
 *
 * @param stdClass $course Course record.
 * @param stdClass $cm Course module record.
 * @param context_module $context Module context.
 * @return array
 */
function videointerview_get_file_areas($course, $cm, $context): array {
    return [
        'questionvideo' => get_string('videofile', 'videointerview'),
        'responseaudio' => get_string('responsetype_audio', 'videointerview'),
        'responsevideo' => get_string('responsetype_video', 'videointerview'),
    ];
}

/**
 * Creates or updates the gradebook item.
 *
 * @param stdClass $activity Activity record.
 * @param array|null $grades Grade records.
 * @return int
 */
function videointerview_grade_item_update(stdClass $activity, ?array $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $item = [
        'itemname' => clean_param($activity->name, PARAM_NOTAGS),
        'gradetype' => (float)$activity->grade > 0 ? GRADE_TYPE_VALUE : GRADE_TYPE_NONE,
        'grademin' => 0,
        'grademax' => max(0, (float)$activity->grade),
    ];
    return grade_update('mod/videointerview', $activity->course, 'mod', 'videointerview', $activity->id, 0, $grades, $item);
}

/**
 * Pushes final attempt grades to the gradebook.
 *
 * @param stdClass $activity Activity record.
 * @param int $userid Optional user id.
 * @param bool $nullifnone Create null grade when absent.
 * @return void
 */
function videointerview_update_grades(stdClass $activity, int $userid = 0, bool $nullifnone = true): void {
    global $DB;
    $params = ['videointerviewid' => $activity->id];
    $usersql = '';
    if ($userid) {
        $usersql = ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    $sql = "SELECT a.*
              FROM {videointerview_attempts} a
              JOIN (SELECT userid, MAX(attemptno) AS attemptno
                      FROM {videointerview_attempts}
                     WHERE videointerviewid = :videointerviewid {$usersql}
                       AND grade IS NOT NULL
                  GROUP BY userid) latest
                ON latest.userid = a.userid AND latest.attemptno = a.attemptno
             WHERE a.videointerviewid = :activityid";
    $params['activityid'] = $activity->id;
    $records = $DB->get_records_sql($sql, $params);
    $grades = [];
    foreach ($records as $record) {
        $grades[$record->userid] = (object)['userid' => $record->userid, 'rawgrade' => $record->grade];
    }
    if (!$grades && $userid && $nullifnone) {
        $grades[$userid] = (object)['userid' => $userid, 'rawgrade' => null];
    }
    videointerview_grade_item_update($activity, $grades ?: null);
}

/**
 * Deletes the gradebook item.
 *
 * @param stdClass $activity Activity record.
 * @return int
 */
function videointerview_grade_item_delete(stdClass $activity): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/videointerview', $activity->course, 'mod', 'videointerview', $activity->id, 0, null, ['deleted' => 1]);
}

/**
 * Returns course module information.
 *
 * @param stdClass $cm Course module record.
 * @return cached_cm_info|null
 */
function videointerview_get_coursemodule_info(stdClass $cm): ?cached_cm_info {
    global $DB;
    $activity = $DB->get_record('videointerview', ['id' => $cm->instance], 'id,name,intro,introformat,completionsubmit');
    if (!$activity) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($cm->showdescription) {
        $info->content = format_module_intro('videointerview', $activity, $cm->id, false);
    }
    if ((int)$cm->completion === COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules'] = ['completionsubmit' => (bool)$activity->completionsubmit];
    }
    return $info;
}

/**
 * Describes active custom completion rules.
 *
 * @param cached_cm_info $cm Cached module info.
 * @return array
 */
function videointerview_get_completion_active_rule_descriptions(cached_cm_info $cm): array {
    if (!empty($cm->customdata['customcompletionrules']['completionsubmit'])) {
        return [get_string('completiondetail:submit', 'videointerview')];
    }
    return [];
}
