<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Task\Enum;

use Middag\Moodle\Domain\Task\Enum\TaskRunState;
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
        self::assertSame('none', TaskRunState::None->value);
        self::assertSame('running', TaskRunState::Running->value);
        self::assertSame('failed', TaskRunState::Failed->value);
    }

    #[Test]
    public function testIsActiveIsTrueOnlyForRunning(): void
    {
        self::assertTrue(TaskRunState::Running->isActive());
    }

    #[Test]
    public function testIsActiveIsFalseForNonRunningStates(): void
    {
        self::assertFalse(TaskRunState::None->isActive());
        self::assertFalse(TaskRunState::Failed->isActive());
    }

    #[Test]
    public function testLabelCoversEveryMatchArm(): void
    {
        self::assertSame('Idle', TaskRunState::None->label());
        self::assertSame('Running', TaskRunState::Running->label());
        self::assertSame('Failed', TaskRunState::Failed->label());
    }

    #[Test]
    public function testResolveReturnsTheMatchingCaseForAKnownValue(): void
    {
        self::assertSame(TaskRunState::Failed, TaskRunState::resolve('failed'));
    }

    #[Test]
    public function testResolveFallsBackToNoneForAnUnknownValue(): void
    {
        self::assertSame(TaskRunState::None, TaskRunState::resolve('bogus'));
    }
}
