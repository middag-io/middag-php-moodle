<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Grade;

use Middag\Moodle\Domain\Grade\Enum\GradeDisplayType;
use Middag\Moodle\Domain\Grade\Enum\GradeType;
use Middag\Moodle\Domain\Grade\GradeItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GradeItem adds the table binding, two typed accessors mapping raw ints to the
 * GradeType / GradeDisplayType enums, and two boolean state predicates over the
 * entity base; every property accessor is inherited from AbstractMoodleEntity.
 *
 * @internal
 */
#[CoversClass(GradeItem::class)]
final class GradeItemCoverageTest extends TestCase
{
    #[Test]
    public function testTableIsGradeItems(): void
    {
        self::assertSame('grade_items', GradeItem::getTable());
    }

    #[Test]
    public function testGradeTypeResolvesTheRawValueToGradeType(): void
    {
        $item = GradeItem::fromRecord(['gradetype' => 1]);

        self::assertSame(GradeType::Value, $item->gradeType());
    }

    #[Test]
    public function testDisplayTypeResolvesTheRawValueToGradeDisplayType(): void
    {
        $item = GradeItem::fromRecord(['display' => 2]);

        self::assertSame(GradeDisplayType::Percentage, $item->displayType());
    }

    #[Test]
    public function testIsHiddenReflectsThePositiveFlag(): void
    {
        self::assertTrue(GradeItem::fromRecord(['hidden' => 1])->isHidden());
        self::assertFalse(GradeItem::fromRecord(['hidden' => 0])->isHidden());
    }

    #[Test]
    public function testIsLockedReflectsThePositiveFlag(): void
    {
        self::assertTrue(GradeItem::fromRecord(['locked' => 1])->isLocked());
        self::assertFalse(GradeItem::fromRecord(['locked' => 0])->isLocked());
    }
}
