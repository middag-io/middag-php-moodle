<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Grade;

/**
 * Typed enum wrapping Moodle's GRADE_DISPLAY_TYPE_* constants.
 *
 * Composite values (e.g. REAL_PERCENTAGE = 12) represent combined display modes.
 *
 * @api
 */
enum GradeDisplayType: int
{
    case Default = 0;

    case Real = 1;

    case Percentage = 2;

    case Letter = 3;

    case RealPercentage = 12;

    case RealLetter = 13;

    case LetterReal = 31;

    case LetterPercentage = 32;

    case PercentageLetter = 23;

    case PercentageReal = 21;

    public function showsReal(): bool
    {
        return in_array($this, [self::Real, self::RealPercentage, self::RealLetter, self::PercentageReal, self::LetterReal], true);
    }

    public function showsPercentage(): bool
    {
        return in_array($this, [self::Percentage, self::RealPercentage, self::LetterPercentage, self::PercentageLetter, self::PercentageReal], true);
    }

    public function showsLetter(): bool
    {
        return in_array($this, [self::Letter, self::RealLetter, self::LetterReal, self::LetterPercentage, self::PercentageLetter], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Real => 'Real',
            self::Percentage => 'Percentage',
            self::Letter => 'Letter',
            self::RealPercentage => 'Real (Percentage)',
            self::RealLetter => 'Real (Letter)',
            self::LetterReal => 'Letter (Real)',
            self::LetterPercentage => 'Letter (Percentage)',
            self::PercentageLetter => 'Percentage (Letter)',
            self::PercentageReal => 'Percentage (Real)',
        };
    }

    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::Default;
    }
}
