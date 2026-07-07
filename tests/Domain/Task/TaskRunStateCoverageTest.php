<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Task;

use Middag\Moodle\Domain\Task\TaskRunState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TaskRunState is a backed enum wrapping Moodle task execution states.
 *
 * @internal
 */
#[CoversClass(TaskRunState::class)]
final class TaskRunStateCoverageTest extends TestCase
{
    #[Test]
    public function testBackingValuesAreStable(): void
    {
        self::assertSame('none', TaskRunState::NONE->value);
        self::assertSame('running', TaskRunState::RUNNING->value);
        self::assertSame('failed', TaskRunState::FAILED->value);
    }

    #[Test]
    public function testIsActiveIsTrueOnlyForRunning(): void
    {
        self::assertTrue(TaskRunState::RUNNING->isActive());
    }

    #[Test]
    public function testIsActiveIsFalseForNonRunningStates(): void
    {
        self::assertFalse(TaskRunState::NONE->isActive());
        self::assertFalse(TaskRunState::FAILED->isActive());
    }

    #[Test]
    public function testLabelCoversEveryMatchArm(): void
    {
        self::assertSame('Idle', TaskRunState::NONE->label());
        self::assertSame('Running', TaskRunState::RUNNING->label());
        self::assertSame('Failed', TaskRunState::FAILED->label());
    }

    #[Test]
    public function testResolveReturnsTheMatchingCaseForAKnownValue(): void
    {
        self::assertSame(TaskRunState::FAILED, TaskRunState::resolve('failed'));
    }

    #[Test]
    public function testResolveFallsBackToNoneForAnUnknownValue(): void
    {
        self::assertSame(TaskRunState::NONE, TaskRunState::resolve('bogus'));
    }
}
