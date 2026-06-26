<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use Middag\Framework\Bus\Attribute\Schedule;

/**
 * Builds Moodle `db/tasks.php` row entries from a `#[Schedule]` attribute.
 *
 * The framework `Schedule` attribute is a platform-agnostic cron DSL; this
 * helper translates it into the array shape consumed by Moodle's
 * `core\task` registration tooling (`db/tasks.php`).
 *
 * Used by code-generation tooling that scans command classes for the
 * `#[Schedule]` attribute and emits a `tasks.php` manifest.
 *
 * @internal
 */
final readonly class TaskDefinitionBuilder
{
    /**
     * Convert a `Schedule` attribute instance + class name to a Moodle
     * `db/tasks.php` row.
     *
     * @param Schedule $schedule       attribute instance read via reflection
     * @param string   $task_classname fully-qualified task class name
     *
     * @return array<string, mixed>
     */
    public static function build(Schedule $schedule, string $task_classname): array
    {
        return [
            'classname' => $task_classname,
            'blocking' => $schedule->exclusive ? 1 : 0,
            'minute' => $schedule->minute,
            'hour' => $schedule->hour,
            'day' => $schedule->day,
            'month' => $schedule->month,
            'dayofweek' => $schedule->dayOfWeek,
            'disabled' => $schedule->disabled ? 1 : 0,
        ];
    }
}
