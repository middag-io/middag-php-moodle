<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Message;

use core\message\message as core_message;
use core\url as moodle_url;
use Middag\Moodle\Domain\Message\MessageService;
use middag_test_message_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use stored_file;

/**
 * MessageService composes MessageSupport / ConfigSupport / CourseSupport /
 * HtmlWriterSupport / UrlSupport / UserSupport / LangSupport over the global $DB,
 * $CFG and $SITE. The $DB is a course-record double (from the domain-message
 * area stub), $CFG/$SITE are plain fixtures, and the Moodle messaging/email
 * helpers come from the shared support stubs, so every branch runs without a
 * Moodle runtime.
 *
 * @internal
 */
#[CoversClass(MessageService::class)]
final class MessageServiceCoverageTest extends TestCase
{
    private mixed $prevDb;

    private mixed $prevCfg;

    private mixed $prevSite;

    /** @var list<string> */
    private array $testGlobals = [
        '__middag_test_message_course_record',
        '__middag_test_site_course',
        '__middag_test_fullname',
        '__middag_test_emails',
        '__middag_test_sent_message',
        '__middag_test_email_result',
        '__middag_test_message_send_result',
        '__middag_test_self_conversation',
        '__middag_test_created_conversation',
        '__middag_test_conversation_between',
    ];

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevSite = $GLOBALS['SITE'] ?? null;

        $GLOBALS['DB'] = new middag_test_message_database();
        $GLOBALS['CFG'] = (object) [
            'wwwroot' => 'https://moodle.test',
            'supportemail' => 'help@moodle.test',
        ];
        $GLOBALS['SITE'] = (object) [
            'id' => 1,
            'fullname' => 'Site Full Name',
            'shortname' => 'SFN',
            'summaryformat' => 1,
        ];

        $GLOBALS['__middag_test_fullname'] = 'John Doe';

        foreach ($this->testGlobals as $key) {
            if ($key !== '__middag_test_fullname') {
                unset($GLOBALS[$key]);
            }
        }
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['SITE'] = $this->prevSite;

