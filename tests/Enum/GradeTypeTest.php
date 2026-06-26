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

use Middag\Moodle\Enum\GradeType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class GradeTypeTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = GradeType::cases();
        $this->assertCount(4, $cases);
    }

    #[Test]
    public function noneHasValue0(): void
    {
        $this->assertSame(0, GradeType::NONE->value);
    }

    #[Test]
    public function valueHasValue1(): void
    {
        $this->assertSame(1, GradeType::VALUE->value);
    }

    #[Test]
    public function scaleHasValue2(): void
    {
        $this->assertSame(2, GradeType::SCALE->value);
    }

    #[Test]
    public function textHasValue3(): void
    {
        $this->assertSame(3, GradeType::TEXT->value);
    }

    #[Test]
    public function isNumericReturnsTrueOnlyForValue(): void
    {
        $this->assertFalse(GradeType::NONE->isNumeric());
        $this->assertTrue(GradeType::VALUE->isNumeric());
        $this->assertFalse(GradeType::SCALE->isNumeric());
        $this->assertFalse(GradeType::TEXT->isNumeric());
    }

    #[Test]
    public function isScaleReturnsTrueOnlyForScale(): void
    {
        $this->assertFalse(GradeType::NONE->isScale());
        $this->assertFalse(GradeType::VALUE->isScale());
        $this->assertTrue(GradeType::SCALE->isScale());
        $this->assertFalse(GradeType::TEXT->isScale());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('None', GradeType::NONE->label());
        $this->assertSame('Value', GradeType::VALUE->label());
        $this->assertSame('Scale', GradeType::SCALE->label());
        $this->assertSame('Text', GradeType::TEXT->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(GradeType::NONE, GradeType::resolve(0));
        $this->assertSame(GradeType::VALUE, GradeType::resolve(1));
        $this->assertSame(GradeType::SCALE, GradeType::resolve(2));
        $this->assertSame(GradeType::TEXT, GradeType::resolve(3));
    }

    #[Test]
    public function resolveDefaultsToNoneForUnknownValue(): void
    {
        $this->assertSame(GradeType::NONE, GradeType::resolve(99));
        $this->assertSame(GradeType::NONE, GradeType::resolve(-1));
    }
}
