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

use core\user as core_user;
use DateTimeZone;
use Middag\Moodle\Support\TimeSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TimeSupport::class)]
final class TimeSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        unset(
            $GLOBALS['__middag_test_userdate'],
            $GLOBALS['__middag_test_usertime'],
            $GLOBALS['__middag_test_make_timestamp'],
            $GLOBALS['__middag_test_server_tz'],
            $GLOBALS['__middag_test_user_tz'],
            $GLOBALS['__middag_test_user_record'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__middag_test_userdate'],
            $GLOBALS['__middag_test_usertime'],
            $GLOBALS['__middag_test_make_timestamp'],
            $GLOBALS['__middag_test_server_tz'],
            $GLOBALS['__middag_test_user_tz'],
            $GLOBALS['__middag_test_user_record'],
        );
    }

    #[Test]
    public function testNowReturnsTheCurrentTimestamp(): void
    {
        self::assertGreaterThan(0, TimeSupport::now());
    }

    #[Test]
    public function testUserdateWithDefaultTimezoneUsesTheMoodleConvention(): void
    {
        self::assertSame('date:1000|tz:99', TimeSupport::userdate(1000));
    }

    #[Test]
    public function testUserdateWithExplicitTimezoneForwardsIt(): void
    {
        self::assertSame('date:1000|tz:America/Sao_Paulo', TimeSupport::userdate(1000, '', 'America/Sao_Paulo'));
    }

    #[Test]
    public function testUsertimeWithDefaultTimezoneReturnsAdjustedTimestamp(): void
    {
        self::assertSame(4600, TimeSupport::usertime(1000));
    }

    #[Test]
    public function testUsertimeWithExplicitTimezoneReturnsAdjustedTimestamp(): void
    {
        self::assertSame(4600, TimeSupport::usertime(1000, 'UTC'));
    }

    #[Test]
    public function testServerTimezoneReturnsTheConfiguredZone(): void
    {
        $GLOBALS['__middag_test_server_tz'] = 'America/Sao_Paulo';

        self::assertSame('America/Sao_Paulo', TimeSupport::serverTimezone());
    }

    #[Test]
    public function testUserTimezoneWithoutUseridReturnsTheDefaultZone(): void
    {
        $GLOBALS['__middag_test_user_tz'] = 'America/Sao_Paulo';

        self::assertSame('America/Sao_Paulo', TimeSupport::userTimezone());
    }

    #[Test]
    public function testMakeTimestampWithDefaultTimezoneReturnsAUnixTimestamp(): void
    {
        $GLOBALS['__middag_test_make_timestamp'] = 1234567;

        self::assertSame(1234567, TimeSupport::makeTimestamp(2026, 7, 6));
    }

    #[Test]
    public function testMakeTimestampWithExplicitTimezoneReturnsAUnixTimestamp(): void
    {
        $GLOBALS['__middag_test_make_timestamp'] = 7654321;

        self::assertSame(7654321, TimeSupport::makeTimestamp(2026, 1, 1, 0, 0, 0, 'UTC'));
    }

    #[Test]
    public function testServerTimezoneObjectReturnsADateTimeZone(): void
    {
        $GLOBALS['__middag_test_server_tz'] = 'UTC';

        $tz = TimeSupport::serverTimezoneObject();

        self::assertInstanceOf(DateTimeZone::class, $tz);
        self::assertSame('UTC', $tz->getName());
    }

    #[Test]
    public function testUserTimezoneObjectWithoutUseridReturnsADateTimeZone(): void
    {
        $GLOBALS['__middag_test_user_tz'] = 'America/Sao_Paulo';

        $tz = TimeSupport::userTimezoneObject();

        self::assertInstanceOf(DateTimeZone::class, $tz);
        self::assertSame('America/Sao_Paulo', $tz->getName());
    }

    #[Test]
    public function testUserTimezoneWithUseridResolvesFromTheUserRecord(): void
    {
        if (!method_exists(core_user::class, 'get_user')) {
            self::markTestSkipped('core\user::get_user central stub pending (see coverage report).');
        }

        $GLOBALS['__middag_test_user_record'] = (object) ['id' => 7];
        $GLOBALS['__middag_test_user_tz'] = 'America/Sao_Paulo';

        self::assertIsString(TimeSupport::userTimezone(7));
    }

    #[Test]
    public function testUserTimezoneObjectWithUseridResolvesFromTheUserRecord(): void
    {
        if (!method_exists(core_user::class, 'get_user')) {
            self::markTestSkipped('core\user::get_user central stub pending (see coverage report).');
        }

        $GLOBALS['__middag_test_user_record'] = (object) ['id' => 7];
        $GLOBALS['__middag_test_user_tz'] = 'America/Sao_Paulo';

        self::assertInstanceOf(DateTimeZone::class, TimeSupport::userTimezoneObject(7));
    }
}
