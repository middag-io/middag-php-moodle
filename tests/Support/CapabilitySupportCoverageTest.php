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
use Middag\Moodle\Support\CapabilitySupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @internal
 */
#[CoversClass(CapabilitySupport::class)]
final class CapabilitySupportCoverageTest extends TestCase
{
    private mixed $prevUser;

    protected function setUp(): void
    {
        $this->prevUser = $GLOBALS['USER'] ?? null;
        $GLOBALS['USER'] = (object) ['id' => 42];

        unset(
            $GLOBALS['__middag_test_has_capability'],
            $GLOBALS['__middag_test_throw_has_capability'],
            $GLOBALS['__middag_test_capability_string'],
            $GLOBALS['__middag_test_require_capability'],
            $GLOBALS['__middag_test_user_roles'],
            $GLOBALS['__middag_test_throw_get_user_roles'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['USER'] = $this->prevUser;

        unset(
            $GLOBALS['__middag_test_has_capability'],
            $GLOBALS['__middag_test_throw_has_capability'],
            $GLOBALS['__middag_test_capability_string'],
            $GLOBALS['__middag_test_require_capability'],
            $GLOBALS['__middag_test_user_roles'],
            $GLOBALS['__middag_test_throw_get_user_roles'],
        );
    }

    #[Test]
    public function testHasReturnsTrueWhenTheUserHoldsTheCapability(): void
    {
        $GLOBALS['__middag_test_has_capability'] = true;

        self::assertTrue(CapabilitySupport::has('local/example:view', new context(1)));
    }

    #[Test]
    public function testHasReturnsFalseWhenTheUserLacksTheCapability(): void
    {
        $GLOBALS['__middag_test_has_capability'] = false;

        self::assertFalse(CapabilitySupport::has('local/example:view', new context(1)));
    }

    #[Test]
    public function testGetStringReturnsTheLocalizedCapabilityName(): void
    {
        $GLOBALS['__middag_test_capability_string'] = 'View example';

        self::assertSame('View example', CapabilitySupport::getString('local/example:view'));
    }

    #[Test]
    public function testRequireDelegatesToMoodleRequireCapability(): void
    {
        CapabilitySupport::require('local/example:manage', new context(3));

        self::assertSame('local/example:manage', $GLOBALS['__middag_test_require_capability'][0]);
    }

    #[Test]
    public function testGetUserRolesReturnsTheRolesForAnExplicitUser(): void
    {
        $roles = [(object) ['roleid' => 5]];
        $GLOBALS['__middag_test_user_roles'] = $roles;

        self::assertSame($roles, CapabilitySupport::getUserRoles(new context(2), 7));
    }

    #[Test]
    public function testGetUserRolesFallsBackToTheCurrentUserWhenUseridIsNull(): void
    {
        $roles = [(object) ['roleid' => 1]];
        $GLOBALS['__middag_test_user_roles'] = $roles;
        $GLOBALS['USER'] = (object) ['id' => 99];

        self::assertSame($roles, CapabilitySupport::getUserRoles(new context(2)));
    }

    #[Test]
    public function testGetUserRolesReturnsAnEmptyArrayWhenMoodleThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_user_roles'] = true;

        self::assertSame([], CapabilitySupport::getUserRoles(new context(2), 7));
    }

    #[Test]
    public function testHasReturnsNullWhenMoodleThrows(): void
    {
        // The central has_capability() stub cannot throw yet, so probe it and skip
        // until it honors the throw flag with a core\exception\moodle_exception.
        // See coverage report: covers the has() catch branch once wired.
        $GLOBALS['__middag_test_throw_has_capability'] = true;

        $throws = false;

        try {
            has_capability('local/example:view', new context(1));
        } catch (Throwable) {
            $throws = true;
        }

        if (!$throws) {
            self::markTestSkipped('has_capability central stub cannot throw yet (see coverage report).');
        }

        $GLOBALS['__middag_test_throw_has_capability'] = true;

        self::assertNull(CapabilitySupport::has('local/example:view', new context(1)));
    }
}
