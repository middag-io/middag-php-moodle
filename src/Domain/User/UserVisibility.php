<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\User;

/**
 * Typed enum wrapping Moodle's user profile field visibility levels (0=nobody, 1=everyone, 2=self-only).
 *
 * @api
 */
enum UserVisibility: int
{
    case NOBODY = 0;

    case EVERYONE = 1;

    case SELF = 2;

    /**
     * Whether the field is visible to everyone.
     */
    public function isPublic(): bool
    {
        return $this === self::EVERYONE;
    }

    /**
     * Whether the field is hidden from all users.
     */
    public function isPrivate(): bool
    {
        return $this === self::NOBODY;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::NOBODY => 'Not visible',
            self::EVERYONE => 'Visible to everyone',
            self::SELF => 'Visible to user only',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to NOBODY).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::NOBODY;
    }
}
