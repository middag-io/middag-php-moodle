<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Enum;

use Middag\Moodle\Enum\GroupMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class GroupModeTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = GroupMode::cases();
        $this->assertCount(3, $cases);
    }

    #[Test]
    public function noGroupsHasValue0(): void
    {
        $this->assertSame(0, GroupMode::NO_GROUPS->value);
    }

    #[Test]
    public function separateGroupsHasValue1(): void
    {
        $this->assertSame(1, GroupMode::SEPARATE_GROUPS->value);
    }

    #[Test]
    public function visibleGroupsHasValue2(): void
    {
        $this->assertSame(2, GroupMode::VISIBLE_GROUPS->value);
    }

    #[Test]
    public function usesGroupsReturnsFalseOnlyForNoGroups(): void
    {
        $this->assertFalse(GroupMode::NO_GROUPS->usesGroups());
        $this->assertTrue(GroupMode::SEPARATE_GROUPS->usesGroups());
        $this->assertTrue(GroupMode::VISIBLE_GROUPS->usesGroups());
    }

    #[Test]
    public function isVisibleReturnsTrueOnlyForVisibleGroups(): void
    {
        $this->assertFalse(GroupMode::NO_GROUPS->isVisible());
        $this->assertFalse(GroupMode::SEPARATE_GROUPS->isVisible());
        $this->assertTrue(GroupMode::VISIBLE_GROUPS->isVisible());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('No groups', GroupMode::NO_GROUPS->label());
        $this->assertSame('Separate groups', GroupMode::SEPARATE_GROUPS->label());
        $this->assertSame('Visible groups', GroupMode::VISIBLE_GROUPS->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(GroupMode::NO_GROUPS, GroupMode::resolve(0));
        $this->assertSame(GroupMode::SEPARATE_GROUPS, GroupMode::resolve(1));
        $this->assertSame(GroupMode::VISIBLE_GROUPS, GroupMode::resolve(2));
    }

    #[Test]
    public function resolveDefaultsToNoGroupsForUnknownValue(): void
    {
        $this->assertSame(GroupMode::NO_GROUPS, GroupMode::resolve(99));
        $this->assertSame(GroupMode::NO_GROUPS, GroupMode::resolve(-1));
    }
}
