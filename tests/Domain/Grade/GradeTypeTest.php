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

use Middag\Moodle\Domain\Grade\GradeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GradeType::class)]
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
        $this->assertSame(0, GradeType::None->value);
    }

    #[Test]
    public function valueHasValue1(): void
    {
        $this->assertSame(1, GradeType::Value->value);
    }

    #[Test]
    public function scaleHasValue2(): void
    {
        $this->assertSame(2, GradeType::Scale->value);
    }

    #[Test]
    public function textHasValue3(): void
    {
        $this->assertSame(3, GradeType::Text->value);
    }

    #[Test]
    public function isNumericReturnsTrueOnlyForValue(): void
    {
        $this->assertFalse(GradeType::None->isNumeric());
        $this->assertTrue(GradeType::Value->isNumeric());
        $this->assertFalse(GradeType::Scale->isNumeric());
        $this->assertFalse(GradeType::Text->isNumeric());
    }

    #[Test]
    public function isScaleReturnsTrueOnlyForScale(): void
    {
        $this->assertFalse(GradeType::None->isScale());
        $this->assertFalse(GradeType::Value->isScale());
        $this->assertTrue(GradeType::Scale->isScale());
        $this->assertFalse(GradeType::Text->isScale());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('None', GradeType::None->label());
        $this->assertSame('Value', GradeType::Value->label());
        $this->assertSame('Scale', GradeType::Scale->label());
        $this->assertSame('Text', GradeType::Text->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(GradeType::None, GradeType::resolve(0));
        $this->assertSame(GradeType::Value, GradeType::resolve(1));
        $this->assertSame(GradeType::Scale, GradeType::resolve(2));
        $this->assertSame(GradeType::Text, GradeType::resolve(3));
    }

    #[Test]
    public function resolveDefaultsToNoneForUnknownValue(): void
    {
        $this->assertSame(GradeType::None, GradeType::resolve(99));
        $this->assertSame(GradeType::None, GradeType::resolve(-1));
    }
}
