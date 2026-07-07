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

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Domain\Message\NotificationDto;
use Middag\Moodle\Support\NotificationSupport;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(NotificationSupport::class)]
final class NotificationSupportCoverageTest extends TestCase
{
    private mixed $prevDb;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;

        ComponentContext::configure('local_example', 'local_example_autoload');

        foreach ([
            '__middag_test_message_send_result',
            '__middag_test_throw_message_send',
            '__middag_test_unread_count',
            '__middag_test_throw_unread',
            '__middag_test_marked_read',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
    }

    #[Test]
    public function testSendWithAllOptionalFieldsReturnsMessageId(): void
    {
        $GLOBALS['__middag_test_message_send_result'] = 555;

        $dto = new NotificationDto(
            component: 'local_example',
            name: 'alert',
            useridTo: 10,
            subject: 'Hi',
            fullMessage: 'Body',
            fullMessageHtml: '<p>Body</p>',
            useridFrom: 20,
            contextUrl: 'https://moodle.test/view',
            contextUrlName: 'View',
            courseid: 3,
        );

        self::assertSame(555, NotificationSupport::send($dto));

        // The optional contextUrl/courseid branches must actually populate the message.
        $msg = $GLOBALS['__middag_test_sent_message'];
        self::assertSame('https://moodle.test/view', $msg->contexturl);
        self::assertSame('View', $msg->contexturlname);
        self::assertSame(3, $msg->courseid);
    }

    #[Test]
    public function testSendWithoutOptionalFieldsUsesNoreplyAndReturnsId(): void
    {
        $GLOBALS['__middag_test_message_send_result'] = 100;

        $dto = new NotificationDto(
            component: 'local_example',
            name: 'alert',
            useridTo: 10,
            subject: 'Hi',
            fullMessage: 'Body',
            fullMessageHtml: '<p>Body</p>',
        );

        self::assertSame(100, NotificationSupport::send($dto));
    }

    #[Test]
    public function testSendReturnsNullWhenDeliveryReturnsFalse(): void
    {
        $GLOBALS['__middag_test_message_send_result'] = false;

        $dto = new NotificationDto(
            component: 'local_example',
            name: 'alert',
            useridTo: 10,
            subject: 'Hi',
            fullMessage: 'Body',
            fullMessageHtml: '<p>Body</p>',
        );

        self::assertNull(NotificationSupport::send($dto));
    }

    #[Test]
    public function testSendReturnsNullWhenDeliveryThrows(): void
    {
        $GLOBALS['__middag_test_throw_message_send'] = true;

        $dto = new NotificationDto(
            component: 'local_example',
            name: 'alert',
            useridTo: 10,
            subject: 'Hi',
            fullMessage: 'Body',
            fullMessageHtml: '<p>Body</p>',
        );

        self::assertNull(NotificationSupport::send($dto));
    }

    #[Test]
    public function testSendSimpleBuildsNotificationAndDispatches(): void
    {
        $GLOBALS['__middag_test_message_send_result'] = 202;

        self::assertSame(202, NotificationSupport::sendSimple(10, 'Subject', '<p>Message</p>', 'https://moodle.test/x'));
    }

    #[Test]
    public function testGetUnreadCountReturnsTheApiCount(): void
    {
        $GLOBALS['__middag_test_unread_count'] = 7;

        self::assertSame(7, NotificationSupport::getUnreadCount(10));
    }

    #[Test]
    public function testGetUnreadCountReturnsZeroOnError(): void
    {
        $GLOBALS['__middag_test_throw_unread'] = true;

        self::assertSame(0, NotificationSupport::getUnreadCount(10));
    }

    #[Test]
    public function testMarkReadReturnsFalseWhenNotificationNotFound(): void
    {
        // Drive the real "record not found -> false" branch (not the catch):
        // $DB->get_record() returns false, so markRead returns false without
        // calling mark_notification_as_read.
        $GLOBALS['DB'] = new class extends moodle_database {
            public function get_record($table, ?array $conditions = null, $fields = '*', $strictness = 0)
            {
                return false;
            }
        };

        self::assertFalse(NotificationSupport::markRead(99));
        self::assertArrayNotHasKey('__middag_test_marked_read', $GLOBALS);
    }

    #[Test]
    public function testMarkReadMarksNotificationAndReturnsTrue(): void
    {
        $GLOBALS['DB'] = new class extends moodle_database {
            public function get_record($table, ?array $conditions = null, $fields = '*', $strictness = 0)
            {
                return (object) ['id' => 99, 'useridto' => 10];
            }
        };

        self::assertTrue(NotificationSupport::markRead(99, 1234));
        self::assertSame(1234, $GLOBALS['__middag_test_marked_read'][1]);
    }

    #[Test]
    public function testMarkReadReturnsFalseWhenLookupThrows(): void
    {
        $GLOBALS['DB'] = new class extends moodle_database {
            public function get_record($table, ?array $conditions = null, $fields = '*', $strictness = 0): void
            {
                throw new RuntimeException('db error');
            }
        };

        self::assertFalse(NotificationSupport::markRead(99));
    }
}
