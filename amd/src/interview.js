// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * interview.js
 *
 * @package   mod_videointerview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {

    /** Formats seconds as mm:ss or hh:mm:ss. */
    const formatTime = (seconds) => {
        const value = Math.max(0, Number(seconds) || 0);
        const hours = Math.floor(value / 3600);
        const minutes = Math.floor((value % 3600) / 60);
        const secs = Math.floor(value % 60);
        const parts = [
            String(minutes).padStart(2, '0'),
            String(secs).padStart(2, '0')
        ];

        if (hours > 0) {
            parts.unshift(String(hours).padStart(2, '0'));
        }

        return parts.join(':');
    };

    /** Initialises countdown. */
    const initTimer = () => {
        const timer = document.querySelector(
            '[data-videointerview-timer]'
        );

        if (!timer) {
            return;
        }

        let seconds = Number(timer.dataset.seconds || 0);

        const output = timer.querySelector(
            '[data-timer-value]'
        );

        const render = () => {
            if (output) {
                output.textContent = formatTime(seconds);
            }
        };

        render();

        const handle = window.setInterval(() => {
            seconds = Math.max(0, seconds - 1);
            render();

            if (seconds <= 0) {
                window.clearInterval(handle);

                const submit = document.querySelector(
                    '[data-videointerview-answer-form] button[type="submit"]'
                );

                if (submit) {
                    submit.disabled = true;
                }
            }
        }, 1000);
    };

    /** Initialises HTML5 video tracking and resume. */
    const initPlayers = (cmid) => {
        document.querySelectorAll(
            '.mod-videointerview-player'
        ).forEach((player) => {

            const questionid = Number(
                player.dataset.questionid || 0
            );

            const attemptid = Number(
                player.dataset.attemptid || 0
            );

            const resume = Number(
                player.dataset.resume || 0
            );

            let lastsent = 0;

            player.addEventListener('loadedmetadata', () => {
                if (
                    resume > 0 &&
                    Number.isFinite(player.duration) &&
                    resume < player.duration - 2
                ) {
                    player.currentTime = resume;
                }
            }, {once: true});

            const send = () => {
                if (
                    !questionid ||
                    !attemptid ||
                    !Number.isFinite(player.duration) ||
                    player.duration <= 0
                ) {
                    return;
                }

                const now = Date.now();

                if (
                    now - lastsent < 4000 &&
                    !player.paused &&
                    !player.ended
                ) {
                    return;
                }

                lastsent = now;

                Ajax.call([{
                    methodname: 'mod_videointerview_update_progress',
                    args: {
                        cmid: cmid,
                        attemptid: attemptid,
                        questionid: questionid,
                        position: player.currentTime || 0,
                        duration: player.duration || 0
                    }
                }])[0].catch(() => {
                });
            };

            player.addEventListener('timeupdate', send);
            player.addEventListener('pause', send);
            player.addEventListener('ended', send);
        });
    };

    /** Shows fields that match selected answer type. */
    const updateResponseFields = () => {
        const select = document.querySelector(
            '[data-response-type]'
        );

        if (!select) {
            return;
        }

        const text = document.querySelector(
            '[data-text-response]'
        );

        const media = document.querySelector(
            '[data-media-response]'
        );

        const audioButton = document.querySelector(
            '[data-record-audio]'
        );

        const videoButton = document.querySelector(
            '[data-record-video]'
        );

        const type = select.value;

        if (text) {
            text.classList.toggle(
                'd-none',
                type !== 'text'
            );
        }

        if (media) {
            media.classList.toggle(
                'd-none',
                type === 'text'
            );
        }

        if (audioButton) {
            audioButton.classList.toggle(
                'd-none',
                type !== 'audio'
            );
        }

        if (videoButton) {
            videoButton.classList.toggle(
                'd-none',
                type !== 'recordvideo'
            );
        }

        const input = document.querySelector(
            '[data-media-file]'
        );

        if (input) {
            input.accept = type === 'audio'
                ? 'audio/*'
                : 'video/*';
        }
    };

    /** Starts browser recording and places the resulting file in the upload input. */
    const initRecorder = () => {
        const form = document.querySelector(
            '[data-videointerview-answer-form]'
        );

        if (!form) {
            return;
        }

        const select = form.querySelector(
            '[data-response-type]'
        );

        const fileInput = form.querySelector(
            '[data-media-file]'
        );

        const audioButton = form.querySelector(
            '[data-record-audio]'
        );

        const videoButton = form.querySelector(
            '[data-record-video]'
        );

        const stopButton = form.querySelector(
            '[data-stop-recording]'
        );

        const status = form.querySelector(
            '[data-recording-status]'
        );

        const preview = form.querySelector(
            '[data-recording-preview]'
        );

        if (select) {
            select.addEventListener(
                'change',
                updateResponseFields
            );

            updateResponseFields();
        }

        if (
            !navigator.mediaDevices ||
            !window.MediaRecorder ||
            !fileInput ||
            !stopButton
        ) {
            if (audioButton) {
                audioButton.disabled = true;
            }

            if (videoButton) {
                videoButton.disabled = true;
            }

            if (status) {
                status.textContent = M.util.get_string(
                    'recordingunsupported',
                    'videointerview'
                );
            }

            return;
        }

        let recorder = null;
        let stream = null;
        let chunks = [];
        let mode = 'audio';

        const start = async (kind) => {
            mode = kind;
            chunks = [];

            stream = await navigator.mediaDevices.getUserMedia(
                kind === 'audio'
                    ? {
                        audio: true
                    }
                    : {
                        audio: true,
                        video: true
                    }
            );

            recorder = new MediaRecorder(stream);

            recorder.addEventListener(
                'dataavailable',
                (event) => {
                    if (
                        event.data &&
                        event.data.size > 0
                    ) {
                        chunks.push(event.data);
                    }
                }
            );

            recorder.addEventListener('stop', () => {
                const mime = recorder.mimeType || (
                    mode === 'audio'
                        ? 'audio/webm'
                        : 'video/webm'
                );

                const blob = new Blob(
                    chunks,
                    {type: mime}
                );

                const extension = mime.includes('mp4')
                    ? 'mp4'
                    : 'webm';

                const filename =
                    `${mode}-${Date.now()}.${extension}`;

                const file = new File(
                    [blob],
                    filename,
                    {type: mime}
                );

                const transfer = new DataTransfer();

                transfer.items.add(file);
                fileInput.files = transfer.files;

                if (preview) {
                    preview.src = URL.createObjectURL(blob);
                    preview.classList.remove('d-none');
                }

                if (status) {
                    status.textContent = M.util.get_string(
                        'recordingready',
                        'videointerview'
                    );
                }

                if (stream) {
                    stream.getTracks().forEach(
                        (track) => track.stop()
                    );
                }

                stopButton.classList.add('d-none');

                if (audioButton) {
                    audioButton.disabled = false;
                }

                if (videoButton) {
                    videoButton.disabled = false;
                }
            });

            recorder.start();

            stopButton.classList.remove('d-none');

            if (audioButton) {
                audioButton.disabled = true;
            }

            if (videoButton) {
                videoButton.disabled = true;
            }

            if (status) {
                status.textContent = mode === 'audio'
                    ? '● Audio'
                    : '● Video';
            }
        };

        if (audioButton) {
            audioButton.addEventListener('click', () => {
                start('audio').catch(() => {
                    if (status) {
                        status.textContent = M.util.get_string(
                            'recordingunsupported',
                            'videointerview'
                        );
                    }
                });
            });
        }

        if (videoButton) {
            videoButton.addEventListener('click', () => {
                start('video').catch(() => {
                    if (status) {
                        status.textContent = M.util.get_string(
                            'recordingunsupported',
                            'videointerview'
                        );
                    }
                });
            });
        }

        stopButton.addEventListener('click', () => {
            if (
                recorder &&
                recorder.state !== 'inactive'
            ) {
                recorder.stop();
            }
        });
    };

    /** Adds confirmation to destructive/final links. */
    const initConfirm = () => {
        document.querySelectorAll(
            '[data-confirm]'
        ).forEach((element) => {

            element.addEventListener(
                'click',
                (event) => {

                    const message =
                        element.dataset.confirm;

                    if (
                        message &&
                        !window.confirm(message)
                    ) {
                        event.preventDefault();
                    }
                }
            );
        });
    };

    /** Initialises the student page. */
    const init = (cmid) => {
        initTimer();
        initPlayers(cmid);
        initRecorder();
        initConfirm();
    };

    return {
        init: init,
        initConfirm: initConfirm
    };
});
