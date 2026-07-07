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

use calendar_event;
use Middag\Moodle\Domain\Calendar\CalendarEventDto;
use Middag\Moodle\Shared\Util\Debug;
use stdClass;
use Throwable;

// File-scope host-library include: runs at autoload, before any test's coverage window.
// @codeCoverageIgnoreStart
global $CFG;

require_once $CFG->dirroot . '/calendar/lib.php';
// @codeCoverageIgnoreEnd

/**
 * Utility wrapper for Moodle's Calendar API.
 *
 * Provides a stable, static interface for creating, updating, deleting
 * and querying calendar events, converting Moodle records into framework DTOs.
 *
 * @internal
 */
class CalendarSupport
{
    /**
     * Creates a new calendar event from a DTO.
     *
     * @param CalendarEventDto $dto the event data
     *
     * @return null|CalendarEventDto the created event DTO with id populated, or null on failure
     */
    public static function create(CalendarEventDto $dto): ?CalendarEventDto
    {
        try {
            $data = new stdClass();
            $data->name = $dto->name;
            $data->description = $dto->description;
            $data->format = $dto->format;
            $data->eventtype = $dto->eventtype;
            $data->timestart = $dto->timestart;
            $data->timeduration = $dto->timeduration;
            $data->courseid = $dto->courseid ?? 0;
            $data->groupid = $dto->groupid ?? 0;
            $data->userid = $dto->userid ?? 0;
            $data->visible = $dto->visible ? 1 : 0;
            $data->categoryid = $dto->categoryid ?? 0;
            $data->repeats = $dto->repeats;

            $event = calendar_event::create($data);

            return new CalendarEventDto(
                id: (int) $event->id,
                name: $dto->name,
                description: $dto->description,
                format: $dto->format,
                eventtype: $dto->eventtype,
                timestart: $dto->timestart,
                timeduration: $dto->timeduration,
                courseid: $dto->courseid,
                groupid: $dto->groupid,
                userid: $dto->userid,
                visible: $dto->visible,
                categoryid: $dto->categoryid,
                repeats: $dto->repeats,
            );
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Updates an existing calendar event from a DTO.
     *
     * @param CalendarEventDto $dto the event data (id must be set)
     *
     * @return bool true on success, false on failure
     */
    public static function update(CalendarEventDto $dto): bool
    {
        try {
            if ($dto->id === null) {
                return false;
            }

            $event = calendar_event::load($dto->id);

            $data = new stdClass();
            $data->name = $dto->name;
            $data->description = $dto->description;
            $data->format = $dto->format;
            $data->eventtype = $dto->eventtype;
            $data->timestart = $dto->timestart;
            $data->timeduration = $dto->timeduration;
            $data->courseid = $dto->courseid ?? 0;
            $data->groupid = $dto->groupid ?? 0;
            $data->userid = $dto->userid ?? 0;
            $data->visible = $dto->visible ? 1 : 0;
            $data->categoryid = $dto->categoryid ?? 0;
            $data->repeats = $dto->repeats;

            $event->update($data);

            return true;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Deletes a calendar event by its ID.
     *
     * @param int  $id             the event ID
     * @param bool $delete_repeats whether to delete repeated events as well
     *
     * @return bool true on success, false on failure
     */
    public static function delete(int $id, bool $delete_repeats = false): bool
    {
        try {
            $event = calendar_event::load($id);
            $event->delete($delete_repeats);

            return true;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Retrieves a single calendar event by its ID.
     *
     * @param int $id the event ID
     *
     * @return null|CalendarEventDto the event DTO or null if not found
     */
    public static function get(int $id): ?CalendarEventDto
    {
        try {
            $event = calendar_event::load($id);

            return self::mapFromRecord($event->properties());
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Retrieves calendar events for a specific course, optionally filtered by time range.
     *
     * @param int      $courseid  the course ID
     * @param null|int $timestart optional start timestamp filter
     * @param null|int $timeend   optional end timestamp filter
     *
     * @return CalendarEventDto[] list of event DTOs
     */
    public static function getByCourse(int $courseid, ?int $timestart = null, ?int $timeend = null): array
    {
        global $DB;

        try {
            $conditions = ['courseid = :courseid'];
            $params = ['courseid' => $courseid];

            if ($timestart !== null) {
                $conditions[] = 'timestart >= :timestart';
                $params['timestart'] = $timestart;
            }

            if ($timeend !== null) {
                $conditions[] = 'timestart <= :timeend';
                $params['timeend'] = $timeend;
            }

            $select = implode(' AND ', $conditions);
            $records = $DB->get_records_select('event', $select, $params, 'timestart ASC');

            return array_map(
                fn (object $record): CalendarEventDto => self::mapFromRecord($record),
                array_values($records)
            );
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Maps a Moodle event record (stdClass) to a CalendarEventDto.
     *
     * @param object $record the Moodle event record
     *
     * @return CalendarEventDto the mapped DTO
     */
    private static function mapFromRecord(object $record): CalendarEventDto
    {
        return new CalendarEventDto(
            id: isset($record->id) ? (int) $record->id : null,
            name: (string) ($record->name ?? ''),
            description: (string) ($record->description ?? ''),
            format: (int) ($record->format ?? 1),
            eventtype: (string) ($record->eventtype ?? 'user'),
            timestart: (int) ($record->timestart ?? 0),
            timeduration: (int) ($record->timeduration ?? 0),
            courseid: isset($record->courseid) && (int) $record->courseid > 0 ? (int) $record->courseid : null,
            groupid: isset($record->groupid) && (int) $record->groupid > 0 ? (int) $record->groupid : null,
            userid: isset($record->userid) && (int) $record->userid > 0 ? (int) $record->userid : null,
            visible: (bool) ($record->visible ?? true),
            categoryid: isset($record->categoryid) && (int) $record->categoryid > 0 ? (int) $record->categoryid : null,
            repeats: (int) ($record->repeats ?? 0),
        );
    }
}
