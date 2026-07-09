<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Enrolment\Enum;

/**
 * Typed enum wrapping Moodle's enrolment status constants.
 *
 * Replaces hardcoded ENROL_USER_ACTIVE (0) and ENROL_USER_SUSPENDED (1)
 * with a type-safe enum for use in enrolment operations.
 *
 * @api
 */
enum EnrolmentStatus: int
{
    case Active = 0;

    case Suspended = 1;

    /**
     * Returns the Moodle constant value for use with enrol APIs.
     */
    public function toMoodleValue(): int
    {
        return $this->value;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Whether the user is actively enrolled.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Resolve from Moodle's raw int value (defaults to ACTIVE).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::Active;
    }
}
