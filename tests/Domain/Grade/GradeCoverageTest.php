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

use Middag\Moodle\Domain\Grade\Grade;
use Middag\Moodle\Shared\Enum\TextFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Grade adds the table binding, two typed-format accessors mapping raw ints to
 * TextFormat, and a set of boolean state predicates over the entity base; every
 * property accessor is inherited from AbstractMoodleEntity.
 *
 * @internal
 */
#[CoversClass(Grade::class)]
final class GradeCoverageTest extends TestCase
{
    #[Test]
    public function testTableIsGradeGrades(): void
    {
        self::assertSame('grade_grades', Grade::getTable());
    }

    #[Test]
    public function testFeedbackFormatResolvesTheRawFormatToTextFormat(): void
    {
        $grade = Grade::fromRecord(['feedbackformat' => 1]);

        self::assertSame(TextFormat::Html, $grade->feedbackFormat());
    }

    #[Test]
    public function testInfoFormatResolvesTheRawFormatToTextFormat(): void
    {
        $grade = Grade::fromRecord(['informationformat' => 4]);

        self::assertSame(TextFormat::Markdown, $grade->infoFormat());
    }

    #[Test]
    public function testIsHiddenReflectsThePositiveFlag(): void
    {
        self::assertTrue(Grade::fromRecord(['hidden' => 1])->isHidden());
        self::assertFalse(Grade::fromRecord(['hidden' => 0])->isHidden());
    }

    #[Test]
    public function testIsLockedReflectsThePositiveFlag(): void
    {
        self::assertTrue(Grade::fromRecord(['locked' => 1])->isLocked());
        self::assertFalse(Grade::fromRecord(['locked' => 0])->isLocked());
    }

    #[Test]
    public function testIsOverriddenReflectsThePositiveFlag(): void
    {
        self::assertTrue(Grade::fromRecord(['overridden' => 1])->isOverridden());
        self::assertFalse(Grade::fromRecord(['overridden' => 0])->isOverridden());
    }

    #[Test]
    public function testIsExcludedReflectsThePositiveFlag(): void
    {
        self::assertTrue(Grade::fromRecord(['excluded' => 1])->isExcluded());
        self::assertFalse(Grade::fromRecord(['excluded' => 0])->isExcluded());
    }

    #[Test]
    public function testHasFeedbackIsTrueOnlyWhenFeedbackTextIsPresent(): void
    {
        self::assertTrue(Grade::fromRecord(['feedback' => 'Well done'])->hasFeedback());
        self::assertFalse(Grade::fromRecord(['feedback' => ''])->hasFeedback());
        self::assertFalse(Grade::fromRecord(['feedback' => null])->hasFeedback());
    }
}
