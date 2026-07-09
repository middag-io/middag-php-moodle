<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Grade\Enum;

use Middag\Moodle\Domain\Grade\Enum\GradeDisplayType;
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
        $this->assertSame(0, GradeDisplayType::Default->value);
        $this->assertSame(1, GradeDisplayType::Real->value);
        $this->assertSame(2, GradeDisplayType::Percentage->value);
        $this->assertSame(3, GradeDisplayType::Letter->value);
        $this->assertSame(12, GradeDisplayType::RealPercentage->value);
        $this->assertSame(13, GradeDisplayType::RealLetter->value);
        $this->assertSame(31, GradeDisplayType::LetterReal->value);
        $this->assertSame(32, GradeDisplayType::LetterPercentage->value);
        $this->assertSame(23, GradeDisplayType::PercentageLetter->value);
        $this->assertSame(21, GradeDisplayType::PercentageReal->value);
        $this->assertCount(10, GradeDisplayType::cases());
    }

    #[Test]
    public function showsRealReturnsTrueForRealBearingModes(): void
    {
        $this->assertTrue(GradeDisplayType::Real->showsReal());
        $this->assertTrue(GradeDisplayType::RealPercentage->showsReal());
        $this->assertTrue(GradeDisplayType::RealLetter->showsReal());
        $this->assertTrue(GradeDisplayType::PercentageReal->showsReal());
        $this->assertTrue(GradeDisplayType::LetterReal->showsReal());
    }

    #[Test]
    public function showsRealReturnsFalseForModesWithoutReal(): void
    {
        $this->assertFalse(GradeDisplayType::Default->showsReal());
        $this->assertFalse(GradeDisplayType::Percentage->showsReal());
        $this->assertFalse(GradeDisplayType::Letter->showsReal());
        $this->assertFalse(GradeDisplayType::LetterPercentage->showsReal());
        $this->assertFalse(GradeDisplayType::PercentageLetter->showsReal());
    }

    #[Test]
    public function showsPercentageReturnsTrueForPercentageBearingModes(): void
    {
        $this->assertTrue(GradeDisplayType::Percentage->showsPercentage());
        $this->assertTrue(GradeDisplayType::RealPercentage->showsPercentage());
        $this->assertTrue(GradeDisplayType::LetterPercentage->showsPercentage());
        $this->assertTrue(GradeDisplayType::PercentageLetter->showsPercentage());
        $this->assertTrue(GradeDisplayType::PercentageReal->showsPercentage());
    }

    #[Test]
    public function showsPercentageReturnsFalseForModesWithoutPercentage(): void
    {
        $this->assertFalse(GradeDisplayType::Default->showsPercentage());
        $this->assertFalse(GradeDisplayType::Real->showsPercentage());
        $this->assertFalse(GradeDisplayType::Letter->showsPercentage());
        $this->assertFalse(GradeDisplayType::RealLetter->showsPercentage());
        $this->assertFalse(GradeDisplayType::LetterReal->showsPercentage());
    }

    #[Test]
    public function showsLetterReturnsTrueForLetterBearingModes(): void
    {
        $this->assertTrue(GradeDisplayType::Letter->showsLetter());
        $this->assertTrue(GradeDisplayType::RealLetter->showsLetter());
        $this->assertTrue(GradeDisplayType::LetterReal->showsLetter());
        $this->assertTrue(GradeDisplayType::LetterPercentage->showsLetter());
        $this->assertTrue(GradeDisplayType::PercentageLetter->showsLetter());
    }

    #[Test]
    public function showsLetterReturnsFalseForModesWithoutLetter(): void
    {
        $this->assertFalse(GradeDisplayType::Default->showsLetter());
        $this->assertFalse(GradeDisplayType::Real->showsLetter());
        $this->assertFalse(GradeDisplayType::Percentage->showsLetter());
        $this->assertFalse(GradeDisplayType::RealPercentage->showsLetter());
        $this->assertFalse(GradeDisplayType::PercentageReal->showsLetter());
    }

    #[Test]
    public function labelCoversEveryCase(): void
    {
        $this->assertSame('Default', GradeDisplayType::Default->label());
        $this->assertSame('Real', GradeDisplayType::Real->label());
        $this->assertSame('Percentage', GradeDisplayType::Percentage->label());
        $this->assertSame('Letter', GradeDisplayType::Letter->label());
        $this->assertSame('Real (Percentage)', GradeDisplayType::RealPercentage->label());
        $this->assertSame('Real (Letter)', GradeDisplayType::RealLetter->label());
        $this->assertSame('Letter (Real)', GradeDisplayType::LetterReal->label());
        $this->assertSame('Letter (Percentage)', GradeDisplayType::LetterPercentage->label());
        $this->assertSame('Percentage (Letter)', GradeDisplayType::PercentageLetter->label());
        $this->assertSame('Percentage (Real)', GradeDisplayType::PercentageReal->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(GradeDisplayType::Real, GradeDisplayType::resolve(1));
        $this->assertSame(GradeDisplayType::RealPercentage, GradeDisplayType::resolve(12));
        $this->assertSame(GradeDisplayType::PercentageReal, GradeDisplayType::resolve(21));
    }

    #[Test]
    public function resolveDefaultsToDefaultForUnknownValue(): void
    {
        $this->assertSame(GradeDisplayType::Default, GradeDisplayType::resolve(0));
        $this->assertSame(GradeDisplayType::Default, GradeDisplayType::resolve(999));
        $this->assertSame(GradeDisplayType::Default, GradeDisplayType::resolve(-5));
    }
}
