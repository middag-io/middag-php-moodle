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
 * Per-area Moodle stubs for Middag\Moodle\Http\Client\HttpClientAdapter coverage.
 *
 * The adapter constructor seeds its default User-Agent header from Moodle's
 * core_useragent::get_moodlebot_useragent(). moodle-stubs declare that class for
 * PHPStan only; it is not autoloadable in the test runtime, so this file provides
 * a deterministic stand-in. Guarded with !class_exists so the file stays additive
 * and order-independent; the filename is unique to this class per the
 * parallel-writer doctrine.
 */

// Stub: core_useragent — only get_moodlebot_useragent() is consumed by the
// adapter (as the default User-Agent header). Returns a deterministic value so
// tests can assert the header the adapter attaches to outgoing requests.
if (!class_exists('core_useragent', false)) {
    class core_useragent
    {
        public static function get_moodlebot_useragent(): string
        {
            return 'MoodleBot/1.0 (+https://moodle.test)';
        }
    }
}
