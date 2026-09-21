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
 * question_form.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videointerview\form;

use moodleform;

/**
 * Form used to create and edit interview questions.
 */
class question_form extends moodleform {
    /**
     * Defines fields.
     */
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata;
        $mform->addElement('hidden', 'cmid', $custom['cmid']);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'questionid', $custom['questionid'] ?? 0);
        $mform->setType('questionid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('questiontitle', 'videointerview'), ['size' => 70]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addElement('editor', 'prompt_editor', get_string('prompt', 'videointerview'), null,
            ['maxfiles' => 0, 'noclean' => false]);

        $sources = [
            'upload' => get_string('sourceupload', 'videointerview'),
            'url' => get_string('sourceurl', 'videointerview'),
            'youtube' => get_string('sourceyoutube', 'videointerview'),
            'vimeo' => get_string('sourcevimeo', 'videointerview'),
        ];
        $mform->addElement('select', 'videosource', get_string('videosource', 'videointerview'), $sources);
        $mform->setDefault('videosource', 'upload');
        $mform->addElement('url', 'videourl', get_string('videourl', 'videointerview'), ['size' => 70], ['usefilepicker' => false]);
        $mform->setType('videourl', PARAM_URL);
        $mform->hideIf('videourl', 'videosource', 'eq', 'upload');
        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'videointerview'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['video'],
        ]);
        $mform->hideIf('videofile', 'videosource', 'neq', 'upload');

        $types = [
            'text' => get_string('responsetype_text', 'videointerview'),
            'audio' => get_string('responsetype_audio', 'videointerview'),
            'video' => get_string('responsetype_video', 'videointerview'),
            'recordvideo' => get_string('responsetype_recordvideo', 'videointerview'),
        ];
        $select = $mform->addElement('select', 'responsetypes', get_string('responsetypes', 'videointerview'), $types);
        $select->setMultiple(true);
        $mform->setDefault('responsetypes', ['text']);

        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'videointerview'), ['optional' => true]);
        $mform->addHelpButton('timelimit', 'timelimit', 'videointerview');
        $mform->addElement('selectyesno', 'required', get_string('requiredquestion', 'videointerview'));
        $mform->setDefault('required', 1);

        $mform->addElement('header', 'conditionheader', get_string('conditionheader', 'videointerview'));
        $questions = [0 => get_string('conditionalways', 'videointerview')] + ($custom['conditionquestions'] ?? []);
        $mform->addElement('select', 'conditionquestionid', get_string('conditionquestion', 'videointerview'), $questions);
        $operators = [
            'always' => get_string('conditionalways', 'videointerview'),
            'equals' => get_string('conditionequals', 'videointerview'),
            'contains' => get_string('conditioncontains', 'videointerview'),
            'notempty' => get_string('conditionnotempty', 'videointerview'),
            'empty' => get_string('conditionempty', 'videointerview'),
        ];
        $mform->addElement('select', 'conditionoperator', get_string('conditionoperator', 'videointerview'), $operators);
        $mform->setDefault('conditionoperator', 'always');
        $mform->hideIf('conditionoperator', 'conditionquestionid', 'eq', 0);
        $mform->addElement('text', 'conditionvalue', get_string('conditionvalue', 'videointerview'), ['size' => 60]);
        $mform->setType('conditionvalue', PARAM_TEXT);
        $mform->hideIf('conditionvalue', 'conditionoperator', 'in', ['always', 'notempty', 'empty']);
        $this->add_action_buttons();
    }

    /**
     * Validates values.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['responsetypes'])) {
            $errors['responsetypes'] = get_string('required');
        }
        $source = (string)($data['videosource'] ?? '');
        if ($source === 'upload') {
            $draftid = (int)($data['videofile'] ?? 0);
            $info = $draftid ? file_get_draft_area_info($draftid) : ['filecount' => 0];
            if (empty($info['filecount'])) {
                $errors['videofile'] = get_string('required');
            }
        } else {
            $url = trim((string)($data['videourl'] ?? ''));
            if (!filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                $errors['videourl'] = get_string('invalidurl', 'videointerview');
            } else if ($source === 'url') {
                $extension = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                if (!in_array($extension, ['mp4', 'webm', 'ogv', 'm4v', 'mov'], true)) {
                    $errors['videourl'] = get_string('invalidvideourl', 'videointerview');
                }
            } else if ($source === 'youtube' &&
                !preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/))[A-Za-z0-9_-]{6,}~i', $url)) {
                $errors['videourl'] = get_string('invalidyoutubeurl', 'videointerview');
            } else if ($source === 'vimeo' && !preg_match('~vimeo\.com/(?:video/)?[0-9]+~i', $url)) {
                $errors['videourl'] = get_string('invalidvimeourl', 'videointerview');
            }
        }
        return $errors;
    }
}
