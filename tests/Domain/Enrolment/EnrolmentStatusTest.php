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

use Middag\Moodle\Domain\Enrolment\EnrolmentStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
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
        $this->assertSame(0, EnrolmentStatus::ACTIVE->value);
    }

    #[Test]
    public function suspendedHasValue1(): void
    {
        $this->assertSame(1, EnrolmentStatus::SUSPENDED->value);
    }

    #[Test]
    public function toMoodleValueReturnsBackingValue(): void
    {
        $this->assertSame(0, EnrolmentStatus::ACTIVE->toMoodleValue());
        $this->assertSame(1, EnrolmentStatus::SUSPENDED->toMoodleValue());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Active', EnrolmentStatus::ACTIVE->label());
        $this->assertSame('Suspended', EnrolmentStatus::SUSPENDED->label());
    }

    #[Test]
    public function isActiveReturnsTrueOnlyForActive(): void
    {
        $this->assertTrue(EnrolmentStatus::ACTIVE->isActive());
        $this->assertFalse(EnrolmentStatus::SUSPENDED->isActive());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(EnrolmentStatus::ACTIVE, EnrolmentStatus::resolve(0));
        $this->assertSame(EnrolmentStatus::SUSPENDED, EnrolmentStatus::resolve(1));
    }

    #[Test]
    public function resolveDefaultsToActiveForUnknownValue(): void
    {
        $this->assertSame(EnrolmentStatus::ACTIVE, EnrolmentStatus::resolve(99));
        $this->assertSame(EnrolmentStatus::ACTIVE, EnrolmentStatus::resolve(-1));
    }

    #[Test]
    public function canBeCreatedFromValue(): void
    {
        $this->assertSame(EnrolmentStatus::ACTIVE, EnrolmentStatus::from(0));
        $this->assertSame(EnrolmentStatus::SUSPENDED, EnrolmentStatus::from(1));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(EnrolmentStatus::tryFrom(2));
        $this->assertNull(EnrolmentStatus::tryFrom(-1));
    }
}
