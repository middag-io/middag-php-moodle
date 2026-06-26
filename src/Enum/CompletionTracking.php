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
 * Typed enum wrapping Moodle's COMPLETION_TRACKING_* constants.
 *
 * Represents how completion is tracked for a course module:
 * - NONE: completion is not tracked
 * - MANUAL: student manually marks as complete
 * - AUTOMATIC: system evaluates completion criteria
 *
 * Maps to: COMPLETION_TRACKING_NONE (0), COMPLETION_TRACKING_MANUAL (1),
 * COMPLETION_TRACKING_AUTOMATIC (2).
 *
 * @api
 */
enum CompletionTracking: int
{
    case NONE = 0;

    case MANUAL = 1;

    case AUTOMATIC = 2;

    /**
     * Whether completion is tracked at all.
     */
    public function isTracked(): bool
    {
        return $this !== self::NONE;
    }

    /**
     * Whether the user marks completion manually.
     */
    public function isManual(): bool
    {
        return $this === self::MANUAL;
    }

    /**
     * Whether the system evaluates completion automatically.
     */
    public function isAutomatic(): bool
    {
        return $this === self::AUTOMATIC;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Not tracked',
            self::MANUAL => 'Manual',
            self::AUTOMATIC => 'Automatic',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to NONE).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::NONE;
    }
}
