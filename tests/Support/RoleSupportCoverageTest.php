<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use core\context;
use dml_exception;
use Middag\Moodle\Domain\Role\RoleAssignment;
use Middag\Moodle\Support\RoleSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RoleSupport reads the global $DB (a recording double installed here), the
 * global $COURSE, and the role helper functions / context_course stub from
 * tests/stubs/support/version-user.php. DB failures are driven through the
 * double's throw flags so the wrappers' catch branches are exercised.
 *
 * @internal
 */
#[CoversClass(RoleSupport::class)]
final class RoleSupportCoverageTest extends TestCase
{
    private mixed $prevDb;

    private mixed $prevCourse;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
        $this->prevCourse = $GLOBALS['COURSE'] ?? null;

        $GLOBALS['DB'] = $this->makeDb();

        $this->clearRoleGlobals();
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
        $GLOBALS['COURSE'] = $this->prevCourse;

        $this->clearRoleGlobals();
    }

    #[Test]
    public function testGetRoleOptionsExcludesConfiguredRolesAndAppendsNone(): void
    {
        $GLOBALS['__middag_test_all_roles'] = [
            1 => (object) ['id' => 1, 'shortname' => 'manager'],
            2 => (object) ['id' => 2, 'shortname' => 'guest'],
            3 => (object) ['id' => 3, 'shortname' => 'student'],
        ];

        $result = RoleSupport::getRoleOptions();

        // "guest" is excluded; the none placeholder is prepended, then reversed.
        self::assertArrayNotHasKey(2, $result);
        self::assertSame('-- [/none] --', $result[0]);
        self::assertSame('manager', $result[1]);
        self::assertSame('student', $result[3]);
    }

    #[Test]
    public function testGetRoleOptionsUsesAssignableRolesForACourseContext(): void
    {
        $GLOBALS['__middag_test_all_roles'] = [
            1 => (object) ['id' => 1, 'shortname' => 'manager'],
        ];
        $GLOBALS['__middag_test_assignable_roles'] = [2 => 'Teacher', 3 => 'Student'];

        $result = RoleSupport::getRoleOptions(new context(5));

        self::assertSame('-- [/none] --', $result[0]);
        self::assertSame('Teacher', $result[2]);
        self::assertSame('Student', $result[3]);
    }

    #[Test]
    public function testGetRoleOptionsExcludesRolesEvenForACourseContext(): void
    {
        // get_assignable_roles() replaces the filtered list, so the exclusion
        // must be re-applied by id — otherwise an excluded role reappears in a
        // course-context picker.
        $GLOBALS['__middag_test_all_roles'] = [
            1 => (object) ['id' => 1, 'shortname' => 'manager'],
            2 => (object) ['id' => 2, 'shortname' => 'editingteacher'],
        ];
        $GLOBALS['__middag_test_assignable_roles'] = [1 => 'Manager', 2 => 'Teacher'];

        $result = RoleSupport::getRoleOptions(new context(5), ['manager']);

        self::assertArrayNotHasKey(1, $result);
        self::assertSame('Teacher', $result[2]);
    }

    #[Test]
    public function testGetRolesOptionsUsesTheCurrentCourseContext(): void
    {
        $GLOBALS['COURSE'] = (object) ['id' => 7];
        $GLOBALS['__middag_test_assignable_roles'] = [2 => 'Teacher'];

        $result = RoleSupport::getRolesOptions();

        self::assertSame('[/none]', $result[0]);
        self::assertSame('Teacher', $result[2]);
    }

    #[Test]
    public function testIsteacherReturnsTrueWhenTheQueryMatches(): void
    {
        $GLOBALS['__middag_test_record_exists'] = true;

        self::assertTrue(RoleSupport::isteacher(5));
    }

    #[Test]
    public function testIsteacherReturnsFalseWhenTheQueryThrows(): void
    {
        $GLOBALS['__middag_test_throw_record_exists_sql'] = true;

        self::assertFalse(RoleSupport::isteacher(5));
    }

    #[Test]
    public function testGetTeacherReturnsTheEditingTeacherRecord(): void
    {
        $GLOBALS['__middag_test_role_records']['editingteacher'] = (object) ['id' => 3, 'shortname' => 'editingteacher'];
        $GLOBALS['__middag_test_role_users'] = [(object) ['userid' => 5]];

        $teacher = RoleSupport::getTeacher(7);

        self::assertIsObject($teacher);
        self::assertSame(5, $teacher->id);
    }

    #[Test]
    public function testGetTeacherFallsBackToTheTeacherRole(): void
    {
        // No editingteacher record -> the wrapper retries with 'teacher'.
        $GLOBALS['__middag_test_role_records']['teacher'] = (object) ['id' => 4, 'shortname' => 'teacher'];
        $GLOBALS['__middag_test_role_users'] = [(object) ['userid' => 6]];

        $teacher = RoleSupport::getTeacher(7);

        self::assertIsObject($teacher);
        self::assertSame(6, $teacher->id);
    }

    #[Test]
    public function testGetTeacherReturnsFalseWhenNoTeacherRoleExists(): void
    {
        self::assertFalse(RoleSupport::getTeacher(7));
    }

    #[Test]
    public function testGetTeacherReturnsFalseWhenNoAssignmentsExist(): void
    {
        $GLOBALS['__middag_test_role_records']['editingteacher'] = (object) ['id' => 3, 'shortname' => 'editingteacher'];
        $GLOBALS['__middag_test_role_users'] = [];

        self::assertFalse(RoleSupport::getTeacher(7));
    }

    #[Test]
    public function testGetTeacherReturnsFalseWhenTheAssignmentHasNoUserId(): void
    {
        $GLOBALS['__middag_test_role_records']['editingteacher'] = (object) ['id' => 3, 'shortname' => 'editingteacher'];
        $GLOBALS['__middag_test_role_users'] = [(object) ['other' => 1]];

        self::assertFalse(RoleSupport::getTeacher(7));
    }

    #[Test]
    public function testGetTeacherReturnsFalseWhenTheUserLookupThrows(): void
    {
        // A non-numeric userid makes Typing::toInt() throw inside the try block.
        $GLOBALS['__middag_test_role_records']['editingteacher'] = (object) ['id' => 3, 'shortname' => 'editingteacher'];
        $GLOBALS['__middag_test_role_users'] = [(object) ['userid' => 'not-numeric']];

        self::assertFalse(RoleSupport::getTeacher(7));
    }

    #[Test]
    public function testGetTeacherAssignmentReturnsAnAssignment(): void
    {
        $GLOBALS['__middag_test_role_assignment_record'] = (object) [
            'id' => 1,
            'roleid' => 3,
            'contextid' => 7,
            'userid' => 5,
        ];

        $assignment = RoleSupport::getTeacherAssignment(7);

        self::assertInstanceOf(RoleAssignment::class, $assignment);
        self::assertSame(5, $assignment->get_userid());
    }

    #[Test]
    public function testGetTeacherAssignmentReturnsNullWhenTheContextIsMissing(): void
    {
        $GLOBALS['__middag_test_context_course_instance'] = false;

        self::assertNull(RoleSupport::getTeacherAssignment(7));
    }

    #[Test]
    public function testGetTeacherAssignmentReturnsNullWhenNoRecordMatches(): void
    {
        self::assertNull(RoleSupport::getTeacherAssignment(7));
    }

    #[Test]
    public function testGetTeacherAssignmentReturnsNullWhenTheQueryThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_record_sql'] = true;

        self::assertNull(RoleSupport::getTeacherAssignment(7));
    }

    #[Test]
    public function testGetTeachersReturnsAssignmentsKeyedByUserId(): void
    {
        $GLOBALS['__middag_test_role_assignment_records'] = [
            (object) ['id' => 1, 'roleid' => 3, 'contextid' => 7, 'userid' => 5],
            (object) ['id' => 2, 'roleid' => 3, 'contextid' => 7, 'userid' => 6],
        ];

        $teachers = RoleSupport::getTeachers(7);

        self::assertCount(2, $teachers);
        self::assertArrayHasKey(5, $teachers);
        self::assertArrayHasKey(6, $teachers);
        self::assertInstanceOf(RoleAssignment::class, $teachers[5]);
    }

    #[Test]
    public function testGetTeachersReturnsEmptyWhenTheContextIsMissing(): void
    {
        $GLOBALS['__middag_test_context_course_instance'] = false;

        self::assertSame([], RoleSupport::getTeachers(7));
    }

    #[Test]
    public function testGetTeachersReturnsEmptyWhenTheQueryThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_records_sql'] = true;

        self::assertSame([], RoleSupport::getTeachers(7));
    }

    private function makeDb(): object
    {
        return new class {
            public function record_exists_sql($sql, ?array $params = null): bool
            {
                if (!empty($GLOBALS['__middag_test_throw_record_exists_sql'])) {
                    throw new dml_exception('recordexistsfailed');
                }

                return $GLOBALS['__middag_test_record_exists'] ?? false;
            }

            public function get_record($table, ?array $conditions = null, $fields = '*', $strictness = 0)
            {
                $shortname = $conditions['shortname'] ?? null;

                return $GLOBALS['__middag_test_role_records'][$shortname] ?? false;
            }

            public function get_record_sql($sql, ?array $params = null, $strictness = 0)
            {
                if (!empty($GLOBALS['__middag_test_throw_get_record_sql'])) {
                    throw new dml_exception('getrecordfailed');
                }

                return $GLOBALS['__middag_test_role_assignment_record'] ?? false;
            }

            public function get_records_sql($sql, ?array $params = null, $limitfrom = 0, $limitnum = 0): array
            {
                if (!empty($GLOBALS['__middag_test_throw_get_records_sql'])) {
                    throw new dml_exception('getrecordsfailed');
                }

                return $GLOBALS['__middag_test_role_assignment_records'] ?? [];
            }
        };
    }

    private function clearRoleGlobals(): void
    {
        unset(
            $GLOBALS['__middag_test_all_roles'],
            $GLOBALS['__middag_test_assignable_roles'],
            $GLOBALS['__middag_test_role_names'],
            $GLOBALS['__middag_test_role_records'],
            $GLOBALS['__middag_test_role_users'],
            $GLOBALS['__middag_test_record_exists'],
            $GLOBALS['__middag_test_throw_record_exists_sql'],
            $GLOBALS['__middag_test_role_assignment_record'],
            $GLOBALS['__middag_test_throw_get_record_sql'],
            $GLOBALS['__middag_test_role_assignment_records'],
            $GLOBALS['__middag_test_throw_get_records_sql'],
            $GLOBALS['__middag_test_context_course_instance'],
        );
    }
}
