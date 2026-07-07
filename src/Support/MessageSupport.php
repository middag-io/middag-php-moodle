<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use core\message\message as core_message;
use core\url as moodle_url;
use core\user as core_user;
use core_message\api;
use stdClass;
use stored_file;

/**
 * Utility functions for Moodle messaging and emails.
 *
 * @api
 */
class MessageSupport
{
    /** @var int Self conversation type. */
    public const CONVERSATION_TYPE_SELF = api::MESSAGE_CONVERSATION_TYPE_SELF;

    /** @var int Individual conversation type. */
    public const CONVERSATION_TYPE_INDIVIDUAL = api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL;

    /**
     * Creates a new Moodle message object.
     *
     * @return core_message Message object
     */
    public static function createMessage(): core_message
    {
        return new core_message();
    }

    /**
     * Sends a message using Moodle's message API.
     *
     * @param core_message $msg Message object
     *
     * @return false|int Message ID or false on failure
     */
    public static function send(core_message $msg)
    {
        return message_send($msg);
    }

    /**
     * Sends an email to a user.
     *
     * @param stdClass $to         Recipient user object
     * @param stdClass $from       Sender user object
     * @param string   $subject    the email subject
     * @param string   $html       the HTML content
     * @param string   $text       the plain text content
     * @param string   $attachment path to attachment file
     * @param string   $name       attachment name
     *
     * @return bool True on success, false otherwise
     */
    public static function email(object $to, $from, string $subject, string $html, string $text, $attachment = '', $name = ''): bool
    {
        return email_to_user($to, $from, $subject, $text, $html, $attachment, $name);
    }

    /**
     * Retrieves the Moodle no-reply user record.
     *
     * @return stdClass the no-reply user object
     */
    public static function getNoreplyUser()
    {
        return core_user::get_noreply_user();
    }

    /**
     * Creates a temporary attachment from a stored file.
     *
     * @param stored_file $file the stored file object
     *
     * @return array{0: string, 1: string} path and filename of the temporary attachment
     */
    public static function createTempAttachment(stored_file $file): array
    {
        $dir = make_temp_directory('middag/mtool_automessage');
        $filename = $file->get_filename();
        $path = $dir . '/' . $filename;
        file_put_contents($path, $file->get_content());

        return [$path, $filename];
    }

    /**
     * Retrieves the conversation ID for a given message.
     *
     * @param core_message $message Message object
     *
     * @return int the conversation ID
     */
    public static function getConversationId(core_message $message): int
    {
        $from_userid = (int) $message->userfrom->id;
        $to_userid = (int) $message->userto->id;

        if ($from_userid === $to_userid) {
            // Self conversation
            $conversation = self::getSelfConversation($from_userid);
            if (empty($conversation)) {
                $conversation = self::createConversation(self::CONVERSATION_TYPE_SELF, [$from_userid]);
            }

            return (int) $conversation->id;
        }

        // Private conversation
        $conversationid = self::getConversationBetweenUsers([$from_userid, $to_userid]);

        if (empty($conversationid)) {
            $conversation = self::createConversation(self::CONVERSATION_TYPE_INDIVIDUAL, [$from_userid, $to_userid]);

            return (int) $conversation->id;
        }

        return (int) $conversationid;
    }

    /**
     * Creates an HTML link for use in messages.
     *
     * @param moodle_url $url  the destination URL
     * @param string     $text the link text
     *
     * @return string the generated HTML link
     */
    public static function createLink(moodle_url $url, string $text): string
    {
        return HtmlWriterSupport::link($url, $text);
    }

    /**
     * Retrieves the self-conversation object for a user.
     *
     * @param int $userid User ID
     *
     * @return false|stdClass the conversation object or false
     */
    public static function getSelfConversation(int $userid)
    {
        return api::get_self_conversation($userid);
    }

    /**
     * Creates a new conversation between users.
     *
     * @param int   $type    the conversation type
     * @param array $userids list of user IDs
     *
     * @return stdClass the conversation object
     */
    public static function createConversation(int $type, array $userids)
    {
        return api::create_conversation($type, $userids);
    }

    /**
     * Retrieves the ID of a conversation between specific users.
     *
     * @param array $userids list of user IDs
     *
     * @return false|int the conversation ID or false if not found
     */
    public static function getConversationBetweenUsers(array $userids)
    {
        return api::get_conversation_between_users($userids);
    }
}
