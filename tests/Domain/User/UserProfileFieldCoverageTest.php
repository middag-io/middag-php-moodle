<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\User;

use Middag\Moodle\Domain\User\UserProfileField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UserProfileField::class)]
final class UserProfileFieldCoverageTest extends TestCase
{
    #[Test]
    public function getTableReturnsMoodleTableName(): void
    {
        self::assertSame('user_info_field', UserProfileField::getTable());
    }

    #[Test]
    public function fromRecordCastsScalarsAndNullableStringsFromArray(): void
    {
        $field = UserProfileField::fromRecord([
            'id' => '7',
            'categoryid' => '3',
            'sortorder' => '2',
            'required' => '1',
            'locked' => '1',
            'visible' => '2',
            'forceunique' => '1',
            'signup' => '1',
            'descriptionformat' => '1',
            'defaultdataformat' => '0',
            'shortname' => 'department',
            'name' => 'Department',
            'datatype' => 'text',
            'description' => 'Your department',
            'defaultdata' => 'Engineering',
            'param1' => 'p1',
            'param2' => 'p2',
            'param3' => 'p3',
            'param4' => 'p4',
            'param5' => 'p5',
        ]);

        // Scalar casts (strings from DB coerced to declared types).
        self::assertSame(7, $field->getId());
        self::assertSame(3, $field->get_categoryid());
        self::assertSame(2, $field->get_sortorder());
        self::assertSame('department', $field->get_shortname());
        self::assertSame('Department', $field->get_name());
        self::assertSame('text', $field->get_datatype());

        // Nullable strings populated when present and non-null.
        self::assertSame('Your department', $field->get_description());
        self::assertSame('Engineering', $field->get_defaultdata());
        self::assertSame('p1', $field->get_param1());
        self::assertSame('p5', $field->get_param5());
    }

    #[Test]
    public function fromRecordSkipsMissingScalarsAndMissingNullableStrings(): void
    {
        // Record with almost nothing: exercises the property_exists() "continue"
        // branch in the scalar map and the absent-property skip for nullables.
        $field = UserProfileField::fromRecord(['shortname' => 'alias']);

        self::assertSame('alias', $field->get_shortname());
        // Untouched scalar defaults preserved.
        self::assertSame(0, $field->get_categoryid());
        self::assertSame(1, $field->get_descriptionformat());
        // Untouched nullable defaults preserved.
        self::assertNull($field->get_description());
        self::assertNull($field->get_param1());
    }

    #[Test]
    public function fromRecordLeavesNullableStringNullWhenRecordValueIsNull(): void
    {
        // description present but explicitly null -> the `!== null` guard is false,
        // so the property keeps its default (null) instead of being cast to "".
        $field = UserProfileField::fromRecord((object) [
            'shortname' => 'bio',
            'description' => null,
        ]);

        self::assertSame('bio', $field->get_shortname());
        self::assertNull($field->get_description());
    }

    #[Test]
    public function isRequiredReflectsRequiredFlag(): void
    {
        self::assertTrue(UserProfileField::fromRecord(['required' => 1])->isRequired());
        self::assertFalse(UserProfileField::fromRecord(['required' => 0])->isRequired());
    }

    #[Test]
    public function isLockedReflectsLockedFlag(): void
    {
        self::assertTrue(UserProfileField::fromRecord(['locked' => 1])->isLocked());
        self::assertFalse(UserProfileField::fromRecord(['locked' => 0])->isLocked());
    }

    #[Test]
    public function isSignupReflectsSignupFlag(): void
    {
        self::assertTrue(UserProfileField::fromRecord(['signup' => 1])->isSignup());
        self::assertFalse(UserProfileField::fromRecord(['signup' => 0])->isSignup());
    }

    #[Test]
    public function requiresUniqueReflectsForceuniqueFlag(): void
    {
        self::assertTrue(UserProfileField::fromRecord(['forceunique' => 1])->requiresUnique());
        self::assertFalse(UserProfileField::fromRecord(['forceunique' => 0])->requiresUnique());
    }

    #[Test]
    public function visibilityLevelReturnsRawVisibleValue(): void
    {
        self::assertSame(2, UserProfileField::fromRecord(['visible' => 2])->visibilityLevel());
        self::assertSame(0, UserProfileField::fromRecord(['visible' => 0])->visibilityLevel());
    }
}
