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

namespace mod_videointerview;

use context_module;
use moodle_url;
use stdClass;

/**
 * Domain service for interview attempts, conditional questions, and grading.
 *
 * @package mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class interview_manager {
    /**
     * Creates the default criteria on a new activity.
     */
    public static function ensure_default_criteria(int $interviewid): void {
        global $DB;
        if ($DB->record_exists('videointerview_criteria', ['videointerviewid' => $interviewid])) {
            return;
        }
        $keys = ['clarity', 'content', 'argumentation', 'communication', 'vocabulary', 'posture'];
        $now = time();
        foreach ($keys as $index => $key) {
            $DB->insert_record('videointerview_criteria', (object)[
                'videointerviewid' => $interviewid,
                'sortorder' => ($index + 1) * 10,
                'name' => get_string('criterion_' . $key, 'videointerview'),
                'description' => '',
                'maxscore' => 10,
                'weight' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Returns the current in-progress attempt, if any.
     */
    public static function get_current_attempt(int $interviewid, int $userid): ?stdClass {
        global $DB;
        $record = $DB->get_record('videointerview_attempts', [
            'videointerviewid' => $interviewid,
            'userid' => $userid,
            'status' => 'inprogress',
        ], '*', IGNORE_MULTIPLE);
        return $record ?: null;
    }

    /**
     * Returns the latest attempt.
     */
    public static function get_latest_attempt(int $interviewid, int $userid): ?stdClass {
        global $DB;
        $records = $DB->get_records('videointerview_attempts', [
            'videointerviewid' => $interviewid,
            'userid' => $userid,
        ], 'attemptno DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Checks whether another attempt is allowed.
     */
    public static function can_start_attempt(stdClass $activity, int $userid): bool {
        global $DB;
        if ((int)$activity->maxattempts === 0) {
            return true;
        }
        $count = $DB->count_records('videointerview_attempts', [
            'videointerviewid' => $activity->id,
            'userid' => $userid,
        ]);
        return $count < (int)$activity->maxattempts;
    }

    /**
     * Starts a new attempt.
     */
    public static function start_attempt(stdClass $activity, int $userid): stdClass {
        global $DB;
        $current = self::get_current_attempt($activity->id, $userid);
        if ($current) {
            return $current;
        }
        if (!self::can_start_attempt($activity, $userid)) {
            throw new \moodle_exception('attemptlimitreached', 'videointerview');
        }
        $attemptno = 1 + (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(attemptno), 0)
                       FROM {videointerview_attempts}
                      WHERE videointerviewid = :id
                        AND userid = :userid',
                ['id' => $activity->id, 'userid' => $userid]
            );
        $now = time();
        $record = (object)[
            'videointerviewid' => $activity->id,
            'userid' => $userid,
            'attemptno' => $attemptno,
            'status' => 'inprogress',
            'timestarted' => $now,
            'timesubmitted' => 0,
            'timemodified' => $now,
            'grade' => null,
            'teacherfeedback' => '',
            'feedbackformat' => FORMAT_HTML,
        ];
        $record->id = $DB->insert_record('videointerview_attempts', $record);
        return $record;
    }

    /**
     * Returns questions ordered by position.
     */
    public static function get_questions(int $interviewid): array {
        global $DB;
        return $DB->get_records('videointerview_questions', ['videointerviewid' => $interviewid], 'position ASC, id ASC');
    }

    /**
     * Tests whether a question should be shown for the attempt.
     */
    public static function question_is_applicable(stdClass $question, int $attemptid): bool {
        global $DB;
        if (empty($question->conditionquestionid) || $question->conditionoperator === 'always') {
            return true;
        }
        $response = $DB->get_record('videointerview_responses', [
            'attemptid' => $attemptid,
            'questionid' => $question->conditionquestionid,
        ]);
        $hasresponse = $response && self::response_is_answered($response);
        $operator = (string)$question->conditionoperator;
        if ($operator === 'notempty') {
            return $hasresponse;
        }
        if ($operator === 'empty') {
            return !$hasresponse;
        }
        if (!$hasresponse || $response->responsetype !== 'text') {
            return false;
        }
        $actual = trim((string)$response->textresponse);
        $expected = trim((string)$question->conditionvalue);
        if ($operator === 'equals') {
            return core_text::strtolower($actual) === core_text::strtolower($expected);
        }
        if ($operator === 'contains') {
            return core_text::strpos(core_text::strtolower($actual), core_text::strtolower($expected)) !== false;
        }
        return true;
    }

    /**
     * Returns applicable questions for an attempt.
     */
    public static function get_applicable_questions(int $interviewid, int $attemptid): array {
        return array_filter(self::get_questions($interviewid), static function (stdClass $question) use ($attemptid): bool {
            return self::question_is_applicable($question, $attemptid);
        });
    }

    /**
     * Returns the next applicable unanswered question.
     */
    public static function get_next_question(int $interviewid, int $attemptid): ?stdClass {
        global $DB;
        foreach (self::get_questions($interviewid) as $question) {
            if (!self::question_is_applicable($question, $attemptid)) {
                continue;
            }
            $response = $DB->get_record('videointerview_responses', [
                'attemptid' => $attemptid,
                'questionid' => $question->id,
            ]);
            if (!$response || (int)$response->timeanswered <= 0) {
                return $question;
            }
        }
        return null;
    }

    /**
     * Returns or creates the response row so the time limit starts on first open.
     */
    public static function get_or_create_response(int $attemptid, int $questionid): stdClass {
        global $DB;
        $response = $DB->get_record('videointerview_responses', [
            'attemptid' => $attemptid,
            'questionid' => $questionid,
        ]);
        if ($response) {
            return $response;
        }
        $now = time();
        $response = (object)[
            'attemptid' => $attemptid,
            'questionid' => $questionid,
            'responsetype' => 'text',
            'textresponse' => '',
            'textformat' => FORMAT_PLAIN,
            'timestarted' => $now,
            'timeanswered' => 0,
            'durationseconds' => 0,
            'timemodified' => $now,
        ];
        $response->id = $DB->insert_record('videointerview_responses', $response);
        return $response;
    }

    /**
     * Determines whether the response contains submitted content.
     */
    public static function response_is_answered(stdClass $response): bool {
        if ((int)$response->timeanswered <= 0) {
            return false;
        }
        if ($response->responsetype === 'text') {
            return trim((string)$response->textresponse) !== '';
        }
        return true;
    }

    /**
     * Returns an answer summary suitable for tables.
     */
    public static function response_summary(?stdClass $response, context_module $context): string {
        if (!$response || !self::response_is_answered($response)) {
            return '-';
        }
        if ($response->responsetype === 'text') {
            return format_text((string)$response->textresponse, (int)$response->textformat, [
                'context' => $context,
                'para' => true,
            ]);
        }
        $area = $response->responsetype === 'audio' ? 'responseaudio' : 'responsevideo';
        $files = get_file_storage()->get_area_files($context->id, 'mod_videointerview', $area, $response->id, 'filename', false);
        if (!$files) {
            return get_string('response', 'videointerview');
        }
        $file = reset($files);
        $url = moodle_url::make_pluginfile_url($context->id, 'mod_videointerview', $area, $response->id,
            $file->get_filepath(), $file->get_filename());
        if ($response->responsetype === 'audio') {
            return \html_writer::tag('audio', '', [
                'controls' => 'controls',
                'preload' => 'metadata',
                'src' => $url->out(false),
                'class' => 'w-100',
            ]);
        }
        return \html_writer::tag('video', '', [
            'controls' => 'controls',
            'preload' => 'metadata',
            'src' => $url->out(false),
            'class' => 'w-100 videointerview-response-video',
        ]);
    }

    /**
     * Calculates and persists an attempt grade from criterion scores.
     */
    public static function recalculate_grade(stdClass $activity, int $attemptid): ?float {
        global $DB;
        $criteria = $DB->get_records('videointerview_criteria', ['videointerviewid' => $activity->id], 'sortorder, id');
        if (!$criteria) {
            return null;
        }
        $grades = $DB->get_records('videointerview_grades', ['attemptid' => $attemptid]);
        $bycriterion = [];
        foreach ($grades as $grade) {
            $bycriterion[$grade->criterionid] = $grade;
        }
        $weighted = 0.0;
        $weights = 0.0;
        foreach ($criteria as $criterion) {
            if (!isset($bycriterion[$criterion->id])) {
                continue;
            }
            $maxscore = max(0.01, (float)$criterion->maxscore);
            $weight = max(0.0, (float)$criterion->weight);
            $weighted += min($maxscore, max(0.0, (float)$bycriterion[$criterion->id]->score)) / $maxscore * $weight;
            $weights += $weight;
        }
        if ($weights <= 0) {
            return null;
        }
        $final = $weighted / $weights * max(0.0, (float)$activity->grade);
        $attempt = $DB->get_record('videointerview_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $attempt->grade = $final;
        $attempt->status = 'graded';
        $attempt->timemodified = time();
        $DB->update_record('videointerview_attempts', $attempt);
        \videointerview_update_grades($activity, (int)$attempt->userid, false);
        return $final;
    }
}
