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

use Middag\Moodle\Domain\User\User;
use Middag\Moodle\Security\ValueObject\Sesskey;
use Middag\Moodle\Support\SessionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(SessionSupport::class)]
final class SessionSupportCoverageTest extends TestCase
{
    private mixed $prevSession;

    protected function setUp(): void
    {
        $this->prevSession = $GLOBALS['SESSION'] ?? null;
        $GLOBALS['SESSION'] = new stdClass();

        unset(
            $GLOBALS['__middag_test_sesskey_valid'],
            $GLOBALS['__middag_test_sesskey'],
            $GLOBALS['__middag_test_require_sesskey_called'],
            $GLOBALS['__middag_test_destroyed_sessions'],
            $GLOBALS['__middag_test_session_user'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['SESSION'] = $this->prevSession;
    }

    #[Test]
    public function testConfirmSesskeyReturnsTrueWhenValid(): void
    {
        $GLOBALS['__middag_test_sesskey_valid'] = true;

        self::assertTrue(SessionSupport::confirmSesskey('abc'));
    }

    #[Test]
    public function testConfirmSesskeyReturnsFalseWhenInvalid(): void
    {
        $GLOBALS['__middag_test_sesskey_valid'] = false;

        self::assertFalse(SessionSupport::confirmSesskey());
    }

    #[Test]
    public function testConfirmWithValueObjectDelegatesToConfirmSesskey(): void
    {
        $GLOBALS['__middag_test_sesskey_valid'] = true;

        self::assertTrue(SessionSupport::confirm(new Sesskey('abc123')));
    }

    #[Test]
    public function testRequireSesskeyInvokesTheMoodleHelper(): void
    {
        SessionSupport::requireSesskey();

        self::assertTrue($GLOBALS['__middag_test_require_sesskey_called']);
    }

    #[Test]
    public function testSesskeyReturnsTheCurrentKey(): void
    {
        $GLOBALS['__middag_test_sesskey'] = 'KEY42';

        self::assertSame('KEY42', SessionSupport::sesskey());
    }

    #[Test]
    public function testSesskeyReturnsEmptyStringWhenTheHostReturnsFalse(): void
    {
        // Moodle's sesskey() returns false before $_SESSION['USER'] is set;
        // under strict_types that would TypeError against the ': string' return,
        // so it must be normalised to '' rather than crashing.
        $GLOBALS['__middag_test_sesskey'] = false;

        self::assertSame('', SessionSupport::sesskey());
    }

    #[Test]
    public function testDestroyUserSessionsForwardsToTheManager(): void
    {
        SessionSupport::destroyUserSessions(7, 'sid-keep');

        self::assertSame([7, 'sid-keep'], $GLOBALS['__middag_test_destroyed_sessions']);
    }

    #[Test]
    public function testSetUserWithStdClassPassesTheRecordThrough(): void
    {
        $user = (object) ['id' => 3];

        SessionSupport::setUser($user);

        self::assertSame($user, $GLOBALS['__middag_test_session_user']);
    }

    #[Test]
    public function testSetUserWithEntityConvertsItToARecord(): void
    {
        SessionSupport::setUser(new User());

        self::assertInstanceOf(stdClass::class, $GLOBALS['__middag_test_session_user']);
    }

    #[Test]
    public function testGetWantsUrlReturnsTheStoredUrl(): void
    {
        $GLOBALS['SESSION']->wantsurl = 'https://moodle.test/course';

        self::assertSame('https://moodle.test/course', SessionSupport::getWantsUrl());
    }

    #[Test]
    public function testGetWantsUrlReturnsNullWhenAbsent(): void
    {
        self::assertNull(SessionSupport::getWantsUrl());
    }

    #[Test]
    public function testUnsetWantsUrlRemovesTheStoredUrl(): void
    {
        $GLOBALS['SESSION']->wantsurl = 'https://moodle.test/course';

        SessionSupport::unsetWantsUrl();

        self::assertNull(SessionSupport::getWantsUrl());
    }

    #[Test]
    public function testGetIdReturnsTheSessionId(): void
    {
        self::assertIsString(SessionSupport::getId());
    }
}
