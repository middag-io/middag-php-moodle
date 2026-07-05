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
        $this->assertSame('manager', RoleArchetype::MANAGER->value);
    }

    #[Test]
    public function courseCreatorHasValueCoursecreator(): void
    {
        $this->assertSame('coursecreator', RoleArchetype::COURSE_CREATOR->value);
    }

    #[Test]
    public function editingTeacherHasValueEditingteacher(): void
    {
        $this->assertSame('editingteacher', RoleArchetype::EDITING_TEACHER->value);
    }

    #[Test]
    public function teacherHasValueTeacher(): void
    {
        $this->assertSame('teacher', RoleArchetype::TEACHER->value);
    }

    #[Test]
    public function studentHasValueStudent(): void
    {
        $this->assertSame('student', RoleArchetype::STUDENT->value);
    }

    #[Test]
    public function guestHasValueGuest(): void
    {
        $this->assertSame('guest', RoleArchetype::GUEST->value);
    }

    #[Test]
    public function userHasValueUser(): void
    {
        $this->assertSame('user', RoleArchetype::USER->value);
    }

    #[Test]
    public function frontpageHasValueFrontpage(): void
    {
        $this->assertSame('frontpage', RoleArchetype::FRONTPAGE->value);
    }

    #[Test]
    public function isTeacherLikeReturnsTrueForTeacherRoles(): void
    {
        $this->assertTrue(RoleArchetype::EDITING_TEACHER->isTeacherLike());
        $this->assertTrue(RoleArchetype::TEACHER->isTeacherLike());
    }

    #[Test]
    public function isTeacherLikeReturnsFalseForNonTeacherRoles(): void
    {
        $this->assertFalse(RoleArchetype::MANAGER->isTeacherLike());
        $this->assertFalse(RoleArchetype::COURSE_CREATOR->isTeacherLike());
        $this->assertFalse(RoleArchetype::STUDENT->isTeacherLike());
        $this->assertFalse(RoleArchetype::GUEST->isTeacherLike());
        $this->assertFalse(RoleArchetype::USER->isTeacherLike());
        $this->assertFalse(RoleArchetype::FRONTPAGE->isTeacherLike());
    }

    #[Test]
    public function isAdminLikeReturnsTrueForAdminRoles(): void
    {
        $this->assertTrue(RoleArchetype::MANAGER->isAdminLike());
        $this->assertTrue(RoleArchetype::COURSE_CREATOR->isAdminLike());
    }

    #[Test]
    public function isAdminLikeReturnsFalseForNonAdminRoles(): void
    {
        $this->assertFalse(RoleArchetype::EDITING_TEACHER->isAdminLike());
        $this->assertFalse(RoleArchetype::TEACHER->isAdminLike());
        $this->assertFalse(RoleArchetype::STUDENT->isAdminLike());
        $this->assertFalse(RoleArchetype::GUEST->isAdminLike());
        $this->assertFalse(RoleArchetype::USER->isAdminLike());
        $this->assertFalse(RoleArchetype::FRONTPAGE->isAdminLike());
    }

    #[Test]
    public function isLearnerReturnsTrueOnlyForStudent(): void
    {
        $this->assertTrue(RoleArchetype::STUDENT->isLearner());
        $this->assertFalse(RoleArchetype::MANAGER->isLearner());
        $this->assertFalse(RoleArchetype::TEACHER->isLearner());
        $this->assertFalse(RoleArchetype::GUEST->isLearner());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Manager', RoleArchetype::MANAGER->label());
        $this->assertSame('Course creator', RoleArchetype::COURSE_CREATOR->label());
        $this->assertSame('Editing teacher', RoleArchetype::EDITING_TEACHER->label());
        $this->assertSame('Non-editing teacher', RoleArchetype::TEACHER->label());
        $this->assertSame('Student', RoleArchetype::STUDENT->label());
        $this->assertSame('Guest', RoleArchetype::GUEST->label());
        $this->assertSame('Authenticated user', RoleArchetype::USER->label());
        $this->assertSame('Frontpage user', RoleArchetype::FRONTPAGE->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(RoleArchetype::MANAGER, RoleArchetype::resolve('manager'));
        $this->assertSame(RoleArchetype::STUDENT, RoleArchetype::resolve('student'));
        $this->assertSame(RoleArchetype::GUEST, RoleArchetype::resolve('guest'));
    }

    #[Test]
    public function resolveDefaultsToUserForUnknownValue(): void
    {
        $this->assertSame(RoleArchetype::USER, RoleArchetype::resolve('unknown'));
        $this->assertSame(RoleArchetype::USER, RoleArchetype::resolve(''));
        $this->assertSame(RoleArchetype::USER, RoleArchetype::resolve('admin'));
    }
}
