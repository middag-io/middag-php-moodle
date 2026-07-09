<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Context;

use Middag\Moodle\Domain\Context\ContextLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ContextLevel::class)]
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
        $this->assertSame(10, ContextLevel::System->value);
    }

    #[Test]
    public function userHasValue30(): void
    {
        $this->assertSame(30, ContextLevel::User->value);
    }

    #[Test]
    public function coursecatHasValue40(): void
    {
        $this->assertSame(40, ContextLevel::Coursecat->value);
    }

    #[Test]
    public function courseHasValue50(): void
    {
        $this->assertSame(50, ContextLevel::Course->value);
    }

    #[Test]
    public function moduleHasValue70(): void
    {
        $this->assertSame(70, ContextLevel::Module->value);
    }

    #[Test]
    public function blockHasValue80(): void
    {
        $this->assertSame(80, ContextLevel::Block->value);
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
        $this->assertSame(ContextLevel::System, ContextLevel::from(10));
        $this->assertSame(ContextLevel::User, ContextLevel::from(30));
        $this->assertSame(ContextLevel::Coursecat, ContextLevel::from(40));
        $this->assertSame(ContextLevel::Course, ContextLevel::from(50));
        $this->assertSame(ContextLevel::Module, ContextLevel::from(70));
        $this->assertSame(ContextLevel::Block, ContextLevel::from(80));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(ContextLevel::tryFrom(0));
        $this->assertNull(ContextLevel::tryFrom(99));
    }

    #[Test]
    public function fromStringResolvesShortNames(): void
    {
        $this->assertSame(ContextLevel::System, ContextLevel::fromString('system'));
        $this->assertSame(ContextLevel::User, ContextLevel::fromString('user'));
        $this->assertSame(ContextLevel::Coursecat, ContextLevel::fromString('coursecat'));
        $this->assertSame(ContextLevel::Coursecat, ContextLevel::fromString('category'));
        $this->assertSame(ContextLevel::Course, ContextLevel::fromString('course'));
        $this->assertSame(ContextLevel::Module, ContextLevel::fromString('module'));
        $this->assertSame(ContextLevel::Module, ContextLevel::fromString('coursemodule'));
        $this->assertSame(ContextLevel::Module, ContextLevel::fromString('cm'));
        $this->assertSame(ContextLevel::Block, ContextLevel::fromString('block'));
    }

    #[Test]
    public function fromStringIsCaseInsensitiveAndAcceptsMoodleConstantSpelling(): void
    {
        $this->assertSame(ContextLevel::Course, ContextLevel::fromString('COURSE'));
        $this->assertSame(ContextLevel::Course, ContextLevel::fromString('  Course  '));
        $this->assertSame(ContextLevel::Course, ContextLevel::fromString('CONTEXT_COURSE'));
        $this->assertSame(ContextLevel::Module, ContextLevel::fromString('context_module'));
    }

    #[Test]
    public function fromStringReturnsNullForUnknownOrNull(): void
    {
        $this->assertNull(ContextLevel::fromString('not-a-context'));
        $this->assertNull(ContextLevel::fromString(''));
        $this->assertNull(ContextLevel::fromString(null));
    }
}
