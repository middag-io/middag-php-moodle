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

use Middag\Moodle\Domain\Grade\GradeDisplayType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GradeDisplayType::class)]
final class GradeDisplayTypeCoverageTest extends TestCase
{
    #[Test]
    public function allCasesHaveExpectedValues(): void
    {
        $this->assertSame(0, GradeDisplayType::DEFAULT->value);
        $this->assertSame(1, GradeDisplayType::REAL->value);
        $this->assertSame(2, GradeDisplayType::PERCENTAGE->value);
        $this->assertSame(3, GradeDisplayType::LETTER->value);
        $this->assertSame(12, GradeDisplayType::REAL_PERCENTAGE->value);
        $this->assertSame(13, GradeDisplayType::REAL_LETTER->value);
        $this->assertSame(31, GradeDisplayType::LETTER_REAL->value);
        $this->assertSame(32, GradeDisplayType::LETTER_PERCENTAGE->value);
        $this->assertSame(23, GradeDisplayType::PERCENTAGE_LETTER->value);
        $this->assertSame(21, GradeDisplayType::PERCENTAGE_REAL->value);
        $this->assertCount(10, GradeDisplayType::cases());
    }

    #[Test]
    public function showsRealReturnsTrueForRealBearingModes(): void
    {
        $this->assertTrue(GradeDisplayType::REAL->showsReal());
        $this->assertTrue(GradeDisplayType::REAL_PERCENTAGE->showsReal());
        $this->assertTrue(GradeDisplayType::REAL_LETTER->showsReal());
        $this->assertTrue(GradeDisplayType::PERCENTAGE_REAL->showsReal());
        $this->assertTrue(GradeDisplayType::LETTER_REAL->showsReal());
    }

    #[Test]
    public function showsRealReturnsFalseForModesWithoutReal(): void
    {
        $this->assertFalse(GradeDisplayType::DEFAULT->showsReal());
        $this->assertFalse(GradeDisplayType::PERCENTAGE->showsReal());
        $this->assertFalse(GradeDisplayType::LETTER->showsReal());
        $this->assertFalse(GradeDisplayType::LETTER_PERCENTAGE->showsReal());
        $this->assertFalse(GradeDisplayType::PERCENTAGE_LETTER->showsReal());
    }

    #[Test]
    public function showsPercentageReturnsTrueForPercentageBearingModes(): void
    {
        $this->assertTrue(GradeDisplayType::PERCENTAGE->showsPercentage());
        $this->assertTrue(GradeDisplayType::REAL_PERCENTAGE->showsPercentage());
        $this->assertTrue(GradeDisplayType::LETTER_PERCENTAGE->showsPercentage());
        $this->assertTrue(GradeDisplayType::PERCENTAGE_LETTER->showsPercentage());
        $this->assertTrue(GradeDisplayType::PERCENTAGE_REAL->showsPercentage());
    }

    #[Test]
    public function showsPercentageReturnsFalseForModesWithoutPercentage(): void
    {
        $this->assertFalse(GradeDisplayType::DEFAULT->showsPercentage());
        $this->assertFalse(GradeDisplayType::REAL->showsPercentage());
        $this->assertFalse(GradeDisplayType::LETTER->showsPercentage());
        $this->assertFalse(GradeDisplayType::REAL_LETTER->showsPercentage());
        $this->assertFalse(GradeDisplayType::LETTER_REAL->showsPercentage());
    }

    #[Test]
    public function showsLetterReturnsTrueForLetterBearingModes(): void
    {
        $this->assertTrue(GradeDisplayType::LETTER->showsLetter());
        $this->assertTrue(GradeDisplayType::REAL_LETTER->showsLetter());
        $this->assertTrue(GradeDisplayType::LETTER_REAL->showsLetter());
        $this->assertTrue(GradeDisplayType::LETTER_PERCENTAGE->showsLetter());
        $this->assertTrue(GradeDisplayType::PERCENTAGE_LETTER->showsLetter());
    }

    #[Test]
    public function showsLetterReturnsFalseForModesWithoutLetter(): void
    {
        $this->assertFalse(GradeDisplayType::DEFAULT->showsLetter());
        $this->assertFalse(GradeDisplayType::REAL->showsLetter());
        $this->assertFalse(GradeDisplayType::PERCENTAGE->showsLetter());
        $this->assertFalse(GradeDisplayType::REAL_PERCENTAGE->showsLetter());
        $this->assertFalse(GradeDisplayType::PERCENTAGE_REAL->showsLetter());
    }

    #[Test]
    public function labelCoversEveryCase(): void
    {
        $this->assertSame('Default', GradeDisplayType::DEFAULT->label());
        $this->assertSame('Real', GradeDisplayType::REAL->label());
        $this->assertSame('Percentage', GradeDisplayType::PERCENTAGE->label());
        $this->assertSame('Letter', GradeDisplayType::LETTER->label());
        $this->assertSame('Real (Percentage)', GradeDisplayType::REAL_PERCENTAGE->label());
        $this->assertSame('Real (Letter)', GradeDisplayType::REAL_LETTER->label());
        $this->assertSame('Letter (Real)', GradeDisplayType::LETTER_REAL->label());
        $this->assertSame('Letter (Percentage)', GradeDisplayType::LETTER_PERCENTAGE->label());
        $this->assertSame('Percentage (Letter)', GradeDisplayType::PERCENTAGE_LETTER->label());
        $this->assertSame('Percentage (Real)', GradeDisplayType::PERCENTAGE_REAL->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(GradeDisplayType::REAL, GradeDisplayType::resolve(1));
        $this->assertSame(GradeDisplayType::REAL_PERCENTAGE, GradeDisplayType::resolve(12));
        $this->assertSame(GradeDisplayType::PERCENTAGE_REAL, GradeDisplayType::resolve(21));
    }

    #[Test]
    public function resolveDefaultsToDefaultForUnknownValue(): void
    {
        $this->assertSame(GradeDisplayType::DEFAULT, GradeDisplayType::resolve(0));
        $this->assertSame(GradeDisplayType::DEFAULT, GradeDisplayType::resolve(999));
        $this->assertSame(GradeDisplayType::DEFAULT, GradeDisplayType::resolve(-5));
    }
}
