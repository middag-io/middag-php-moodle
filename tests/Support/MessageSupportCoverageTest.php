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

use core\message\message as core_message;
use core\url as moodle_url;
use Middag\Moodle\Support\MessageSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stored_file;

/**
 * @internal
 */
#[CoversClass(MessageSupport::class)]
final class MessageSupportCoverageTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        foreach ([
            '__middag_test_emails',
            '__middag_test_email_result',
            '__middag_test_message_send_result',
            '__middag_test_throw_message_send',
            '__middag_test_self_conversation',
            '__middag_test_created_conversation',
            '__middag_test_conversation_between',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->tempFiles = [];
    }

    #[Test]
    public function testCreateMessageReturnsACoreMessage(): void
    {
        self::assertInstanceOf(core_message::class, MessageSupport::createMessage());
    }

    #[Test]
    public function testSendReturnsTheMessageId(): void
    {
        $GLOBALS['__middag_test_message_send_result'] = 4242;

        self::assertSame(4242, MessageSupport::send(MessageSupport::createMessage()));
    }

    #[Test]
    public function testSendReturnsFalseWhenDeliveryFails(): void
    {
        $GLOBALS['__middag_test_message_send_result'] = false;

        self::assertFalse(MessageSupport::send(MessageSupport::createMessage()));
    }

    #[Test]
    public function testEmailForwardsReorderedArgumentsToEmailToUser(): void
    {
        $to = (object) ['id' => 5, 'email' => 'to@example.test'];
        $from = (object) ['id' => 6, 'email' => 'from@example.test'];

        $result = MessageSupport::email($to, $from, 'Subject', '<b>html</b>', 'plain text', 'attach.pdf', 'label.pdf');

        self::assertTrue($result);
        $recorded = end($GLOBALS['__middag_test_emails']);
        self::assertSame('Subject', $recorded['subject']);
        self::assertSame('plain text', $recorded['text']);
        self::assertSame('<b>html</b>', $recorded['html']);
        self::assertSame('attach.pdf', $recorded['attachment']);
    }

    #[Test]
    public function testGetNoreplyUserReturnsTheNoreplyUser(): void
    {
        self::assertSame(-99, MessageSupport::getNoreplyUser()->id);
    }

    #[Test]
    public function testCreateTempAttachmentWritesTheFileAndReturnsPath(): void
    {
        $file = new stored_file(['filename' => 'report.pdf', 'content' => 'DATA-BYTES']);

        [$path, $filename] = MessageSupport::createTempAttachment($file);
        $this->tempFiles[] = $path;

        self::assertSame('report.pdf', $filename);
        self::assertFileExists($path);
        self::assertSame('DATA-BYTES', file_get_contents($path));
    }

    #[Test]
    public function testGetConversationIdReturnsExistingSelfConversation(): void
    {
        $GLOBALS['__middag_test_self_conversation'] = (object) ['id' => 55];

        $message = MessageSupport::createMessage();
        $message->userfrom = (object) ['id' => 1];
        $message->userto = (object) ['id' => 1];

        self::assertSame(55, MessageSupport::getConversationId($message));
    }

    #[Test]
    public function testGetConversationIdCreatesSelfConversationWhenMissing(): void
    {
        // Self conversation absent → create one of type SELF (default stub id 903).
        $message = MessageSupport::createMessage();
        $message->userfrom = (object) ['id' => 2];
        $message->userto = (object) ['id' => 2];

        self::assertSame(903, MessageSupport::getConversationId($message));
    }

    #[Test]
    public function testGetConversationIdReturnsExistingPrivateConversation(): void
    {
        $GLOBALS['__middag_test_conversation_between'] = 42;

        $message = MessageSupport::createMessage();
        $message->userfrom = (object) ['id' => 1];
        $message->userto = (object) ['id' => 2];

        self::assertSame(42, MessageSupport::getConversationId($message));
    }

    #[Test]
    public function testGetConversationIdAcceptsRawIntUserIds(): void
    {
        // core\message\message allows userfrom/userto to be a raw int id.
        // Dereferencing ->id on an int would coerce to 0 and misclassify this
        // as a self-conversation on user 0 instead of a private one.
        $GLOBALS['__middag_test_conversation_between'] = 42;

        $message = MessageSupport::createMessage();
        $message->userfrom = 7;
        $message->userto = 9;

        self::assertSame(42, MessageSupport::getConversationId($message));
    }

    #[Test]
    public function testGetConversationIdCreatesPrivateConversationWhenMissing(): void
    {
        // No conversation between users → create INDIVIDUAL (default stub id 901).
        $message = MessageSupport::createMessage();
        $message->userfrom = (object) ['id' => 1];
        $message->userto = (object) ['id' => 3];

        self::assertSame(901, MessageSupport::getConversationId($message));
    }

    #[Test]
    public function testCreateLinkDelegatesToHtmlWriter(): void
    {
        $link = MessageSupport::createLink(new moodle_url('/dashboard'), 'Go');

        self::assertStringContainsString('Go', $link);
        self::assertStringContainsString('/dashboard', $link);
    }

    #[Test]
    public function testGetSelfConversationDelegatesToApi(): void
    {
        $GLOBALS['__middag_test_self_conversation'] = (object) ['id' => 9];

        self::assertSame(9, MessageSupport::getSelfConversation(1)->id);
    }

    #[Test]
    public function testCreateConversationDelegatesToApi(): void
    {
        $GLOBALS['__middag_test_created_conversation'] = (object) ['id' => 12];

        self::assertSame(12, MessageSupport::createConversation(MessageSupport::CONVERSATION_TYPE_SELF, [1])->id);
    }

    #[Test]
    public function testGetConversationBetweenUsersDelegatesToApi(): void
    {
        $GLOBALS['__middag_test_conversation_between'] = 88;

        self::assertSame(88, MessageSupport::getConversationBetweenUsers([1, 2]));
    }

    #[Test]
    public function testConversationTypeConstantsMirrorMoodle(): void
    {
        self::assertSame(3, MessageSupport::CONVERSATION_TYPE_SELF);
        self::assertSame(1, MessageSupport::CONVERSATION_TYPE_INDIVIDUAL);
    }
}
