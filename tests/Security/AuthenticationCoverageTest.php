<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Security;

use Middag\Moodle\Security\Authentication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Authentication is a thin adapter that delegates every call to the AuthSupport
 * and SessionSupport boundary wrappers, which in turn call the Moodle globals
 * (require_login / isloggedin / isguestuser / require_sesskey). The bootstrap
 * stubs those globals so behaviour is observable via $GLOBALS record keys and
 * driven return values, exercising each method without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(Authentication::class)]
final class AuthenticationCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        unset(
            $GLOBALS['__middag_test_require_login'],
            $GLOBALS['__middag_test_isloggedin'],
            $GLOBALS['__middag_test_isguest'],
            $GLOBALS['__middag_test_require_sesskey_called'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__middag_test_require_login'],
            $GLOBALS['__middag_test_isloggedin'],
            $GLOBALS['__middag_test_isguest'],
            $GLOBALS['__middag_test_require_sesskey_called'],
        );
    }

    #[Test]
    public function testRequireLoginForwardsCourseAndGuestFlagWithSupportDefaults(): void
    {
        $auth = new Authentication();

        $auth->requireLogin(5, false);

        // Authentication passes only (courseid, autologinguest); AuthSupport
        // fills the remaining require_login() args (cm=null, setwantsurl=true,
        // preventredirect=false), which the stub records positionally.
        self::assertSame([5, false, null, true, false], $GLOBALS['__middag_test_require_login']);
    }

    #[Test]
    public function testRequireLoginUsesInterfaceDefaultsWhenCalledBare(): void
    {
        $auth = new Authentication();

        $auth->requireLogin();

        self::assertSame([null, true, null, true, false], $GLOBALS['__middag_test_require_login']);
    }

    #[Test]
    public function testIsLoggedInReturnsTrueWhenSessionActive(): void
    {
        $GLOBALS['__middag_test_isloggedin'] = true;

        $auth = new Authentication();

        self::assertTrue($auth->isLoggedIn());
    }

    #[Test]
    public function testIsLoggedInReturnsFalseWhenNoSession(): void
    {
        $GLOBALS['__middag_test_isloggedin'] = false;

        $auth = new Authentication();

        self::assertFalse($auth->isLoggedIn());
    }

    #[Test]
    public function testIsGuestReturnsTrueForGuestUser(): void
    {
        $GLOBALS['__middag_test_isguest'] = true;

        $auth = new Authentication();

        self::assertTrue($auth->isGuest());
    }

    #[Test]
    public function testIsGuestReturnsFalseForAuthenticatedUser(): void
    {
        $GLOBALS['__middag_test_isguest'] = false;

        $auth = new Authentication();

        self::assertFalse($auth->isGuest());
    }

    #[Test]
    public function testRequireSesskeyDelegatesToSessionSupport(): void
    {
        $auth = new Authentication();

        $auth->requireSesskey();

        self::assertTrue($GLOBALS['__middag_test_require_sesskey_called']);
    }
}
