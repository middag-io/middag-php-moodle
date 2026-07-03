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
 * Typed enum wrapping Moodle's GRADE_TYPE_* constants.
 *
 * @api
 */
enum GradeType: int
{
    case NONE = 0;

    case VALUE = 1;

    case SCALE = 2;

    case TEXT = 3;

    public function isNumeric(): bool
    {
        return $this === self::VALUE;
    }

    public function isScale(): bool
    {
        return $this === self::SCALE;
    }

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::VALUE => 'Value',
            self::SCALE => 'Scale',
            self::TEXT => 'Text',
        };
    }

    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::NONE;
    }
}
