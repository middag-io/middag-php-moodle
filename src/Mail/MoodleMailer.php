<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Mail;

use core\user as core_user;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Mail\Address;
use Middag\Framework\Mail\Contract\MailerInterface;
use Middag\Framework\Mail\Mail;
use stdClass;

/**
 * Moodle implementation of the framework mail port: maps a {@see Mail}
 * value object onto `email_to_user()`.
 *
 * Recipients become pseudo-user records cloned from the no-reply user, so
 * arbitrary addresses work without a matching Moodle account. Reply-To maps
 * to the native `$replyto`/`$replytoname` arguments. Each recipient gets its
 * own `email_to_user()` call.
 *
 * `email_to_user()` has no Cc/Bcc and carries at most ONE attachment —
 * those, and embedded `cid:` parts, raise an exception instead of silently
 * degrading. Attachment paths follow the Moodle contract: relative to
 * `$CFG->dataroot`, or an absolute path inside `$CFG->tempdir`.
 *
 * @api
 */
final class MoodleMailer implements MailerInterface
{
    public function send(Mail $mail): void
    {
        if ($mail->cc !== [] || $mail->bcc !== []) {
            throw new MiddagInfrastructureException('email_to_user() has no Cc/Bcc support; send separate mails or use another transport.');
        }

        if (\count($mail->attachments) > 1) {
            throw new MiddagInfrastructureException('email_to_user() carries at most one attachment.');
        }

        $attachment = '';
        $attachname = '';

        foreach ($mail->attachments as $part) {
            if ($part->isEmbedded()) {
                throw new MiddagInfrastructureException(
                    sprintf('email_to_user() cannot embed "cid:%s" parts; attach the file normally or use another transport.', (string) $part->contentId),
                );
            }

            $attachment = $part->path;
            $attachname = $part->filename();
        }

        $from = $mail->from instanceof Address
            ? $this->pseudoUser($mail->from)
            : core_user::get_noreply_user();

        $replyto = $mail->replyTo instanceof Address ? $mail->replyTo->email : '';
        $replytoname = $mail->replyTo instanceof Address ? ($mail->replyTo->name ?? '') : '';

        foreach ($mail->to as $recipient) {
            $sent = email_to_user(
                $this->pseudoUser($recipient),
                $from,
                $mail->subject,
                $mail->body,
                $mail->htmlBody ?? '',
                $attachment,
                $attachname,
                true,
                $replyto,
                $replytoname,
            );

            if (!$sent) {
                throw new MiddagInfrastructureException(
                    sprintf('email_to_user() failed to send "%s" to %s.', $mail->subject, $recipient->email),
                );
            }
        }
    }

    /**
     * Builds a deliverable pseudo-user record for an arbitrary address,
     * cloned from the no-reply user so every field Moodle's mail pipeline
     * reads (id, deleted, auth, maildisplay, ...) is present.
     */
    private function pseudoUser(Address $address): stdClass
    {
        // (object)(array) copy — the moodle-stubs signature declares a namespace-relative stdClass return.
        $user = (object) (array) core_user::get_noreply_user();
        $user->email = $address->email;
        $user->firstname = $address->name ?? $address->email;
        $user->lastname = '';
        $user->mailformat = 1;
        $user->emailstop = 0;

        return $user;
    }
}
