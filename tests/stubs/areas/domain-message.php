<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Moodle function/class stubs for the Domain\Message coverage area
 * (MessageService). Guarded with !function_exists / !class_exists so the file is
 * order-independent and purely additive (mirrors the support/* stubs). The
 * FORMAT_HTML constant, core\message\message, core_message\api, message_send(),
 * make_temp_directory(), email_to_user(), fullname() and html_to_text() the
 * service transitively touches are already provided by the shared support stubs
 * (msg-file.php, version-user.php, output-db.php) and the bootstrap; only the
 * two symbols missing there are added below.
 */

// get_site() — MessageService::prepareText() falls back to the site "course"
// record when no course is resolved. Returns $GLOBALS['__middag_test_site_course']
// (default a minimal front-page record with the id/fullname prepareText reads).
if (!function_exists('get_site')) {
    function get_site(): object
    {
        return $GLOBALS['__middag_test_site_course'] ?? (object) ['id' => 1, 'fullname' => 'Front page'];
    }
}

// $DB double whose get_record('course', …) yields the record placed in
// $GLOBALS['__middag_test_message_course_record'] (default false). CourseSupport::
// getCourse() uses $DB->get_record under the default IGNORE_MISSING strictness,
// so this lets the Message tests drive the "course found" vs "course null" arms
// without a Moodle runtime.
if (!class_exists('middag_test_message_database', false)) {
    class middag_test_message_database extends moodle_database
    {
        public function get_record($table, ?array $conditions = null, $fields = '*', $strictness = 0)
        {
            return $GLOBALS['__middag_test_message_course_record'] ?? false;
        }
    }
}
