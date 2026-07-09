<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Group\Enum;

use Middag\Moodle\Domain\Group\Enum\GroupMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GroupMode::class)]
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
        $this->assertSame(0, GroupMode::NoGroups->value);
    }

    #[Test]
    public function separateGroupsHasValue1(): void
    {
        $this->assertSame(1, GroupMode::SeparateGroups->value);
    }

    #[Test]
    public function visibleGroupsHasValue2(): void
    {
        $this->assertSame(2, GroupMode::VisibleGroups->value);
    }

    #[Test]
    public function usesGroupsReturnsFalseOnlyForNoGroups(): void
    {
        $this->assertFalse(GroupMode::NoGroups->usesGroups());
        $this->assertTrue(GroupMode::SeparateGroups->usesGroups());
        $this->assertTrue(GroupMode::VisibleGroups->usesGroups());
    }

    #[Test]
    public function isVisibleReturnsTrueOnlyForVisibleGroups(): void
    {
        $this->assertFalse(GroupMode::NoGroups->isVisible());
        $this->assertFalse(GroupMode::SeparateGroups->isVisible());
        $this->assertTrue(GroupMode::VisibleGroups->isVisible());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('No groups', GroupMode::NoGroups->label());
        $this->assertSame('Separate groups', GroupMode::SeparateGroups->label());
        $this->assertSame('Visible groups', GroupMode::VisibleGroups->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(GroupMode::NoGroups, GroupMode::resolve(0));
        $this->assertSame(GroupMode::SeparateGroups, GroupMode::resolve(1));
        $this->assertSame(GroupMode::VisibleGroups, GroupMode::resolve(2));
    }

    #[Test]
    public function resolveDefaultsToNoGroupsForUnknownValue(): void
    {
        $this->assertSame(GroupMode::NoGroups, GroupMode::resolve(99));
        $this->assertSame(GroupMode::NoGroups, GroupMode::resolve(-1));
    }
}
