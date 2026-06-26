<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Dto;

use Middag\Framework\Shared\Dto\AbstractDto as abstract_dto;
use stdClass;

/**
 * Data Transfer Object for system-to-user notifications.
 *
 * Represents notification data independent of the Moodle core message API.
 *
 * @api
 */
final class NotificationDto extends abstract_dto
{
    /**
     * Constructor.
     *
     * @param string      $component       Frankenstyle component (e.g. 'local_example')
     * @param string      $name            Message provider name (registered in db/messages.php)
     * @param int         $useridTo        Recipient user ID
     * @param string      $subject         Notification subject line
     * @param string      $fullMessage     Full text version
     * @param string      $fullMessageHtml Full HTML version
     * @param string      $shortMessage    Short version for popup
     * @param null|int    $useridFrom      Sender user ID (null = system/noreply)
     * @param null|string $contextUrl      URL to link to
     * @param null|string $contextUrlName  Display name for the URL
     * @param null|int    $courseid        Course context ID
     */
    public function __construct(
        /** Frankenstyle component (e.g. 'local_example'). */
        public string $component,
        /** Message provider name (registered in db/messages.php). */
        public string $name,
        /** Recipient user ID. */
        public int $useridTo,
        /** Notification subject line. */
        public string $subject,
        /** Full text version. */
        public string $fullMessage,
        /** Full HTML version. */
        public string $fullMessageHtml,
        /** Short version for popup. */
        public string $shortMessage = '',
        /** Sender user ID (null = system/noreply). */
        public ?int $useridFrom = null,
        /** URL to link to. */
        public ?string $contextUrl = null,
        /** Display name for the URL. */
        public ?string $contextUrlName = null,
        /** Course context ID. */
        public ?int $courseid = null,
    ) {}

    /**
     * Convert the DTO to an associative array.
     *
     * @return array<string, null|int|string>
     */
    public function toArray(): array
    {
        return [
            'component' => $this->component,
            'name' => $this->name,
            'userid_to' => $this->useridTo,
            'subject' => $this->subject,
            'full_message' => $this->fullMessage,
            'full_message_html' => $this->fullMessageHtml,
            'short_message' => $this->shortMessage,
            'userid_from' => $this->useridFrom,
            'context_url' => $this->contextUrl,
            'context_url_name' => $this->contextUrlName,
            'courseid' => $this->courseid,
        ];
    }

    /**
     * Convert the DTO to a stdClass.
     */
    public function toObject(): stdClass
    {
        $obj = new stdClass();
        foreach ($this->toArray() as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }
}
