<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Completion\Enum;

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
    case Incomplete = 0;

    case Complete = 1;

    case CompletePass = 2;

    case CompleteFail = 3;

    /**
     * Whether the activity is considered completed (regardless of pass/fail).
     */
    public function isComplete(): bool
    {
        return $this !== self::Incomplete;
    }

    /**
     * Whether the activity was completed with a passing grade.
     */
    public function isPassed(): bool
    {
        return $this === self::Complete || $this === self::CompletePass;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Incomplete => 'Incomplete',
            self::Complete => 'Complete',
            self::CompletePass => 'Complete (Pass)',
            self::CompleteFail => 'Complete (Fail)',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to INCOMPLETE).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::Incomplete;
    }
}
