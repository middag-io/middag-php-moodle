<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Contract;

/**
 * Public contract for the message_service facade.
 *
 * Extensions depend ONLY on this interface — never on the core implementation.
 * No Moodle/Core dependencies are allowed inside this contract.
 *
 * @api
 */
interface MessageServiceInterface
{
    /**
     * Send an email message.
     *
     * @param mixed      $from        Sender descriptor (user or address)
     * @param mixed      $to          Recipient descriptor (user or address)
     * @param string     $subject     Email subject
     * @param string     $text        Email body
     * @param int        $courseid    Course context for messaging
     * @param null|array $attachments Optional list of attachments
     */
    public function email(
        mixed $from,
        mixed $to,
        string $subject,
        string $text,
        int $courseid = SITEID,
        ?array $attachments = null
    ): void;

    /**
     * Send a Moodle message (non-notification).
     *
     * @param mixed  $from     Sender descriptor (user or address)
     * @param mixed  $to       Recipient descriptor (user or address)
     * @param string $subject  Message subject
     * @param string $text     Message body
     * @param int    $courseid Course context for messaging
     *
     * @return mixed Implementation-defined result of the send operation
     */
    public function message(
        mixed $from,
        mixed $to,
        string $subject,
        string $text,
        int $courseid = SITEID
    ): mixed;

    /**
     * Send a Moodle notification.
     *
     * @param mixed       $from     Sender descriptor (user or address)
     * @param mixed       $to       Recipient descriptor (user or address)
     * @param string      $subject  Notification subject
     * @param string      $text     Notification body
     * @param int         $courseid Course context for messaging
     * @param null|mixed  $url      Optional URL payload
     * @param null|string $urlname  Optional URL label
     *
     * @return mixed Implementation-defined result of the send operation
     */
    public function notification(
        mixed $from,
        mixed $to,
        string $subject,
        string $text,
        int $courseid = SITEID,
        mixed $url = null,
        ?string $urlname = null
    ): mixed;

    /**
     * Prepare a structured message object.
     *
     * @param mixed  $from     Sender descriptor (user or address)
     * @param mixed  $to       Recipient descriptor (user or address)
     * @param string $subject  Message subject
     * @param string $text     Message body
     * @param int    $courseid Course context for messaging
     *
     * @return mixed Prepared message structure
     */
    public function prepare(
        mixed $from,
        mixed $to,
        string $subject,
        string $text,
        int $courseid = SITEID
    ): mixed;

    /**
     * Get or create a conversation ID for a message.
     *
     * @param mixed $message Prepared message structure
     *
     * @return int Conversation identifier
     */
    public function getConversationId(mixed $message): int;

    /**
     * Replace template variables in a message.
     *
     * @param mixed $course Course reference
     * @param mixed $user   User reference
     *
     * @return array<string, mixed> Prepared text placeholders
     */
    public function prepareText(mixed $course, mixed $user): array;
}
