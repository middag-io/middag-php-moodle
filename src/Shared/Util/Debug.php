<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Shared\Util;

use JsonException;
use Middag\Framework\Shared\Util\Debug as BaseDebug;
use Throwable;

/**
 * Tracing helper (Moodle-flavor).
 *
 * Overrides the framework base to route output through Moodle's `mtrace()`
 * (gives cron task visibility) and to surface Moodle-specific exception
 * properties (`debuginfo`, `module`, `sql`, `params`) typically attached
 * by `moodle_exception` subclasses.
 *
 * The class is intentionally not `final` to keep Moodle-bound extension
 * points open for in-plugin specialization.
 *
 * @api
 */
class Debug extends BaseDebug
{
    /**
     * Emit via Moodle `mtrace()` so cron tasks surface the message in
     * the task log. Falls back to the parent PSR-3 sink only if `mtrace`
     * is somehow unavailable (e.g. tests outside the Moodle bootstrap).
     */
    protected static function emit(string $message): void
    {
        if (function_exists('mtrace')) {
            mtrace($message);

            return;
        }

        parent::emit($message);
    }

    /**
     * Append Moodle-specific exception fields when present:
     *   - `debuginfo` (moodle_exception)
     *   - `module` (moodle_exception, optional)
     *   - `sql` / `params` (dml_exception)
     *
     * Uses `format_backtrace()` for the trace block when the Moodle
     * helper is available; otherwise the parent's string trace is used.
     *
     * @return list<string>
     */
    protected static function formatExceptionLines(Throwable $exception): array
    {
        $lines = [
            '@@@@@@ EXCEPTION @@@@@@',
            'Code: ' . $exception->getCode(),
            'Message: ' . $exception->getMessage(),
            'Trace: ',
            self::formatTrace($exception),
        ];

        if (property_exists($exception, 'debuginfo') && $exception->debuginfo !== null) {
            $lines[] = 'DEBUGINFO: ' . $exception->debuginfo;
        }

        if (property_exists($exception, 'module') && $exception->module !== null) {
            $lines[] = 'Module: ' . $exception->module;
        }

        if (property_exists($exception, 'sql') && $exception->sql !== null) {
            $lines[] = 'SQL: ' . $exception->sql;

            if (property_exists($exception, 'params') && is_array($exception->params)) {
                try {
                    $lines[] = 'SQL PARAMS: ' . json_encode($exception->params, JSON_THROW_ON_ERROR);
                } catch (JsonException $jsonException) {
                    $lines[] = 'SQL PARAMS: ' . $jsonException->getMessage();
                }
            }
        }

        return $lines;
    }

    private static function formatTrace(Throwable $exception): string
    {
        if (function_exists('format_backtrace')) {
            $cli_only = (defined('CLI_SCRIPT') && CLI_SCRIPT)
                || (defined('NO_DEBUG_DISPLAY') && NO_DEBUG_DISPLAY);

            return format_backtrace($exception->getTrace(), $cli_only);
        }

        return $exception->getTraceAsString();
    }
}
