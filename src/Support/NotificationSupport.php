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
use core\user as core_user;
use core_message\api;
use message_popup\api as popup_api;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Domain\Message\NotificationDto;
use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * System-to-user notification wrapper for Moodle's Message API.
 *
 * Handles notifications where `notification = 1` on the Moodle message object.
 * Complements {@see MessageSupport} which handles inter-user messaging and email.
 *
 * @api
 */
class NotificationSupport
{
    /**
     * Sends a system-to-user notification via Moodle's messaging API.
     *
     * Creates a core message object with `notification = 1`, resolves sender
     * and recipient user records, and dispatches via `message_send()`.
     *
     * @param NotificationDto $notification the notification data
     *
     * @return null|int message ID on success, null on failure
     */
    public static function send(NotificationDto $notification): ?int
    {
        try {
            $msg = new core_message();
            $msg->notification = 1;
            $msg->component = $notification->component;
            $msg->name = $notification->name;

            $msg->userfrom = $notification->useridFrom !== null
                ? core_user::get_user($notification->useridFrom)
                : core_user::get_noreply_user();

            $msg->userto = core_user::get_user($notification->useridTo);

            $msg->subject = $notification->subject;
            $msg->fullmessage = $notification->fullMessage;
            // fullMessage is HTML only when it IS the HTML version (the
            // sendSimple() convention, where both fields carry the same markup).
            // A caller following the DTO contract — plain text in fullMessage,
            // distinct HTML in fullMessageHtml — must be FORMAT_PLAIN, or a
            // FORMAT_HTML-aware reader runs it through format_text() and mangles
            // it (e.g. strips '<needs review>' as a bogus tag).
            $msg->fullmessageformat = $notification->fullMessage === $notification->fullMessageHtml
                ? FORMAT_HTML
                : FORMAT_PLAIN;
            $msg->fullmessagehtml = $notification->fullMessageHtml;
            $msg->smallmessage = $notification->shortMessage;

            if ($notification->contextUrl !== null) {
                $msg->contexturl = $notification->contextUrl;
                $msg->contexturlname = $notification->contextUrlName;
            }

            if ($notification->courseid !== null) {
                $msg->courseid = $notification->courseid;
            }

            $result = message_send($msg);

            return $result !== false ? (int) $result : null;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Sends a simple system notification with minimal configuration.
     *
     * Convenience method that creates a {@see NotificationDto} internally
     * using `local_example` as component and `system_notification` as name.
     *
     * @param int         $userid_to    recipient user ID
     * @param string      $subject      notification subject line
     * @param string      $message_html HTML content (used as both full and HTML message)
     * @param null|string $context_url  optional URL to link to
     *
     * @return null|int message ID on success, null on failure
     */
    public static function sendSimple(
        int $userid_to,
        string $subject,
        string $message_html,
        ?string $context_url = null,
    ): ?int {
        $notification = new NotificationDto(
            component: ComponentContext::name(),
            name: 'system_notification',
            useridTo: $userid_to,
            subject: $subject,
            fullMessage: $message_html,
            fullMessageHtml: $message_html,
            contextUrl: $context_url,
        );

        return self::send($notification);
    }

    /**
     * Returns the count of unread popup notifications for a user.
     *
     * @param int $userid the user ID
     *
     * @return int unread notification count, 0 on error
     */
    public static function getUnreadCount(int $userid): int
    {
        // count_unread_popup_notifications() treats a 0 userid as empty() and
        // substitutes $USER, leaking the current session user's count for a call
        // the caller scoped to id 0. Honour the documented per-user contract.
        if ($userid <= 0) {
            return 0;
        }

        try {
            return popup_api::count_unread_popup_notifications($userid);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return 0;
        }
    }

    /**
     * Marks a notification as read.
     *
     * @param int      $notificationid the notification record ID
     * @param null|int $timeread       timestamp to mark as read (null = current time)
     *
     * @return bool true on success, false on failure
     */
    public static function markRead(int $notificationid, ?int $timeread = null): bool
    {
        global $DB;

        try {
            $notification = $DB->get_record('notifications', ['id' => $notificationid]);

            if (!$notification) {
                return false;
            }

            api::mark_notification_as_read($notification, $timeread);

            return true;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }
}
