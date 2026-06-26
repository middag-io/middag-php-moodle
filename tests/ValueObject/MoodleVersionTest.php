<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\ValueObject;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Moodle\ValueObject\MoodleVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * @internal
 *
 * @coversNothing
 */
final class MoodleVersionTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithMajorAndMinor(): void
    {
        $v = new MoodleVersion(major: 4, minor: 5);
        $this->assertSame(4, $v->major);
        $this->assertSame(5, $v->minor);
        $this->assertSame(0, $v->patch);
        $this->assertNull($v->suffix);
    }

    #[Test]
    public function canBeConstructedWithAllParts(): void
    {
        $v = new MoodleVersion(major: 4, minor: 5, patch: 2, suffix: '+');
        $this->assertSame(4, $v->major);
        $this->assertSame(5, $v->minor);
        $this->assertSame(2, $v->patch);
        $this->assertSame('+', $v->suffix);
    }

    #[Test]
    public function toStringReturnsVersionString(): void
    {
        $v = new MoodleVersion(major: 4, minor: 5, patch: 0);
        $this->assertSame('4.5.0', (string) $v);
    }

    #[Test]
    public function toStringIncludesSuffix(): void
    {
        $v = new MoodleVersion(major: 4, minor: 5, patch: 0, suffix: '+');
        $this->assertSame('4.5.0+', (string) $v);
    }

    #[Test]
    public function toStringIncludesDevSuffix(): void
    {
        $v = new MoodleVersion(major: 4, minor: 5, patch: 0, suffix: '-dev');
        $this->assertSame('4.5.0-dev', (string) $v);
    }

    #[Test]
    public function implementsStringable(): void
    {
        $v = new MoodleVersion(major: 4, minor: 5);
        $this->assertInstanceOf(Stringable::class, $v);
    }

    #[Test]
    public function fromStringParsesMajorMinorPatch(): void
    {
        $v = MoodleVersion::from_string('4.5.2');
        $this->assertSame(4, $v->major);
        $this->assertSame(5, $v->minor);
        $this->assertSame(2, $v->patch);
        $this->assertNull($v->suffix);
    }

    #[Test]
    public function fromStringParsesMajorMinorOnly(): void
    {
        $v = MoodleVersion::from_string('4.5');
        $this->assertSame(4, $v->major);
        $this->assertSame(5, $v->minor);
        $this->assertSame(0, $v->patch);
    }

    #[Test]
    public function fromStringParsesSuffix(): void
    {
        $v = MoodleVersion::from_string('4.5.0+');
        $this->assertSame(4, $v->major);
        $this->assertSame(5, $v->minor);
        $this->assertSame(0, $v->patch);
        $this->assertSame('+', $v->suffix);
    }

    #[Test]
    public function fromStringParsesBetaSuffix(): void
    {
        $v = MoodleVersion::from_string('4.5.0-beta1');
        $this->assertSame(4, $v->major);
        $this->assertSame(5, $v->minor);
        $this->assertSame(0, $v->patch);
        $this->assertSame('-beta1', $v->suffix);
    }

    #[Test]
    public function fromStringTrimsWhitespace(): void
    {
        $v = MoodleVersion::from_string('  4.5.2  ');
        $this->assertSame(4, $v->major);
        $this->assertSame(5, $v->minor);
        $this->assertSame(2, $v->patch);
    }

    #[Test]
    public function fromStringThrowsForSingleNumber(): void
    {
        $this->expectException(MiddagValidationException::class);
        MoodleVersion::from_string('4');
    }

    #[Test]
    public function fromStringThrowsForFourParts(): void
    {
        $this->expectException(MiddagValidationException::class);
        MoodleVersion::from_string('4.5.2.1');
    }

    #[Test]
    public function compareReturnsZeroForEqualVersions(): void
    {
        $a = new MoodleVersion(4, 5, 0);
        $b = new MoodleVersion(4, 5, 0);
        $this->assertSame(0, $a->compare($b));
    }

    #[Test]
    public function compareReturnsNegativeWhenLess(): void
    {
        $a = new MoodleVersion(4, 4, 0);
        $b = new MoodleVersion(4, 5, 0);
        $this->assertLessThan(0, $a->compare($b));
    }

    #[Test]
    public function compareReturnsPositiveWhenGreater(): void
    {
        $a = new MoodleVersion(4, 5, 1);
        $b = new MoodleVersion(4, 5, 0);
        $this->assertGreaterThan(0, $a->compare($b));
    }

    #[Test]
    public function compareChecksMajorFirst(): void
    {
        $a = new MoodleVersion(5, 0, 0);
        $b = new MoodleVersion(4, 9, 9);
        $this->assertGreaterThan(0, $a->compare($b));
    }

    #[Test]
    public function compareChecksMinorSecond(): void
    {
        $a = new MoodleVersion(4, 6, 0);
        $b = new MoodleVersion(4, 5, 9);
        $this->assertGreaterThan(0, $a->compare($b));
    }

    #[Test]
    public function compareChecksPatchThird(): void
    {
        $a = new MoodleVersion(4, 5, 3);
        $b = new MoodleVersion(4, 5, 2);
        $this->assertGreaterThan(0, $a->compare($b));
    }

    #[Test]
    public function isAtLeastReturnsTrueWhenEqual(): void
    {
        $a = new MoodleVersion(4, 5, 0);
        $b = new MoodleVersion(4, 5, 0);
        $this->assertTrue($a->is_at_least($b));
    }

    #[Test]
    public function isAtLeastReturnsTrueWhenGreater(): void
    {
        $a = new MoodleVersion(4, 5, 1);
        $b = new MoodleVersion(4, 5, 0);
        $this->assertTrue($a->is_at_least($b));
    }

    #[Test]
    public function isAtLeastReturnsFalseWhenLess(): void
    {
        $a = new MoodleVersion(4, 4, 0);
        $b = new MoodleVersion(4, 5, 0);
        $this->assertFalse($a->is_at_least($b));
    }

    #[Test]
    public function isBetweenReturnsTrueWhenInRange(): void
    {
        $v = new MoodleVersion(4, 5, 0);
        $min = new MoodleVersion(4, 4, 0);
        $max = new MoodleVersion(4, 6, 0);
        $this->assertTrue($v->is_between($min, $max));
    }

    #[Test]
    public function isBetweenReturnsTrueAtBoundaries(): void
    {
        $v = new MoodleVersion(4, 4, 0);
        $min = new MoodleVersion(4, 4, 0);
        $max = new MoodleVersion(4, 6, 0);
        $this->assertTrue($v->is_between($min, $max));

        $v2 = new MoodleVersion(4, 6, 0);
        $this->assertTrue($v2->is_between($min, $max));
    }

    #[Test]
    public function isBetweenReturnsFalseWhenOutsideRange(): void
    {
        $v = new MoodleVersion(4, 3, 0);
        $min = new MoodleVersion(4, 4, 0);
        $max = new MoodleVersion(4, 6, 0);
        $this->assertFalse($v->is_between($min, $max));

        $v2 = new MoodleVersion(4, 7, 0);
        $this->assertFalse($v2->is_between($min, $max));
    }

    #[Test]
    public function equalsReturnsTrueForSameVersion(): void
    {
        $a = new MoodleVersion(4, 5, 2);
        $b = new MoodleVersion(4, 5, 2);
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentVersion(): void
    {
        $a = new MoodleVersion(4, 5, 2);
        $b = new MoodleVersion(4, 5, 3);
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function equalsIgnoresSuffixInComparison(): void
    {
        // compare() only checks major/minor/patch, not suffix
        $a = new MoodleVersion(4, 5, 0, '+');
        $b = new MoodleVersion(4, 5, 0, '-dev');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(MoodleVersion::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(MoodleVersion::class);
        $this->assertTrue($reflection->isFinal());
    }
}
