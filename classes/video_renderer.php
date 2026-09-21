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
 * video_renderer.php
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videointerview;

use context_module;
use html_writer;
use moodle_url;
use stdClass;

/**
 * Renders supported question video sources.
 */
class video_renderer {
    /**
     * Returns video HTML.
     */
    public static function render(stdClass $question, context_module $context, int $attemptid = 0): string {
        $source = (string)$question->videosource;
        if ($source === 'upload') {
            $files = get_file_storage()->get_area_files($context->id, 'mod_videointerview', 'questionvideo', $question->id,
                'filename', false);
            if (!$files) {
                return '';
            }
            $file = reset($files);
            $url = moodle_url::make_pluginfile_url($context->id, 'mod_videointerview', 'questionvideo', $question->id,
                $file->get_filepath(), $file->get_filename());
            return self::html5($url->out(false), $question->id, $attemptid);
        }
        $url = trim((string)$question->videourl);
        if ($url === '') {
            return '';
        }
        if ($source === 'youtube') {
            $id = self::youtube_id($url);
            if (!$id) {
                return '';
            }
            $src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?rel=0';
            return html_writer::tag('div', html_writer::tag('iframe', '', [
                'src' => $src,
                'title' => s($question->title),
                'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
                'allowfullscreen' => 'allowfullscreen',
                'loading' => 'lazy',
            ]), ['class' => 'mod-videointerview-embed']);
        }
        if ($source === 'vimeo') {
            $id = self::vimeo_id($url);
            if (!$id) {
                return '';
            }
            $src = 'https://player.vimeo.com/video/' . rawurlencode($id);
            return html_writer::tag('div', html_writer::tag('iframe', '', [
                'src' => $src,
                'title' => s($question->title),
                'allow' => 'autoplay; fullscreen; picture-in-picture',
                'allowfullscreen' => 'allowfullscreen',
                'loading' => 'lazy',
            ]), ['class' => 'mod-videointerview-embed']);
        }
        return self::html5($url, $question->id, $attemptid);
    }

    /**
     * Creates HTML5 video.
     */
    private static function html5(string $url, int $questionid, int $attemptid): string {
        global $DB;
        $resume = 0.0;
        if ($attemptid > 0) {
            $resume = (float)$DB->get_field('videointerview_progress', 'lastposition', [
                'attemptid' => $attemptid, 'questionid' => $questionid,
            ]);
        }
        return html_writer::tag('video', html_writer::empty_tag('source', ['src' => $url]), [
            'controls' => 'controls',
            'preload' => 'metadata',
            'class' => 'mod-videointerview-player',
            'data-questionid' => $questionid,
            'data-attemptid' => $attemptid,
            'data-resume' => $resume,
            'playsinline' => 'playsinline',
        ]);
    }

    /**
     * Extracts YouTube id.
     */
    private static function youtube_id(string $url): string {
        if (preg_match(
            '~(?:youtu\\.be/|youtube(?:-nocookie)?\\.com/(?:watch\\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~i',
            $url, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Extracts Vimeo id.
     */
    private static function vimeo_id(string $url): string {
        if (preg_match('~vimeo\\.com/(?:video/)?([0-9]+)~i', $url, $m)) {
            return $m[1];
        }
        return '';
    }
}
