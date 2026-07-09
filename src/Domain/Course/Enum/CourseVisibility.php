<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Course\Enum;

/**
 * Typed enum for Moodle visibility flags.
 *
 * Used across courses, activities, categories and other Moodle objects
 * that have a binary visible/hidden state.
 *
 * @api
 */
enum CourseVisibility: int
{
    case Hidden = 0;

    case Visible = 1;

    public function isVisible(): bool
    {
        return $this === self::Visible;
    }

    public function label(): string
    {
        return match ($this) {
            self::Hidden => 'Hidden',
            self::Visible => 'Visible',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to VISIBLE).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::Visible;
    }
}
