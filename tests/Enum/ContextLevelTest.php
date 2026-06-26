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

use Middag\Moodle\Enum\ContextLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ContextLevelTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = ContextLevel::cases();
        $this->assertCount(6, $cases);
    }

    #[Test]
    public function systemHasValue10(): void
    {
        $this->assertSame(10, ContextLevel::SYSTEM->value);
    }

    #[Test]
    public function userHasValue30(): void
    {
        $this->assertSame(30, ContextLevel::USER->value);
    }

    #[Test]
    public function coursecatHasValue40(): void
    {
        $this->assertSame(40, ContextLevel::COURSECAT->value);
    }

    #[Test]
    public function courseHasValue50(): void
    {
        $this->assertSame(50, ContextLevel::COURSE->value);
    }

    #[Test]
    public function moduleHasValue70(): void
    {
        $this->assertSame(70, ContextLevel::MODULE->value);
    }

    #[Test]
    public function blockHasValue80(): void
    {
        $this->assertSame(80, ContextLevel::BLOCK->value);
    }

    #[Test]
    public function toMoodleValueReturnsBackingValue(): void
    {
        foreach (ContextLevel::cases() as $case) {
            $this->assertSame($case->value, $case->toMoodleValue());
        }
    }

    #[Test]
    public function canBeCreatedFromValue(): void
    {
        $this->assertSame(ContextLevel::SYSTEM, ContextLevel::from(10));
        $this->assertSame(ContextLevel::USER, ContextLevel::from(30));
        $this->assertSame(ContextLevel::COURSECAT, ContextLevel::from(40));
        $this->assertSame(ContextLevel::COURSE, ContextLevel::from(50));
        $this->assertSame(ContextLevel::MODULE, ContextLevel::from(70));
        $this->assertSame(ContextLevel::BLOCK, ContextLevel::from(80));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(ContextLevel::tryFrom(0));
        $this->assertNull(ContextLevel::tryFrom(99));
    }
}
