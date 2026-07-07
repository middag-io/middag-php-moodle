<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Course;

use Middag\Moodle\Domain\Course\CourseVisibility;
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
        $this->assertSame(0, CourseVisibility::HIDDEN->value);
    }

    #[Test]
    public function visibleHasValue1(): void
    {
        $this->assertSame(1, CourseVisibility::VISIBLE->value);
    }

    #[Test]
    public function isVisibleReturnsTrueOnlyForVisible(): void
    {
        $this->assertFalse(CourseVisibility::HIDDEN->isVisible());
        $this->assertTrue(CourseVisibility::VISIBLE->isVisible());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Hidden', CourseVisibility::HIDDEN->label());
        $this->assertSame('Visible', CourseVisibility::VISIBLE->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(CourseVisibility::HIDDEN, CourseVisibility::resolve(0));
        $this->assertSame(CourseVisibility::VISIBLE, CourseVisibility::resolve(1));
    }

    #[Test]
    public function resolveDefaultsToVisibleForUnknownValue(): void
    {
        $this->assertSame(CourseVisibility::VISIBLE, CourseVisibility::resolve(99));
        $this->assertSame(CourseVisibility::VISIBLE, CourseVisibility::resolve(-1));
    }
}
