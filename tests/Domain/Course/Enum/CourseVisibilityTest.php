<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Course\Enum;

use Middag\Moodle\Domain\Course\Enum\CourseVisibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CourseVisibility::class)]
final class CourseVisibilityTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = CourseVisibility::cases();
        $this->assertCount(2, $cases);
    }

    #[Test]
    public function hiddenHasValue0(): void
    {
        $this->assertSame(0, CourseVisibility::Hidden->value);
    }

    #[Test]
    public function visibleHasValue1(): void
    {
        $this->assertSame(1, CourseVisibility::Visible->value);
    }

    #[Test]
    public function isVisibleReturnsTrueOnlyForVisible(): void
    {
        $this->assertFalse(CourseVisibility::Hidden->isVisible());
        $this->assertTrue(CourseVisibility::Visible->isVisible());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Hidden', CourseVisibility::Hidden->label());
        $this->assertSame('Visible', CourseVisibility::Visible->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(CourseVisibility::Hidden, CourseVisibility::resolve(0));
        $this->assertSame(CourseVisibility::Visible, CourseVisibility::resolve(1));
    }

    #[Test]
    public function resolveDefaultsToVisibleForUnknownValue(): void
    {
        $this->assertSame(CourseVisibility::Visible, CourseVisibility::resolve(99));
        $this->assertSame(CourseVisibility::Visible, CourseVisibility::resolve(-1));
    }
}
