<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Mail;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Mail\Attachment;
use Middag\Framework\Mail\Mail;
use Middag\Moodle\Mail\MoodleMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MoodleMailer::class)]
final class MoodleMailerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__middag_test_emails'] = [];
        unset($GLOBALS['__middag_test_email_result']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_emails'], $GLOBALS['__middag_test_email_result']);
    }

    #[Test]
    public function sendsToEachRecipientAsPseudoUser(): void
    {
        $mailer = new MoodleMailer();
        $mailer->send(new Mail(
            to: ['jane@example.org', 'John <john@example.org>'],
            subject: 'Hello',
            body: 'Plain text',
        ));

        $sent = $GLOBALS['__middag_test_emails'];
        self::assertCount(2, $sent);
        self::assertSame('jane@example.org', $sent[0]['to']->email);
        self::assertSame('john@example.org', $sent[1]['to']->email);
        self::assertSame('John', $sent[1]['to']->firstname);
        self::assertSame('Hello', $sent[0]['subject']);
        self::assertSame('Plain text', $sent[0]['text']);
        self::assertSame('noreply@example.test', $sent[0]['from']->email, 'defaults to the no-reply user');
    }

    #[Test]
    public function mapsFromReplyToHtmlBodyAndSingleAttachment(): void
    {
        $mailer = new MoodleMailer();
        $mailer->send(new Mail(
            to: ['jane@example.org'],
            subject: 'Report',
            body: 'text',
            htmlBody: '<p>html</p>',
            from: 'Sender <sender@example.org>',
            replyTo: 'Support <support@example.org>',
            attachments: [new Attachment('exports/report.pdf', 'report.pdf')],
        ));

        $sent = $GLOBALS['__middag_test_emails'][0];
        self::assertSame('sender@example.org', $sent['from']->email);
        self::assertSame('<p>html</p>', $sent['html']);
        self::assertSame('support@example.org', $sent['replyto']);
        self::assertSame('Support', $sent['replytoname']);
        self::assertSame('exports/report.pdf', $sent['attachment']);
        self::assertSame('report.pdf', $sent['attachname']);
    }

    #[Test]
    public function rejectsCcAndBcc(): void
    {
        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('no Cc/Bcc support');

        (new MoodleMailer())->send(new Mail(
            to: ['jane@example.org'],
            subject: 'x',
            body: 'x',
            cc: ['copy@example.org'],
        ));
    }

    #[Test]
    public function rejectsMultipleAttachments(): void
    {
        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('at most one attachment');

        (new MoodleMailer())->send(new Mail(
            to: ['jane@example.org'],
            subject: 'x',
            body: 'x',
            attachments: ['a.pdf', 'b.pdf'],
        ));
    }

    #[Test]
    public function rejectsEmbeddedParts(): void
    {
        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('cid:logo');

        (new MoodleMailer())->send(new Mail(
            to: ['jane@example.org'],
            subject: 'x',
            body: 'x',
            attachments: [Attachment::embedded('logo.png', 'logo')],
        ));
    }

    #[Test]
    public function throwsWhenTransportReportsFailure(): void
    {
        $GLOBALS['__middag_test_email_result'] = false;

        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('failed to send');

        (new MoodleMailer())->send(new Mail(to: ['jane@example.org'], subject: 'x', body: 'x'));
    }
}
