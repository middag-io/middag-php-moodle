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

use Middag\Moodle\Enum\CapabilityType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CapabilityTypeTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = CapabilityType::cases();
        $this->assertCount(2, $cases);
    }

    #[Test]
    public function readHasValueRead(): void
    {
        $this->assertSame('read', CapabilityType::READ->value);
    }

    #[Test]
    public function writeHasValueWrite(): void
    {
        $this->assertSame('write', CapabilityType::WRITE->value);
    }

    #[Test]
    public function toMoodleValueReturnsBackingValue(): void
    {
        $this->assertSame('read', CapabilityType::READ->toMoodleValue());
        $this->assertSame('write', CapabilityType::WRITE->toMoodleValue());
    }

    #[Test]
    public function canBeCreatedFromValue(): void
    {
        $this->assertSame(CapabilityType::READ, CapabilityType::from('read'));
        $this->assertSame(CapabilityType::WRITE, CapabilityType::from('write'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(CapabilityType::tryFrom('execute'));
        $this->assertNull(CapabilityType::tryFrom(''));
    }
}
