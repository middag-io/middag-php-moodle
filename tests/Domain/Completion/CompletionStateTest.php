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

use Middag\Moodle\Domain\Completion\CompletionState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
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
        $this->assertSame(0, CompletionState::INCOMPLETE->value);
    }

    #[Test]
    public function completeHasValue1(): void
    {
        $this->assertSame(1, CompletionState::COMPLETE->value);
    }

    #[Test]
    public function completePassHasValue2(): void
    {
        $this->assertSame(2, CompletionState::COMPLETE_PASS->value);
    }

    #[Test]
    public function completeFailHasValue3(): void
    {
        $this->assertSame(3, CompletionState::COMPLETE_FAIL->value);
    }

    #[Test]
    public function isCompleteReturnsFalseOnlyForIncomplete(): void
    {
        $this->assertFalse(CompletionState::INCOMPLETE->isComplete());
        $this->assertTrue(CompletionState::COMPLETE->isComplete());
        $this->assertTrue(CompletionState::COMPLETE_PASS->isComplete());
        $this->assertTrue(CompletionState::COMPLETE_FAIL->isComplete());
    }

    #[Test]
    public function isPassedReturnsTrueForCompleteAndCompletePass(): void
    {
        $this->assertFalse(CompletionState::INCOMPLETE->isPassed());
        $this->assertTrue(CompletionState::COMPLETE->isPassed());
        $this->assertTrue(CompletionState::COMPLETE_PASS->isPassed());
        $this->assertFalse(CompletionState::COMPLETE_FAIL->isPassed());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Incomplete', CompletionState::INCOMPLETE->label());
        $this->assertSame('Complete', CompletionState::COMPLETE->label());
        $this->assertSame('Complete (Pass)', CompletionState::COMPLETE_PASS->label());
        $this->assertSame('Complete (Fail)', CompletionState::COMPLETE_FAIL->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(CompletionState::INCOMPLETE, CompletionState::resolve(0));
        $this->assertSame(CompletionState::COMPLETE, CompletionState::resolve(1));
        $this->assertSame(CompletionState::COMPLETE_PASS, CompletionState::resolve(2));
        $this->assertSame(CompletionState::COMPLETE_FAIL, CompletionState::resolve(3));
    }

    #[Test]
    public function resolveDefaultsToIncompleteForUnknownValue(): void
    {
        $this->assertSame(CompletionState::INCOMPLETE, CompletionState::resolve(99));
        $this->assertSame(CompletionState::INCOMPLETE, CompletionState::resolve(-1));
    }
}
