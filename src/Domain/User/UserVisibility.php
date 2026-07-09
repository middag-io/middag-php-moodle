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
    case Nobody = 0;

    case Everyone = 1;

    case Self = 2;

    /**
     * Whether the field is visible to everyone.
     */
    public function isPublic(): bool
    {
        return $this === self::Everyone;
    }

    /**
     * Whether the field is hidden from all users.
     */
    public function isPrivate(): bool
    {
        return $this === self::Nobody;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Nobody => 'Not visible',
            self::Everyone => 'Visible to everyone',
            self::Self => 'Visible to user only',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to NOBODY).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::Nobody;
    }
}
