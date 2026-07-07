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
 * Per-area Moodle stubs for Middag\Moodle\Shared\Util\Debug coverage.
 *
 * Debug::emit() routes messages through Moodle's mtrace() (cron/task log
 * visibility) and Debug::formatTrace() prefers Moodle's format_backtrace().
 * Neither global exists in the test runtime, so this file provides recording
 * stand-ins driven via $GLOBALS. Every definition is guarded with
 * !function_exists so the file stays additive and order-independent; the
 * filename is unique to this class per the parallel-writer doctrine.
 */

// Stub: mtrace() — records each emitted line in $GLOBALS['__middag_test_mtrace'].
// Mirrors Moodle's ($string, $eol, $bare) signature; Debug::emit() passes only
// the message.
if (!function_exists('mtrace')) {
    function mtrace($string, $eol = "\n", $bare = false): void
    {
        $GLOBALS['__middag_test_mtrace'][] = (string) $string;
    }
}

// Stub: format_backtrace() — records the ($callers, $plaintext) call and returns
// a deterministic sentinel so the trace block produced by formatTrace() is
// assertable. Real Moodle renders the backtrace; the exact rendering is not
// load-bearing for the adapter's delegation, only that it is used.
if (!function_exists('format_backtrace')) {
    function format_backtrace($callers, $plaintext = false): string
    {
        $GLOBALS['__middag_test_format_backtrace_calls'][] = [
            'callers' => $callers,
            'plaintext' => $plaintext,
        ];

        return 'FORMATTED_BACKTRACE';
    }
}
