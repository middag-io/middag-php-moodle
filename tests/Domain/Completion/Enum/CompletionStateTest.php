<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Completion\Enum;

use Middag\Moodle\Domain\Completion\Enum\CompletionState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CompletionState::class)]
final class CompletionStateTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = CompletionState::cases();
        $this->assertCount(4, $cases);
    }

    #[Test]
    public function incompleteHasValue0(): void
    {
        $this->assertSame(0, CompletionState::Incomplete->value);
    }

    #[Test]
    public function completeHasValue1(): void
    {
        $this->assertSame(1, CompletionState::Complete->value);
    }

    #[Test]
    public function completePassHasValue2(): void
    {
        $this->assertSame(2, CompletionState::CompletePass->value);
    }

    #[Test]
    public function completeFailHasValue3(): void
    {
        $this->assertSame(3, CompletionState::CompleteFail->value);
    }

    #[Test]
    public function isCompleteReturnsFalseOnlyForIncomplete(): void
    {
        $this->assertFalse(CompletionState::Incomplete->isComplete());
        $this->assertTrue(CompletionState::Complete->isComplete());
        $this->assertTrue(CompletionState::CompletePass->isComplete());
        $this->assertTrue(CompletionState::CompleteFail->isComplete());
    }

    #[Test]
    public function isPassedReturnsTrueForCompleteAndCompletePass(): void
    {
        $this->assertFalse(CompletionState::Incomplete->isPassed());
        $this->assertTrue(CompletionState::Complete->isPassed());
        $this->assertTrue(CompletionState::CompletePass->isPassed());
        $this->assertFalse(CompletionState::CompleteFail->isPassed());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Incomplete', CompletionState::Incomplete->label());
        $this->assertSame('Complete', CompletionState::Complete->label());
        $this->assertSame('Complete (Pass)', CompletionState::CompletePass->label());
        $this->assertSame('Complete (Fail)', CompletionState::CompleteFail->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(CompletionState::Incomplete, CompletionState::resolve(0));
        $this->assertSame(CompletionState::Complete, CompletionState::resolve(1));
        $this->assertSame(CompletionState::CompletePass, CompletionState::resolve(2));
        $this->assertSame(CompletionState::CompleteFail, CompletionState::resolve(3));
    }

    #[Test]
    public function resolveDefaultsToIncompleteForUnknownValue(): void
    {
        $this->assertSame(CompletionState::Incomplete, CompletionState::resolve(99));
        $this->assertSame(CompletionState::Incomplete, CompletionState::resolve(-1));
    }
}
