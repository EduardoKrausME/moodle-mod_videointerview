# Video Interview (`mod_videointerview`)

Moodle activity module for sequential or conditional video interviews. Teachers create recorded/video questions and
students answer with text, uploaded audio/video, or browser-recorded audio/video when MediaRecorder is available.

## Features

- Fixed or conditional question sequence.
- Prompt video sources: protected upload, direct HTML5 URL, YouTube, and Vimeo.
- Text, audio, video upload, and in-browser recording responses.
- Per-question response time limits.
- One, multiple, or unlimited interview attempts.
- Optional review/edit stage before final submission.
- Assessment criteria with score, weight, feedback, and gradebook integration.
- Student report with started/answered/submitted/evaluated/grade status.
- HTML5 video progress tracking and resume information.
- Moodle completion API, Privacy API, File API, backup/restore, and gradebook support.

## Requirements

Moodle 4.4 or newer and PHP supported by that Moodle release. Browser recording requires a secure context (HTTPS) and
MediaRecorder support.

## Installation

Copy the `videointerview` directory to `mod/videointerview`, then visit Site administration > Notifications.

## License

GNU GPL v3 or later.
