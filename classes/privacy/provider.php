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
 * provider.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videointerview\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for Video Interview.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describes stored personal data.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videointerview_attempts', [
            'userid' => 'privacy:metadata:videointerview_attempts:userid',
            'timestarted' => 'privacy:metadata:videointerview_attempts:timestarted',
            'timesubmitted' => 'privacy:metadata:videointerview_attempts:timesubmitted',
            'grade' => 'privacy:metadata:videointerview_attempts:grade',
        ], 'privacy:metadata:videointerview_attempts');
        $collection->add_database_table('videointerview_responses', [
            'textresponse' => 'privacy:metadata:videointerview_responses:textresponse',
            'timeanswered' => 'privacy:metadata:videointerview_responses:timeanswered',
        ], 'privacy:metadata:videointerview_responses');
        $collection->add_database_table('videointerview_progress', [
            'lastposition' => 'privacy:metadata:videointerview_progress:lastposition',
            'percent' => 'privacy:metadata:videointerview_progress:percent',
        ], 'privacy:metadata:videointerview_progress');
        $collection->add_database_table('videointerview_grades', [
            'score' => 'privacy:metadata:videointerview_grades:score',
            'feedback' => 'privacy:metadata:videointerview_grades:feedback',
            'graderid' => 'privacy:metadata:videointerview_grades:graderid',
        ], 'privacy:metadata:videointerview_grades');
        return $collection;
    }

    /**
     * Finds module contexts containing data for a user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videointerview} v ON v.id = cm.instance
                  JOIN {videointerview_attempts} a ON a.videointerviewid = v.id
                 WHERE a.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videointerview',
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Exports user data from approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videointerview', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $attempts = $DB->get_records('videointerview_attempts', [
                'videointerviewid' => $cm->instance,
                'userid' => $userid,
            ], 'attemptno ASC');
            foreach ($attempts as $attempt) {
                $path = [get_string('privacy:path', 'videointerview'),
                    get_string('privacy:attempt', 'videointerview', $attempt->attemptno)];
                writer::with_context($context)->export_data($path, (object)[
                    'status' => $attempt->status,
                    'timestarted' => transform::datetime($attempt->timestarted),
                    'timesubmitted' => $attempt->timesubmitted ? transform::datetime($attempt->timesubmitted) : null,
                    'grade' => $attempt->grade,
                    'teacherfeedback' => $attempt->teacherfeedback,
                ]);
                $responses = $DB->get_records('videointerview_responses', ['attemptid' => $attempt->id]);
                $responseexport = [];
                foreach ($responses as $response) {
                    $responseexport[] = (object)[
                        'questionid' => $response->questionid,
                        'type' => $response->responsetype,
                        'text' => $response->textresponse,
                        'timeanswered' => $response->timeanswered ? transform::datetime($response->timeanswered) : null,
                    ];
                    writer::with_context($context)->export_area_files($path, 'mod_videointerview', 'responseaudio', $response->id);
                    writer::with_context($context)->export_area_files($path, 'mod_videointerview', 'responsevideo', $response->id);
                }
                writer::with_context($context)->export_data(array_merge($path, [get_string('response', 'videointerview')]),
                    (object)['responses' => $responseexport]);
                $progress = $DB->get_records('videointerview_progress', ['attemptid' => $attempt->id]);
                writer::with_context($context)->export_data(array_merge($path, ['progress']),
                    (object)['items' => array_values($progress)]);
                $grades = $DB->get_records('videointerview_grades', ['attemptid' => $attempt->id]);
                writer::with_context($context)->export_data(array_merge($path, [get_string('evaluation', 'videointerview')]),
                    (object)['criteria' => array_values($grades)]);
            }
        }
    }

    /**
     * Deletes all user data in one module context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videointerview', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $attemptids = $DB->get_fieldset_select('videointerview_attempts', 'id', 'videointerviewid = :id', ['id' => $cm->instance]);
        self::delete_attempts($context, $attemptids);
        $DB->delete_records('videointerview_attempts', ['videointerviewid' => $cm->instance]);
    }

    /**
     * Deletes data for one user from approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videointerview', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $attemptids = $DB->get_fieldset_select('videointerview_attempts', 'id',
                'videointerviewid = :id AND userid = :userid', ['id' => $cm->instance, 'userid' => $userid]);
            self::delete_attempts($context, $attemptids);
            $DB->delete_records('videointerview_attempts', ['videointerviewid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Deletes child records and response files for attempts.
     */
    private static function delete_attempts(context_module $context, array $attemptids): void {
        global $DB;
        if (!$attemptids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'attempt');
        $responses = $DB->get_records_select('videointerview_responses', "attemptid {$insql}", $params);
        $fs = get_file_storage();
        foreach ($responses as $response) {
            $fs->delete_area_files($context->id, 'mod_videointerview', 'responseaudio', $response->id);
            $fs->delete_area_files($context->id, 'mod_videointerview', 'responsevideo', $response->id);
        }
        $DB->delete_records_select('videointerview_grades', "attemptid {$insql}", $params);
        $DB->delete_records_select('videointerview_progress', "attemptid {$insql}", $params);
        $DB->delete_records_select('videointerview_responses', "attemptid {$insql}", $params);
    }
}
