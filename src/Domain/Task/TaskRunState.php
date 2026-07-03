<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Task;

/**
 * Typed enum wrapping Moodle task execution states.
 *
 * @api
 */
enum TaskRunState: string
{
    case NONE = 'none';

    case RUNNING = 'running';

    case FAILED = 'failed';

    public function isActive(): bool
    {
        return $this === self::RUNNING;
    }

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Idle',
            self::RUNNING => 'Running',
            self::FAILED => 'Failed',
        };
    }

    public static function resolve(string $value): self
    {
        return self::tryFrom($value) ?? self::NONE;
    }
}
