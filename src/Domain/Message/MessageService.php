<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Message;

use core\exception\moodle_exception;
use core\message\message;
use core\url as moodle_url;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Domain\Message\Contract\MessageServiceInterface;
use Middag\Moodle\Support\ConfigSupport;
use Middag\Moodle\Support\CourseSupport;
use Middag\Moodle\Support\HtmlWriterSupport;
use Middag\Moodle\Support\LangSupport;
use Middag\Moodle\Support\MessageSupport;
use Middag\Moodle\Support\UrlSupport;
use Middag\Moodle\Support\UserSupport;
use stored_file;

/**
 * Message Service.
 *
 * Facade for Moodle's messaging and email subsystems with token replacement support.
 *
 * @internal
 *
 * @see MessageServiceInterface
 */
class MessageService implements MessageServiceInterface
{
    public const NAME = 'middagsender';

    /**
     * Component name used as the message provider component.
     *
     * Resolved from the composition-root {@see ComponentContext} seam instead of
     * a hard-coded plugin constant, keeping the adapter product-agnostic.
     */
    public static function component(): string
    {
        return ComponentContext::name();
    }

    /**
     * Send an email message using Moodle's messaging subsystem.
     *
     * @param mixed              $from        Sender descriptor (user or address)
     * @param mixed              $to          Recipient descriptor (user or address)
     * @param string             $subject     Email subject
     * @param string             $text        Email body (HTML)
     * @param int                $courseid    Course context
     * @param null|stored_file[] $attachments Optional attachment list
     *
     * @throws moodle_exception
     */
    public function email(mixed $from, mixed $to, string $subject, string $text, int $courseid = 0, ?array $attachments = null): void
    {
        if ($courseid === 0) {
            $courseid = ConfigSupport::getSiteId();
        }

        $course = CourseSupport::getCourse($courseid);

        $data = $this->prepareText($course, $to);

        foreach ($data as $key => $value) {
            $text = str_replace($key, (string) $value, $text);
            $subject = str_replace($key, (string) $value, $subject);
        }

        $attachment = '';
        $attachname = '';

        if (is_array($attachments)) {
            $file = array_shift($attachments);
            // Ensure temp directory is within request lifespan
            if ($file instanceof stored_file) {
                [$attachment, $attachname] = MessageSupport::createTempAttachment($file);
            }
        }

        MessageSupport::email($to, $from, $subject, $text, HtmlWriterSupport::htmlToText($text), $attachment, $attachname);
    }

    /**
     * Send an in-app message.
     *
     * @param mixed  $from     Sender descriptor (user or address)
     * @param mixed  $to       Recipient descriptor (user or address)
     * @param string $subject  Message subject
     * @param string $text     Message body (HTML)
     * @param int    $courseid Course context
     *
     * @return mixed Result from Moodle messaging API
     *
     * @throws moodle_exception
     */
    public function message(mixed $from, mixed $to, string $subject, string $text, int $courseid = 0): mixed
    {
        if ($courseid === 0) {
            $courseid = ConfigSupport::getSiteId();
        }

        $course = CourseSupport::getCourse($courseid);

        $data = $this->prepareText($course, $to);

        foreach ($data as $key => $value) {
            $text = str_replace($key, (string) $value, $text);
            $subject = str_replace($key, (string) $value, $subject);
        }

        $message = $this->prepare($from, $to, $subject, HtmlWriterSupport::tag('h5', $subject) . $text);
        $message->notification = 0;

        return MessageSupport::send($message);
    }

