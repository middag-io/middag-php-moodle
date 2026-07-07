<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Course;

use Middag\Moodle\Domain\Course\CourseModule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CourseModule adds three concrete members over the entity base: the table
 * name and the isVisible()/hasCompletion() predicates. Each branch is exercised
 * by building modules through fromRecord() (no Moodle runtime needed).
 *
 * @internal
 */
#[CoversClass(CourseModule::class)]
final class CourseModuleCoverageTest extends TestCase
{
    #[Test]
    public function tableIsCourseModules(): void
    {
        $this->assertSame('course_modules', CourseModule::getTable());
    }

    #[Test]
    public function isVisibleTrueWhenVisibleAndNotBeingDeleted(): void
    {
        // Defaults: visible = 1, deletioninprogress = 0.
        $module = new CourseModule();

        $this->assertTrue($module->isVisible());
    }

    #[Test]
    public function isVisibleFalseWhenHidden(): void
    {
        $module = CourseModule::fromRecord(['visible' => 0]);

        $this->assertFalse($module->isVisible());
    }

    #[Test]
    public function isVisibleFalseWhenDeletionInProgress(): void
    {
        $module = CourseModule::fromRecord(['visible' => 1, 'deletioninprogress' => 1]);

        $this->assertFalse($module->isVisible());
    }

    #[Test]
    public function hasCompletionFalseWhenCompletionDisabled(): void
    {
        // Default completion = 0.
        $module = new CourseModule();

        $this->assertFalse($module->hasCompletion());
    }

    #[Test]
    public function hasCompletionTrueWhenCompletionTracked(): void
    {
        $module = CourseModule::fromRecord(['completion' => 2]);

        $this->assertTrue($module->hasCompletion());
    }
}
