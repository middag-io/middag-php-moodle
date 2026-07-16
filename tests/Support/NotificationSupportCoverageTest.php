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
    public function testSendMarksDistinctPlainTextAsFormatPlain(): void
    {
        // fullMessage differs from fullMessageHtml (genuine plain text) → it must
        // be FORMAT_PLAIN, or a reader runs it through format_text() and mangles
        // it (e.g. strips '<needs review>').
        $dto = new NotificationDto(
            component: 'local_example',
            name: 'alert',
            useridTo: 10,
            subject: 'Hi',
            fullMessage: 'Score 85% <needs review>',
            fullMessageHtml: '<p>Score 85% needs review</p>',
        );

        NotificationSupport::send($dto);

        self::assertSame(FORMAT_PLAIN, $GLOBALS['__middag_test_sent_message']->fullmessageformat);
    }

    #[Test]
    public function testSendMarksAnHtmlBodyAsFormatHtml(): void
    {
        // When fullMessage IS the HTML version (sendSimple convention) → FORMAT_HTML.
        $dto = new NotificationDto(
            component: 'local_example',
            name: 'alert',
            useridTo: 10,
            subject: 'Hi',
            fullMessage: '<p>Body</p>',
            fullMessageHtml: '<p>Body</p>',
        );

        NotificationSupport::send($dto);

        self::assertSame(FORMAT_HTML, $GLOBALS['__middag_test_sent_message']->fullmessageformat);
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
    public function testSendSimpleUsesTheValueFreeDefaultMessageName(): void
    {
        $GLOBALS['__middag_test_message_send_result'] = 202;

        NotificationSupport::sendSimple(10, 'Subject', '<p>Message</p>');

        self::assertSame('system_notification', $GLOBALS['__middag_test_sent_message']->name);
    }

    #[Test]
    public function testSendSimpleHonoursADefaultMessageNameOverride(): void
    {
        // The seam must resolve via late static binding: a host subclass
        // overriding defaultMessageName() reroutes sendSimple() to the
        // provider its product actually registers in db/messages.php.
        $GLOBALS['__middag_test_message_send_result'] = 202;

        NotificationSupportWithCustomMessageName::sendSimple(10, 'Subject', '<p>Message</p>');

        self::assertSame('custom_provider', $GLOBALS['__middag_test_sent_message']->name);
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
    public function testGetUnreadCountReturnsZeroForANonPositiveUserId(): void
    {
        // A 0 userid must not fall through to count_unread_popup_notifications(),
        // whose empty() check would substitute $USER and leak that user's count.
        $GLOBALS['__middag_test_unread_count'] = 7;

        self::assertSame(0, NotificationSupport::getUnreadCount(0));
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

/**
 * Fixture: host subclass overriding the defaultMessageName() seam.
 *
 * @internal
 */
final class NotificationSupportWithCustomMessageName extends NotificationSupport
{
    protected static function defaultMessageName(): string
    {
        return 'custom_provider';
    }
}
