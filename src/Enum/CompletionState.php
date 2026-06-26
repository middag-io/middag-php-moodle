<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Enum;

/**
 * Typed enum wrapping Moodle's COMPLETION_* constants.
 *
 * Maps to: COMPLETION_INCOMPLETE (0), COMPLETION_COMPLETE (1),
 * COMPLETION_COMPLETE_PASS (2), COMPLETION_COMPLETE_FAIL (3).
 *
 * @api
 */
enum CompletionState: int
{
    case INCOMPLETE = 0;

    case COMPLETE = 1;

    case COMPLETE_PASS = 2;

    case COMPLETE_FAIL = 3;

    /**
     * Whether the activity is considered completed (regardless of pass/fail).
     */
    public function isComplete(): bool
    {
        return $this !== self::INCOMPLETE;
    }

    /**
     * Whether the activity was completed with a passing grade.
     */
    public function isPassed(): bool
    {
        return $this === self::COMPLETE || $this === self::COMPLETE_PASS;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::INCOMPLETE => 'Incomplete',
            self::COMPLETE => 'Complete',
            self::COMPLETE_PASS => 'Complete (Pass)',
            self::COMPLETE_FAIL => 'Complete (Fail)',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to INCOMPLETE).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::INCOMPLETE;
    }
}
