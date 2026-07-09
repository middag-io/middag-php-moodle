<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Security\Enum;

use Middag\Moodle\Security\Enum\CapabilityRisk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CapabilityRisk is a pure int-backed enum wrapping Moodle's RISK_* bitmask
 * constants. The only reachable behaviour is toMoodleValue(), which returns the
 * backing value; the remaining assertions pin the case set and the documented
 * bitwise-OR composability so the RISK_* mapping cannot drift silently.
 *
 * @internal
 */
#[CoversClass(CapabilityRisk::class)]
final class CapabilityRiskCoverageTest extends TestCase
{
    #[Test]
    public function allFiveCasesExist(): void
    {
        $this->assertCount(5, CapabilityRisk::cases());
    }

    #[Test]
    public function eachCaseBacksItsMoodleRiskConstant(): void
    {
        $this->assertSame(1, CapabilityRisk::Spam->value);
        $this->assertSame(2, CapabilityRisk::Personal->value);
        $this->assertSame(4, CapabilityRisk::Xss->value);
        $this->assertSame(8, CapabilityRisk::Config->value);
        $this->assertSame(16, CapabilityRisk::Dataloss->value);
    }

    #[Test]
    public function toMoodleValueReturnsBackingValueForSpam(): void
    {
        $risk = CapabilityRisk::Spam;

        $this->assertSame(1, $risk->toMoodleValue());
    }

    #[Test]
    public function toMoodleValueReturnsBackingValueForPersonal(): void
    {
        $risk = CapabilityRisk::Personal;

        $this->assertSame(2, $risk->toMoodleValue());
    }

    #[Test]
    public function toMoodleValueReturnsBackingValueForXss(): void
    {
        $risk = CapabilityRisk::Xss;

        $this->assertSame(4, $risk->toMoodleValue());
    }

    #[Test]
    public function toMoodleValueReturnsBackingValueForConfig(): void
    {
        $risk = CapabilityRisk::Config;

        $this->assertSame(8, $risk->toMoodleValue());
    }

    #[Test]
    public function toMoodleValueReturnsBackingValueForDataloss(): void
    {
        $risk = CapabilityRisk::Dataloss;

        $this->assertSame(16, $risk->toMoodleValue());
    }

    #[Test]
    public function toMoodleValueEqualsBackingValueForEveryCase(): void
    {
        foreach (CapabilityRisk::cases() as $risk) {
            $this->assertSame($risk->value, $risk->toMoodleValue());
        }
    }

    #[Test]
    public function moodleValuesAreComposableViaBitwiseOr(): void
    {
        $mask = CapabilityRisk::Xss->toMoodleValue() | CapabilityRisk::Config->toMoodleValue();

        // 4 | 8 = 12; distinct power-of-two bits so the composed mask is lossless.
        $this->assertSame(12, $mask);
        $this->assertSame(CapabilityRisk::Xss->value, $mask & CapabilityRisk::Xss->value);
        $this->assertSame(CapabilityRisk::Config->value, $mask & CapabilityRisk::Config->value);
        $this->assertSame(0, $mask & CapabilityRisk::Spam->value);
    }

    #[Test]
    public function canBeCreatedFromBackingValue(): void
    {
        $this->assertSame(CapabilityRisk::Dataloss, CapabilityRisk::from(16));
    }

    #[Test]
    public function tryFromReturnsNullForUnknownValue(): void
    {
        $this->assertNull(CapabilityRisk::tryFrom(3));
        $this->assertNull(CapabilityRisk::tryFrom(0));
    }
}
