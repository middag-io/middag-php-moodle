<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Task\Enum;

/**
 * Typed enum wrapping Moodle task execution states.
 *
 * @api
 */
enum TaskRunState: string
{
    case None = 'none';

    case Running = 'running';

    case Failed = 'failed';

    public function isActive(): bool
    {
        return $this === self::Running;
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'Idle',
            self::Running => 'Running',
            self::Failed => 'Failed',
        };
    }

    public static function resolve(string $value): self
    {
        return self::tryFrom($value) ?? self::None;
    }
}
