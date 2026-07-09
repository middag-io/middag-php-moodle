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

use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Moodle\Domain\Context\ContextLevel;
use Middag\Moodle\Security\Authorizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Line coverage for the composed Authorizer.
 *
 * `Authorizer` is a thin facade: its constructor wires the `Authentication` and
 * `Capability` adapters, and every public method forwards to one of them. The
 * tests assert the observable delegation effect (recorded Moodle stub calls,
 * returned booleans, thrown authorization exception) rather than re-testing the
 * adapters, so every Authorizer line is exercised end-to-end.
 *
 * @internal
 */
#[CoversClass(Authorizer::class)]
final class AuthorizerCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetGlobals();
    }

    protected function tearDown(): void
    {
        $this->resetGlobals();
    }

    #[Test]
    public function testRequireLoginForwardsExplicitArgumentsToMoodle(): void
    {
        $authorizer = new Authorizer();

        $authorizer->requireLogin(42, false);

        // Authentication → AuthSupport::requireLogin($courseid, $autologinguest)
        // → require_login($courseorid, $autologinguest, $cm=null, $setwantsurl=true, $preventredirect=false).
        self::assertSame([42, false, null, true, false], $GLOBALS['__middag_test_require_login']);
    }

    #[Test]
    public function testRequireLoginForwardsDefaultArgumentsToMoodle(): void
    {
        $authorizer = new Authorizer();

        $authorizer->requireLogin();

        self::assertSame([null, true, null, true, false], $GLOBALS['__middag_test_require_login']);
    }

    #[Test]
    public function testIsLoggedInReturnsTrueFromAuthentication(): void
    {
        $GLOBALS['__middag_test_isloggedin'] = true;

        $authorizer = new Authorizer();

        self::assertTrue($authorizer->isLoggedIn());
    }

    #[Test]
    public function testIsLoggedInReturnsFalseFromAuthentication(): void
    {
        $GLOBALS['__middag_test_isloggedin'] = false;

        $authorizer = new Authorizer();

        self::assertFalse($authorizer->isLoggedIn());
    }

    #[Test]
    public function testIsGuestReturnsTrueFromAuthentication(): void
    {
        $GLOBALS['__middag_test_isguest'] = true;

        $authorizer = new Authorizer();

        self::assertTrue($authorizer->isGuest());
    }

    #[Test]
    public function testIsGuestReturnsFalseFromAuthentication(): void
    {
        $GLOBALS['__middag_test_isguest'] = false;

        $authorizer = new Authorizer();

        self::assertFalse($authorizer->isGuest());
    }

    #[Test]
    public function testRequireSesskeyDelegatesToSession(): void
    {
        $authorizer = new Authorizer();

        $authorizer->requireSesskey();

        self::assertTrue($GLOBALS['__middag_test_require_sesskey_called']);
    }

    #[Test]
    public function testCanReturnsTrueWhenTheUserHoldsTheCapability(): void
    {
        $GLOBALS['__middag_test_has_capability'] = true;

        $authorizer = new Authorizer();

        // Non-SYSTEM level: ContextSupport::course() yields a proper
        // core\context\course, avoiding the intentional system() TypeError stub.
        self::assertTrue($authorizer->can('local/example:view', ContextLevel::Course, 5));
    }

    #[Test]
    public function testCanReturnsFalseWhenTheUserLacksTheCapability(): void
    {
        $GLOBALS['__middag_test_has_capability'] = false;

        $authorizer = new Authorizer();

        self::assertFalse($authorizer->can('local/example:view', ContextLevel::Course, 5));
    }

    #[Test]
    public function testAuthorizeReturnsWithoutThrowingWhenCapabilityGranted(): void
    {
        $GLOBALS['__middag_test_has_capability'] = true;

        $authorizer = new Authorizer();

        $threw = false;

        try {
            $authorizer->authorize('local/example:manage', ContextLevel::Course, 5);
        } catch (MiddagAuthorizationException) {
            $threw = true;
        }

        self::assertFalse($threw, 'authorize() must not throw when the capability is granted.');
    }

    #[Test]
    public function testAuthorizeThrowsWhenCapabilityDenied(): void
    {
        $GLOBALS['__middag_test_has_capability'] = false;

        $authorizer = new Authorizer();

        $this->expectException(MiddagAuthorizationException::class);
        $this->expectExceptionMessage('Missing capability: local/example:manage');

        $authorizer->authorize('local/example:manage', ContextLevel::Course, 5);
    }

    private function resetGlobals(): void
    {
        unset(
            $GLOBALS['__middag_test_require_login'],
            $GLOBALS['__middag_test_isloggedin'],
            $GLOBALS['__middag_test_isguest'],
            $GLOBALS['__middag_test_require_sesskey_called'],
            $GLOBALS['__middag_test_has_capability'],
            $GLOBALS['__middag_test_throw_has_capability'],
        );
    }
}