        foreach ($this->testGlobals as $key) {
            unset($GLOBALS[$key]);
        }
    }

    #[Test]
    public function testComponentResolvesFromComponentContext(): void
    {
        self::assertSame('local_example', MessageService::component());
        self::assertSame('middagsender', MessageService::NAME);
    }

    #[Test]
    public function testEmailWithDefaultCourseReplacesTokensAndSends(): void
    {
        $GLOBALS['__middag_test_message_course_record'] = (object) ['id' => 1, 'fullname' => 'Course One'];

        $service = new MessageService();
        $service->email($this->makeUser(2), $this->makeUser(20), 'Hello [[firstname]]', 'Body for [[lastname]]');

        self::assertArrayHasKey('__middag_test_emails', $GLOBALS);
        self::assertCount(1, $GLOBALS['__middag_test_emails']);
        $sent = $GLOBALS['__middag_test_emails'][0];
        self::assertSame('Hello John', $sent['subject']);
        self::assertSame(20, $sent['to']->id);
        self::assertSame('', $sent['attachment']);
        self::assertSame('', $sent['attachname']);
    }

    #[Test]
    public function testEmailWithExplicitCourseAndStoredFileAttachment(): void
    {
        $GLOBALS['__middag_test_message_course_record'] = (object) ['id' => 5, 'fullname' => 'Course Five'];

        $file = new stored_file(['filename' => 'report.pdf', 'content' => 'PDF-BYTES']);

        $service = new MessageService();
        $service->email($this->makeUser(21), $this->makeUser(2), 'Subject', 'Body', 5, [$file]);

        $sent = $GLOBALS['__middag_test_emails'][0];
        self::assertSame('report.pdf', $sent['attachname']);
        self::assertNotSame('', $sent['attachment']);
        self::assertStringEndsWith('report.pdf', $sent['attachment']);
    }

    #[Test]
    public function testEmailWithEmptyAttachmentsArraySendsWithoutAttachment(): void
    {
        $GLOBALS['__middag_test_message_course_record'] = (object) ['id' => 1, 'fullname' => 'Course One'];

        $service = new MessageService();
        $service->email($this->makeUser(22), $this->makeUser(2), 'Subject', 'Body', 0, []);

        $sent = $GLOBALS['__middag_test_emails'][0];
        self::assertSame('', $sent['attachment']);
        self::assertSame('', $sent['attachname']);
    }

    #[Test]
    public function testMessageSendsNonNotificationAndReturnsMoodleResult(): void
    {
        $GLOBALS['__middag_test_message_course_record'] = (object) ['id' => 1, 'fullname' => 'Course One'];
        $GLOBALS['__middag_test_message_send_result'] = 4242;

        $service = new MessageService();
        $result = $service->message($this->makeUser(20), $this->makeUser(2), 'Subject', 'Body');

        self::assertSame(4242, $result);
        $msg = $GLOBALS['__middag_test_sent_message'];
        self::assertInstanceOf(core_message::class, $msg);
        self::assertSame(0, $msg->notification);
        self::assertSame('local_example', $msg->component);
        self::assertSame('middagsender', $msg->name);
        self::assertStringContainsString('<h5>Subject</h5>', $msg->smallmessage);
    }

    #[Test]
    public function testNotificationWithUrlObjectSetsContextUrlAndNoreplySender(): void
    {
        $GLOBALS['__middag_test_message_course_record'] = (object) ['id' => 1, 'fullname' => 'Course One'];
        $GLOBALS['__middag_test_message_send_result'] = 999;

        $url = new moodle_url('/mod/unidade/view.php');

        $service = new MessageService();
        $result = $service->notification($this->makeUser(20), $this->makeUser(2), 'Subject', 'Body', 1, $url, 'Open unit');

        self::assertSame(999, $result);
        $msg = $GLOBALS['__middag_test_sent_message'];
        self::assertSame(1, $msg->notification);
        self::assertSame(-99, $msg->userfrom->id);
        self::assertSame('/mod/unidade/view.php', $msg->contexturl);
        self::assertSame('Open unit', $msg->contexturlname);
    }

    #[Test]
    public function testNotificationWithStringUrlAndNoUrlNameLeavesUrlNameUnset(): void
    {
        $GLOBALS['__middag_test_message_course_record'] = (object) ['id' => 1, 'fullname' => 'Course One'];

        $service = new MessageService();
        $service->notification($this->makeUser(20), $this->makeUser(2), 'Subject', 'Body', 1, 'https://external.test/x');

        $msg = $GLOBALS['__middag_test_sent_message'];
        self::assertSame('https://external.test/x', $msg->contexturl);
        self::assertNull($msg->contexturlname);
    }

    #[Test]
    public function testNotificationWithoutUrlLeavesContextUrlUnset(): void
    {
        $GLOBALS['__middag_test_message_course_record'] = (object) ['id' => 1, 'fullname' => 'Course One'];

        $service = new MessageService();
        $service->notification($this->makeUser(20), $this->makeUser(2), 'Subject', 'Body');

        $msg = $GLOBALS['__middag_test_sent_message'];
        self::assertNull($msg->contexturl);
        self::assertNull($msg->contexturlname);
        self::assertSame(1, $msg->notification);
    }

    #[Test]
    public function testPrepareBuildsCoreMessageWithSiteCourseWhenCourseidIsZero(): void
    {
        $service = new MessageService();
        $msg = $service->prepare($this->makeUser(20), $this->makeUser(2), 'The subject', 'The body');

        self::assertInstanceOf(core_message::class, $msg);
        self::assertSame('local_example', $msg->component);
        self::assertSame('middagsender', $msg->name);
        self::assertSame('The subject', $msg->subject);
        self::assertSame('The body', $msg->fullmessage);
        self::assertSame(FORMAT_HTML, $msg->fullmessageformat);
        self::assertSame(1, $msg->courseid);
    }

    #[Test]
    public function testGetConversationIdCreatesIndividualConversationForDistinctUsers(): void
    {
        $service = new MessageService();
        $message = $this->makeMessage(2, 3);

        // No existing conversation between users → create_conversation(INDIVIDUAL=1) → id 901.
        self::assertSame(901, $service->getConversationId($message));
    }

    #[Test]
    public function testGetConversationIdReturnsExistingIndividualConversation(): void
    {
        $GLOBALS['__middag_test_conversation_between'] = 555;

        $service = new MessageService();
        $message = $this->makeMessage(2, 3);

        self::assertSame(555, $service->getConversationId($message));
    }

    #[Test]
    public function testGetConversationIdReturnsExistingSelfConversation(): void
    {
        $GLOBALS['__middag_test_self_conversation'] = (object) ['id' => 777];

        $service = new MessageService();
        $message = $this->makeMessage(9, 9);

        self::assertSame(777, $service->getConversationId($message));
    }

    #[Test]
    public function testGetConversationIdCreatesSelfConversationWhenNoneExists(): void
    {
        $service = new MessageService();
        $message = $this->makeMessage(9, 9);

        // No self conversation → create_conversation(SELF=3) → id 903.
        self::assertSame(903, $service->getConversationId($message));
    }

    #[Test]
    public function testPrepareTextWithCourseAndFallbackForgotUrl(): void
    {
        // $CFG has no forgottenpasswordurl → prepareText falls back to a moodle_url,
        // exercising the moodle_url->out() arm of [[forgotpasswordurl]].
        $course = (object) ['id' => 3, 'fullname' => 'My Course'];
        $user = $this->makeUser(2);

        $service = new MessageService();
        $data = $service->prepareText($course, $user);

        self::assertSame('jdoe2', $data['[[username]]']);
        self::assertSame('John', $data['[[firstname]]']);
        self::assertSame('Doe', $data['[[lastname]]']);
        self::assertSame('John Doe', $data['[[fullname]]']);
        self::assertSame('My Course', $data['[[coursename]]']);
        self::assertSame('Site Full Name', $data['[[sitename]]']);
        self::assertSame('help@moodle.test', $data['[[supportemail]]']);
        // Fallback URL is a moodle_url → its out() string is the login path.
        self::assertSame('/login/forgot_password.php', $data['[[forgotpasswordurl]]']);
    }

    #[Test]
    public function testPrepareTextWithNullCourseUsesSiteAndConfiguredForgotUrl(): void
    {
        $GLOBALS['CFG']->forgottenpasswordurl = 'https://moodle.test/forgot';
        $GLOBALS['__middag_test_site_course'] = (object) ['id' => 1, 'fullname' => 'Front Page'];

        $service = new MessageService();
        $data = $service->prepareText(null, $this->makeUser(2));

        // Null course → get_site() front-page record supplies [[coursename]].
        self::assertSame('Front Page', $data['[[coursename]]']);
        // Configured (non-empty) forgot URL is a plain string → returned verbatim.
        self::assertSame('https://moodle.test/forgot', $data['[[forgotpasswordurl]]']);
    }

    private function makeUser(int $id): stdClass
    {
        return (object) [
            'id' => $id,
            'username' => 'jdoe' . $id,
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'jdoe' . $id . '@moodle.test',
        ];
    }

    private function makeMessage(int $fromId, int $toId): core_message
    {
        $message = new core_message();
        $message->userfrom = (object) ['id' => $fromId];
        $message->userto = (object) ['id' => $toId];

        return $message;
    }
}
