<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Grade\Enum;

/**
 * Typed enum wrapping Moodle's GRADE_TYPE_* constants.
 *
 * @api
 */
enum GradeType: int
{
    case None = 0;

    case Value = 1;

    case Scale = 2;

    case Text = 3;

    public function isNumeric(): bool
    {
        return $this === self::Value;
    }

    public function isScale(): bool
    {
        return $this === self::Scale;
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Value => 'Value',
            self::Scale => 'Scale',
            self::Text => 'Text',
        };
    }

    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::None;
    }
}