    /**
     * Send a notification (non-reply) message.
     *
     * @param mixed       $from     Sender descriptor
     * @param mixed       $to       Recipient descriptor
     * @param string      $subject  Notification subject
     * @param string      $text     Notification body (HTML)
     * @param int         $courseid Course context
     * @param null|mixed  $url      Optional context URL
     * @param null|string $urlname  Optional URL label
     *
     * @return mixed Result from Moodle messaging API
     *
     * @throws moodle_exception
     */
    public function notification(mixed $from, mixed $to, string $subject, string $text, int $courseid = 0, mixed $url = null, ?string $urlname = null): mixed
    {
        if ($courseid === 0) {
            $courseid = ConfigSupport::getSiteId();
        }

        $course = CourseSupport::getCourse($courseid);

        $data = $this->prepareText($course?->asStdClass(), $to);

        foreach ($data as $key => $value) {
            $text = str_replace($key, (string) $value, $text);
            $subject = str_replace($key, (string) $value, $subject);
        }

        $message = $this->prepare($from, $to, $subject, $text, $course?->getId());
        $message->userfrom = MessageSupport::getNoreplyUser();
        $message->notification = 1;

        if ($url instanceof moodle_url) {
            $message->contexturl = $url->out(false);
        } elseif (is_string($url) && $url !== '') {
            $message->contexturl = $url;
        }

        if (!in_array($urlname, [null, '', '0'], true)) {
            $message->contexturlname = $urlname;
        }

        return MessageSupport::send($message);
    }

    /**
     * Prepare a core message payload.
     *
     * @param mixed  $from     Sender descriptor
     * @param mixed  $to       Recipient descriptor
     * @param string $subject  Message subject
     * @param string $text     Message body (HTML)
     * @param int    $courseid Course context
     *
     * @return message
     */
    public function prepare(mixed $from, mixed $to, string $subject, string $text, int $courseid = 0): message
    {
        if ($courseid === 0) {
            $courseid = ConfigSupport::getSiteId();
        }

        $class = static::class;

        $message = MessageSupport::createMessage();
        $message->component = $class::component();
        $message->name = $class::NAME;
        $message->userfrom = $from;
        $message->userto = $to;
        $message->subject = $subject;
        $message->smallmessage = $text;
        $message->fullmessage = $text;
        $message->fullmessagehtml = $text;
        $message->fullmessageformat = FORMAT_HTML;
        $message->courseid = $courseid;

        $message->convid = MessageSupport::getConversationId($message);

        return $message;
    }

    /**
     * Retrieve the conversation identifier for a prepared message.
     */
    public function getConversationId(mixed $message): int
    {
        return MessageSupport::getConversationId($message);
    }

    /**
     * Build placeholder replacements for user/course context.
     *
     * @param mixed $course Course reference
     * @param mixed $user   User reference
     *
     * @return array<string, mixed>
     *
     * @throws moodle_exception
     */
    public function prepareText(mixed $course, mixed $user): array
    {
        $site_info = ConfigSupport::getSiteInfo();

        if (!$course) {
            $course = get_site();
        }

        $forgotpasswordurl = ConfigSupport::getGlobal('forgottenpasswordurl');
        if (empty($forgotpasswordurl)) {
            $forgotpasswordurl = UrlSupport::get('/login/forgot_password.php');
        }

        $supportemail = ConfigSupport::getGlobal('supportemail');
        $mailto = UrlSupport::get('mailto:' . $supportemail);

        return [
            '[[username]]' => $user->username,
            '[[firstname]]' => $user->firstname,
            '[[lastname]]' => $user->lastname,
            '[[fullname]]' => UserSupport::fullname($user),
            '[[coursename]]' => $course->fullname,
            '[[courselink]]' => HtmlWriterSupport::link(UrlSupport::get('/course/view.php', ['id' => $course->id]), $course->fullname),
            '[[courseurl]]' => UrlSupport::get('/course/view.php', ['id' => $course->id])->out(false),
            '[[sitename]]' => $site_info->fullname,
            '[[sitelink]]' => HtmlWriterSupport::link(UrlSupport::get('/'), $site_info->fullname),
            '[[forgotpasswordlink]]' => HtmlWriterSupport::link($forgotpasswordurl, LangSupport::get('forgotpassword', ComponentContext::name())),
            '[[forgotpasswordurl]]' => $forgotpasswordurl instanceof moodle_url ? $forgotpasswordurl->out() : $forgotpasswordurl,
            '[[supportemail]]' => $supportemail,
            '[[supportemailmailto]]' => $mailto->out(),
        ];
    }
}
