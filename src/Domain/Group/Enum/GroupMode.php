<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Group\Enum;

/**
 * Typed enum wrapping Moodle's group mode constants (NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS).
 *
 * @api
 */
enum GroupMode: int
{
    case NoGroups = 0;

    case SeparateGroups = 1;

    case VisibleGroups = 2;

    /**
     * Whether groups are enabled in any mode.
     */
    public function usesGroups(): bool
    {
        return $this !== self::NoGroups;
    }

    /**
     * Whether groups are visible to all participants.
     */
    public function isVisible(): bool
    {
        return $this === self::VisibleGroups;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::NoGroups => 'No groups',
            self::SeparateGroups => 'Separate groups',
            self::VisibleGroups => 'Visible groups',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to NO_GROUPS).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::NoGroups;
    }
}
