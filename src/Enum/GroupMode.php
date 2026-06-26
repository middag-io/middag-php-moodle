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
 * Typed enum wrapping Moodle's group mode constants (NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS).
 *
 * @api
 */
enum GroupMode: int
{
    case NO_GROUPS = 0;

    case SEPARATE_GROUPS = 1;

    case VISIBLE_GROUPS = 2;

    /**
     * Whether groups are enabled in any mode.
     */
    public function usesGroups(): bool
    {
        return $this !== self::NO_GROUPS;
    }

    /**
     * Whether groups are visible to all participants.
     */
    public function isVisible(): bool
    {
        return $this === self::VISIBLE_GROUPS;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::NO_GROUPS => 'No groups',
            self::SEPARATE_GROUPS => 'Separate groups',
            self::VISIBLE_GROUPS => 'Visible groups',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to NO_GROUPS).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::NO_GROUPS;
    }
}
