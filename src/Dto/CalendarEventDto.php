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
 * Data Transfer Object for Moodle calendar events.
 *
 * Represents a calendar event independent of the Moodle core API,
 * suitable for creation, update and read operations.
 *
 * @api
 */
final class CalendarEventDto extends abstract_dto
{
    /**
     * Constructor.
     *
     * @param null|int $id           Event ID (null for new events, set for existing)
     * @param string   $name         Event name/title
     * @param string   $description  Event description
     * @param int      $format       Description format (FORMAT_HTML = 1)
     * @param string   $eventtype    Event type: user|site|course|group|category
     * @param int      $timestart    Unix timestamp start
     * @param int      $timeduration Duration in seconds (0 = no duration)
     * @param null|int $courseid     Course ID (null = site-level)
     * @param null|int $groupid      Group ID (null = no group)
     * @param null|int $userid       User ID (null = current user)
     * @param bool     $visible      Whether event is visible
     * @param null|int $categoryid   Category ID for category events
     * @param int      $repeats      Number of repeats (0 = no repeat)
     */
    public function __construct(
        /** Event ID (null for new events, set for existing). */
        public ?int $id = null,
        /** Event name/title. */
        public string $name = '',
        /** Event description. */
        public string $description = '',
        /** Description format (FORMAT_HTML = 1). */
        public int $format = 1,
        /** Event type: user|site|course|group|category. */
        public string $eventtype = 'user',
        /** Unix timestamp start. */
        public int $timestart = 0,
        /** Duration in seconds (0 = no duration). */
        public int $timeduration = 0,
        /** Course ID (null = site-level). */
        public ?int $courseid = null,
        /** Group ID (null = no group). */
        public ?int $groupid = null,
        /** User ID (null = current user). */
        public ?int $userid = null,
        /** Whether event is visible. */
        public bool $visible = true,
        /** Category ID for category events. */
        public ?int $categoryid = null,
        /** Number of repeats (0 = no repeat). */
        public int $repeats = 0,
    ) {}

    /**
     * Convert the DTO to an associative array.
     *
     * @return array<string, null|bool|int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'eventtype' => $this->eventtype,
            'timestart' => $this->timestart,
            'timeduration' => $this->timeduration,
            'courseid' => $this->courseid,
            'userid' => $this->userid,
            'visible' => $this->visible,
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
