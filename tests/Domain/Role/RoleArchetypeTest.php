<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Role;

use Middag\Moodle\Domain\Role\RoleArchetype;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RoleArchetype::class)]
final class RoleArchetypeTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = RoleArchetype::cases();
        $this->assertCount(8, $cases);
    }

    #[Test]
    public function managerHasValueManager(): void
    {
        $this->assertSame('manager', RoleArchetype::Manager->value);
    }

    #[Test]
    public function courseCreatorHasValueCoursecreator(): void
    {
        $this->assertSame('coursecreator', RoleArchetype::CourseCreator->value);
    }

    #[Test]
    public function editingTeacherHasValueEditingteacher(): void
    {
        $this->assertSame('editingteacher', RoleArchetype::EditingTeacher->value);
    }

    #[Test]
    public function teacherHasValueTeacher(): void
    {
        $this->assertSame('teacher', RoleArchetype::Teacher->value);
    }

    #[Test]
    public function studentHasValueStudent(): void
    {
        $this->assertSame('student', RoleArchetype::Student->value);
    }

    #[Test]
    public function guestHasValueGuest(): void
    {
        $this->assertSame('guest', RoleArchetype::Guest->value);
    }

    #[Test]
    public function userHasValueUser(): void
    {
        $this->assertSame('user', RoleArchetype::User->value);
    }

    #[Test]
    public function frontpageHasValueFrontpage(): void
    {
        $this->assertSame('frontpage', RoleArchetype::Frontpage->value);
    }

    #[Test]
    public function isTeacherLikeReturnsTrueForTeacherRoles(): void
    {
        $this->assertTrue(RoleArchetype::EditingTeacher->isTeacherLike());
        $this->assertTrue(RoleArchetype::Teacher->isTeacherLike());
    }

    #[Test]
    public function isTeacherLikeReturnsFalseForNonTeacherRoles(): void
    {
        $this->assertFalse(RoleArchetype::Manager->isTeacherLike());
        $this->assertFalse(RoleArchetype::CourseCreator->isTeacherLike());
        $this->assertFalse(RoleArchetype::Student->isTeacherLike());
        $this->assertFalse(RoleArchetype::Guest->isTeacherLike());
        $this->assertFalse(RoleArchetype::User->isTeacherLike());
        $this->assertFalse(RoleArchetype::Frontpage->isTeacherLike());
    }

    #[Test]
    public function isAdminLikeReturnsTrueForAdminRoles(): void
    {
        $this->assertTrue(RoleArchetype::Manager->isAdminLike());
        $this->assertTrue(RoleArchetype::CourseCreator->isAdminLike());
    }

    #[Test]
    public function isAdminLikeReturnsFalseForNonAdminRoles(): void
    {
        $this->assertFalse(RoleArchetype::EditingTeacher->isAdminLike());
        $this->assertFalse(RoleArchetype::Teacher->isAdminLike());
        $this->assertFalse(RoleArchetype::Student->isAdminLike());
        $this->assertFalse(RoleArchetype::Guest->isAdminLike());
        $this->assertFalse(RoleArchetype::User->isAdminLike());
        $this->assertFalse(RoleArchetype::Frontpage->isAdminLike());
    }

    #[Test]
    public function isLearnerReturnsTrueOnlyForStudent(): void
    {
        $this->assertTrue(RoleArchetype::Student->isLearner());
        $this->assertFalse(RoleArchetype::Manager->isLearner());
        $this->assertFalse(RoleArchetype::Teacher->isLearner());
        $this->assertFalse(RoleArchetype::Guest->isLearner());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Manager', RoleArchetype::Manager->label());
        $this->assertSame('Course creator', RoleArchetype::CourseCreator->label());
        $this->assertSame('Editing teacher', RoleArchetype::EditingTeacher->label());
        $this->assertSame('Non-editing teacher', RoleArchetype::Teacher->label());
        $this->assertSame('Student', RoleArchetype::Student->label());
        $this->assertSame('Guest', RoleArchetype::Guest->label());
        $this->assertSame('Authenticated user', RoleArchetype::User->label());
        $this->assertSame('Frontpage user', RoleArchetype::Frontpage->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(RoleArchetype::Manager, RoleArchetype::resolve('manager'));
        $this->assertSame(RoleArchetype::Student, RoleArchetype::resolve('student'));
        $this->assertSame(RoleArchetype::Guest, RoleArchetype::resolve('guest'));
    }

    #[Test]
    public function resolveDefaultsToUserForUnknownValue(): void
    {
        $this->assertSame(RoleArchetype::User, RoleArchetype::resolve('unknown'));
        $this->assertSame(RoleArchetype::User, RoleArchetype::resolve(''));
        $this->assertSame(RoleArchetype::User, RoleArchetype::resolve('admin'));
    }
}
