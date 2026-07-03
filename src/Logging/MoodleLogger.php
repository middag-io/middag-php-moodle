<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * PSR-3 logger adapter delegating to Moodle's debugging() function.
 *
 * This is the default LoggerInterface implementation registered in the
 * container. It serves as the PSR-3 entry point for services, loaders
 * and extensions that declare LoggerInterface as a dependency.
 *
 * Behavior by level:
 * - debug, info, notice, warning → debugging() only (respects $CFG->debug)
 * - error, critical, alert, emergency → debugging() + error_log() (always visible in production)
 *
 * The error_log() fallback ensures that critical failures (boot errors,
 * infrastructure failures) are never silently lost in production where
 * Moodle's debug mode is typically disabled. This is an interim solution
 * until the file-based logger (ADR-924) is implemented.
 *
 * Can be instantiated without the container (no constructor dependencies),
 * making it safe to use as a boot logger during kernel initialization.
 *
 * @internal
 */
class MoodleLogger extends AbstractLogger
{
    /**
     * PSR-3 level → Moodle debugging level mapping.
     */
    private const LEVEL_MAP = [
        LogLevel::EMERGENCY => DEBUG_NORMAL,
        LogLevel::ALERT => DEBUG_NORMAL,
        LogLevel::CRITICAL => DEBUG_NORMAL,
        LogLevel::ERROR => DEBUG_NORMAL,
        LogLevel::WARNING => DEBUG_NORMAL,
        LogLevel::NOTICE => DEBUG_NORMAL,
        LogLevel::INFO => DEBUG_DEVELOPER,
        LogLevel::DEBUG => DEBUG_DEVELOPER,
    ];

    /**
     * PSR-3 levels that always write to PHP error_log(), regardless of Moodle debug config.
     */
    private const CRITICAL_LEVELS = [
        LogLevel::EMERGENCY => true,
        LogLevel::ALERT => true,
        LogLevel::CRITICAL => true,
        LogLevel::ERROR => true,
    ];

    /**
     * @param string|Stringable $level
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $level_string = (string) $level;
        $formatted = $this->format($level_string, (string) $message, $context);
        $moodle_level = self::LEVEL_MAP[$level_string] ?? DEBUG_DEVELOPER;

        debugging($formatted, $moodle_level);

        if (isset(self::CRITICAL_LEVELS[$level_string])) {
            // phpcs:ignore -- error_log is intentional for critical/emergency: debugging() alone does not guarantee server-level visibility (syslog/stderr) for production incidents.
            error_log($formatted);
        }
    }

    /**
     * Format the log message with context.
     */
    private function format(string $level, string $message, array $context): string
    {
        // Interpolate {placeholder} from context (PSR-3 spec)
        $replacements = [];
        $used_keys = [];

        foreach ($context as $key => $value) {
            $placeholder = '{' . $key . '}';

            if (str_contains($message, $placeholder) === false) {
                continue;
            }

            if ($key === 'exception' && $value instanceof Throwable) {
                $replacements[$placeholder] = $value->getMessage();
                $used_keys[$key] = true;

                continue;
            }

            if (is_scalar($value) || $value instanceof Stringable) {
                $replacements[$placeholder] = (string) $value;
                $used_keys[$key] = true;
            }
        }

        $interpolated = strtr($message, $replacements);

        // Append remaining context as key=value pairs
        $extra = array_diff_key($context, $used_keys);

        if ($extra !== []) {
            $pairs = [];

            foreach ($extra as $key => $value) {
                if ($value instanceof Throwable) {
                    $pairs[] = sprintf('%s=%s', $key, $value->getMessage());
                } elseif (is_scalar($value)) {
                    $pairs[] = sprintf('%s=%s', $key, $value);
                } elseif (is_array($value)) {
                    $pairs[] = sprintf('%s=%s', $key, json_encode($value, JSON_UNESCAPED_SLASHES));
                }
            }

            if ($pairs !== []) {
                $interpolated .= ' [' . implode(', ', $pairs) . ']';
            }
        }

        return sprintf('[middag.%s] %s', $level, $interpolated);
    }
}
