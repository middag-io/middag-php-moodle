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

use Middag\Moodle\Support\AuthSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AuthSupport::class)]
final class AuthSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        unset(
            $GLOBALS['__middag_test_require_login'],
            $GLOBALS['__middag_test_isloggedin'],
            $GLOBALS['__middag_test_isguest'],
            $GLOBALS['__middag_test_complete_login'],
            $GLOBALS['__middag_test_complete_login_extrauserinfo'],
            $GLOBALS['__middag_test_admin'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__middag_test_require_login'],
            $GLOBALS['__middag_test_isloggedin'],
            $GLOBALS['__middag_test_isguest'],
            $GLOBALS['__middag_test_complete_login'],
            $GLOBALS['__middag_test_complete_login_extrauserinfo'],
            $GLOBALS['__middag_test_admin'],
        );
    }

    #[Test]
    public function testRequireLoginForwardsAllArguments(): void
    {
        AuthSupport::requireLogin(5, false, null, false, true);

        self::assertSame([5, false, null, false, true], $GLOBALS['__middag_test_require_login']);
    }

    #[Test]
    public function testIsLoggedInReturnsTrueWhenLoggedIn(): void
    {
        $GLOBALS['__middag_test_isloggedin'] = true;

        self::assertTrue(AuthSupport::isLoggedIn());
    }

    #[Test]
    public function testIsLoggedInReturnsFalseWhenLoggedOut(): void
    {
        $GLOBALS['__middag_test_isloggedin'] = false;

        self::assertFalse(AuthSupport::isLoggedIn());
    }

    #[Test]
    public function testIsGuestReturnsTrueForAGuest(): void
    {
        $GLOBALS['__middag_test_isguest'] = true;

        self::assertTrue(AuthSupport::isGuest());
    }

    #[Test]
    public function testIsGuestReturnsFalseForARealUser(): void
    {
        $GLOBALS['__middag_test_isguest'] = false;

        self::assertFalse(AuthSupport::isGuest());
    }

    #[Test]
    public function testCompleteUserLoginReturnsTheLoggedInUserRecord(): void
    {
        // Real complete_user_login() can never return a falsy value — the
        // old bool contract let callers write dead failure branches.
        $user = (object) ['id' => 1];

        self::assertSame($user, AuthSupport::completeUserLogin($user));
    }

    #[Test]
    public function testCompleteUserLoginForwardsExtraUserInfoToTheEvent(): void
    {
        AuthSupport::completeUserLogin((object) ['id' => 1], ['authmethod' => 'sso']);

        self::assertSame(
            ['authmethod' => 'sso'],
            $GLOBALS['__middag_test_complete_login_extrauserinfo'],
        );
    }

    #[Test]
    public function testGetAdminReturnsTheAdminRecord(): void
    {
        $admin = (object) ['id' => 2];
        $GLOBALS['__middag_test_admin'] = $admin;

        self::assertSame($admin, AuthSupport::getAdmin());
    }

    #[Test]
    public function testGetAdminReturnsNullWhenAbsent(): void
    {
        self::assertNull(AuthSupport::getAdmin());
    }

    #[Test]
    public function testGetAdminNormalisesTheHostFalseIntoNull(): void
    {
        // get_admin() returns false (not null) when there is no site admin.
        // Passing that straight through the ?stdClass signature would raise a
        // TypeError; getAdmin() must normalise it to null.
        $GLOBALS['__middag_test_admin'] = false;

        self::assertNull(AuthSupport::getAdmin());
    }
}
