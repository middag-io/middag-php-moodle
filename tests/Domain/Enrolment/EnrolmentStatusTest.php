<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Enrolment;

use Middag\Moodle\Domain\Enrolment\EnrolmentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EnrolmentStatus::class)]
final class EnrolmentStatusTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = EnrolmentStatus::cases();
        $this->assertCount(2, $cases);
    }

    #[Test]
    public function activeHasValue0(): void
    {
        $this->assertSame(0, EnrolmentStatus::Active->value);
    }

    #[Test]
    public function suspendedHasValue1(): void
    {
        $this->assertSame(1, EnrolmentStatus::Suspended->value);
    }

    #[Test]
    public function toMoodleValueReturnsBackingValue(): void
    {
        $this->assertSame(0, EnrolmentStatus::Active->toMoodleValue());
        $this->assertSame(1, EnrolmentStatus::Suspended->toMoodleValue());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Active', EnrolmentStatus::Active->label());
        $this->assertSame('Suspended', EnrolmentStatus::Suspended->label());
    }

    #[Test]
    public function isActiveReturnsTrueOnlyForActive(): void
    {
        $this->assertTrue(EnrolmentStatus::Active->isActive());
        $this->assertFalse(EnrolmentStatus::Suspended->isActive());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(EnrolmentStatus::Active, EnrolmentStatus::resolve(0));
        $this->assertSame(EnrolmentStatus::Suspended, EnrolmentStatus::resolve(1));
    }

    #[Test]
    public function resolveDefaultsToActiveForUnknownValue(): void
    {
        $this->assertSame(EnrolmentStatus::Active, EnrolmentStatus::resolve(99));
        $this->assertSame(EnrolmentStatus::Active, EnrolmentStatus::resolve(-1));
    }

    #[Test]
    public function canBeCreatedFromValue(): void
    {
        $this->assertSame(EnrolmentStatus::Active, EnrolmentStatus::from(0));
        $this->assertSame(EnrolmentStatus::Suspended, EnrolmentStatus::from(1));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(EnrolmentStatus::tryFrom(2));
        $this->assertNull(EnrolmentStatus::tryFrom(-1));
    }
}
