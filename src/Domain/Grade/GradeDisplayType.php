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
    case DEFAULT = 0;

    case REAL = 1;

    case PERCENTAGE = 2;

    case LETTER = 3;

    case REAL_PERCENTAGE = 12;

    case REAL_LETTER = 13;

    case LETTER_REAL = 31;

    case LETTER_PERCENTAGE = 32;

    case PERCENTAGE_LETTER = 23;

    case PERCENTAGE_REAL = 21;

    public function showsReal(): bool
    {
        return in_array($this, [self::REAL, self::REAL_PERCENTAGE, self::REAL_LETTER, self::PERCENTAGE_REAL, self::LETTER_REAL], true);
    }

    public function showsPercentage(): bool
    {
        return in_array($this, [self::PERCENTAGE, self::REAL_PERCENTAGE, self::LETTER_PERCENTAGE, self::PERCENTAGE_LETTER, self::PERCENTAGE_REAL], true);
    }

    public function showsLetter(): bool
    {
        return in_array($this, [self::LETTER, self::REAL_LETTER, self::LETTER_REAL, self::LETTER_PERCENTAGE, self::PERCENTAGE_LETTER], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => 'Default',
            self::REAL => 'Real',
            self::PERCENTAGE => 'Percentage',
            self::LETTER => 'Letter',
            self::REAL_PERCENTAGE => 'Real (Percentage)',
            self::REAL_LETTER => 'Real (Letter)',
            self::LETTER_REAL => 'Letter (Real)',
            self::LETTER_PERCENTAGE => 'Letter (Percentage)',
            self::PERCENTAGE_LETTER => 'Percentage (Letter)',
            self::PERCENTAGE_REAL => 'Percentage (Real)',
        };
    }

    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::DEFAULT;
    }
}
