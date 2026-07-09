<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Completion;

use Middag\Moodle\Domain\Completion\Completion;
use Middag\Moodle\Domain\Completion\Enum\CompletionState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Completion::class)]
final class CompletionCoverageTest extends TestCase
{
    #[Test]
    public function getTableReturnsCourseModulesCompletion(): void
    {
        self::assertSame('course_modules_completion', Completion::getTable());
    }

    #[Test]
    public function getStateResolvesEnumFromRawValue(): void
    {
        $entity = Completion::fromRecord(['completionstate' => 2]);

        self::assertSame(CompletionState::CompletePass, $entity->getState());
    }

    #[Test]
    public function getStateDefaultsToIncompleteForUnknownValue(): void
    {
        $entity = Completion::fromRecord(['completionstate' => 99]);

        self::assertSame(CompletionState::Incomplete, $entity->getState());
    }

    #[Test]
    public function withStateStoresTheEnumValueAndReturnsSelf(): void
    {
        $entity = new Completion();

        $returned = $entity->withState(CompletionState::CompleteFail);

        self::assertSame($entity, $returned);
        self::assertSame(CompletionState::CompleteFail, $entity->getState());
    }

    #[Test]
    public function isCompleteTrueForNonIncompleteState(): void
    {
        $entity = (new Completion())->withState(CompletionState::Complete);

        self::assertTrue($entity->isComplete());
    }

    #[Test]
    public function isCompleteFalseForIncompleteState(): void
    {
        $entity = (new Completion())->withState(CompletionState::Incomplete);

        self::assertFalse($entity->isComplete());
    }

    #[Test]
    public function isOverriddenTrueWhenOverridebyPositive(): void
    {
        $entity = Completion::fromRecord(['overrideby' => 7]);

        self::assertTrue($entity->isOverridden());
    }

    #[Test]
    public function isOverriddenFalseWhenOverridebyNull(): void
    {
        $entity = Completion::fromRecord([]);

        self::assertFalse($entity->isOverridden());
    }

    #[Test]
    public function isOverriddenFalseWhenOverridebyZero(): void
    {
        $entity = Completion::fromRecord(['overrideby' => 0]);

        self::assertFalse($entity->isOverridden());
    }

    #[Test]
    public function hasViewedTrueWhenViewedPositive(): void
    {
        $entity = Completion::fromRecord(['viewed' => 1]);

        self::assertTrue($entity->hasViewed());
    }

    #[Test]
    public function hasViewedFalseWhenViewedZero(): void
    {
        $entity = Completion::fromRecord(['viewed' => 0]);

        self::assertFalse($entity->hasViewed());
    }

    #[Test]
    public function fromRecordHydratesAllFieldsUsingCoursemoduleid(): void
    {
        $entity = Completion::fromRecord([
            'id' => '5',
            'coursemoduleid' => '12',
            'userid' => '3',
            'completionstate' => '1',
            'viewed' => '1',
            'overrideby' => '9',
            'timemodified' => '1700',
        ]);

        self::assertSame(5, $entity->get_id());
        self::assertSame(12, $entity->get_coursemoduleid());
        self::assertSame(3, $entity->get_userid());
        self::assertSame(CompletionState::Complete, $entity->getState());
        self::assertTrue($entity->hasViewed());
        self::assertSame(9, $entity->get_overrideby());
        self::assertSame(1700, $entity->getTimemodified());
    }

    #[Test]
    public function fromRecordFallsBackToCmidWhenCoursemoduleidAbsent(): void
    {
        $entity = Completion::fromRecord(['cmid' => 44]);

        self::assertSame(44, $entity->get_coursemoduleid());
    }

    #[Test]
    public function fromRecordPrefersCoursemoduleidOverCmid(): void
    {
        $entity = Completion::fromRecord(['coursemoduleid' => 12, 'cmid' => 99]);

        self::assertSame(12, $entity->get_coursemoduleid());
    }

    #[Test]
    public function fromRecordLeavesOverridebyNullWhenExplicitlyNull(): void
    {
        $entity = Completion::fromRecord(['overrideby' => null]);

        self::assertNull($entity->get_overrideby());
    }

    #[Test]
    public function fromRecordLeavesDefaultsWhenFieldsAbsent(): void
    {
        $entity = Completion::fromRecord([]);

        self::assertNull($entity->getId());
        self::assertSame(0, $entity->get_coursemoduleid());
        self::assertSame(0, $entity->get_userid());
        self::assertSame(CompletionState::Incomplete, $entity->getState());
        self::assertSame(0, $entity->get_viewed());
        self::assertNull($entity->get_overrideby());
    }
}
