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

use dml_exception;
use Middag\Framework\Shared\Util\Typing;
use Middag\Moodle\Domain\Grade\Grade;
use Middag\Moodle\Domain\Grade\GradeItem;
use Middag\Moodle\Shared\Util\Debug;

/**
 * Utility functions for Moodle grades.
 *
 * @api
 */
class GradeSupport
{
    /**
     * Retrieves grade data for a user in a specific course.
     *
     * @param int         $courseid Course ID
     * @param int         $userid   User ID
     * @param string      $fields   fields to select (default: '*')
     * @param null|string $itemtype optional grade item type filter
     * @param bool        $unique   if true, returns a single field value; otherwise returns an array of records
     *
     * @return mixed scalar value|null if $unique is true, or array of stdClass records otherwise
     */
    public static function getGrade(int $courseid, int $userid, string $fields = '*', ?string $itemtype = null, bool $unique = false): mixed
    {
        global $DB;

        try {
            // Qualify EVERY field with the gg. alias, not just the first.
            // "SELECT gg.{$fields}" only prefixes the first token, so any later
            // field shared by grade_grades and grade_items (id, hidden, locked,
            // timecreated, ...) is left unqualified and the DB rejects it as an
            // ambiguous column. Leave already-qualified tokens and '*' alone.
            $select = implode(', ', array_map(
                static fn (string $f): string => str_contains($f, '.') ? $f : 'gg.' . $f,
                array_map('trim', explode(',', $fields))
            ));

            $sql = "SELECT {$select}
                    FROM {grade_grades} gg
                    JOIN {grade_items} gi ON gi.id = gg.itemid
                    WHERE gg.userid = :userid AND gi.courseid = :courseid";

            $param = ['courseid' => $courseid, 'userid' => $userid];

            if ($itemtype !== null && $itemtype !== '') {
                $sql .= ' AND gi.itemtype = :itemtype';
                $param['itemtype'] = $itemtype;
            }

            if ($unique) {
                // For $unique = true, ensure $fields denotes a single plain field.
                $trimmed = trim($fields);
                if ($trimmed === '*' || str_contains($trimmed, ',') || str_contains($trimmed, ' ')) {
                    // Invalid field for a single-value return.
                    return null;
                }

                $grade = $DB->get_record_sql($sql, $param);
                if ($grade === false || $grade === null) {
                    return null;
                }

                $val = $grade->{$trimmed} ?? null;
                if ($val === null) {
                    return null;
                }
                // Numeric fields come back as int when integral, float otherwise.
                if (is_numeric($val)) {
                    return Typing::toNumber($val);
                }

                return $val;
            }

            $records = $DB->get_records_sql($sql, $param);
            // Normalize common fields when present.
            foreach ($records as $k => $rec) {
                $spec = [];
                if (property_exists($rec, 'itemid')) {
                    $spec['itemid'] = 'int';
                }
                if (property_exists($rec, 'userid')) {
                    $spec['userid'] = 'int';
                }
                if ($spec !== []) {
                    $records[$k] = Typing::normalizeRecord($rec, $spec);
                }
            }

            return $records;
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return [];
        }
    }

    /**
     * Retrieves a grade item by ID.
     *
     * @return null|GradeItem the grade item entity or null
     */
    public static function getItem(int $itemid): ?GradeItem
    {
        global $DB;

        try {
            $record = $DB->get_record('grade_items', ['id' => $itemid]);

            return $record ? GradeItem::fromRecord($record) : null;
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Retrieves all grades for a user in a course, indexed by item ID.
     *
     * @return array<int, Grade>
     */
    public static function getUserGradesForCourse(int $courseid, int $userid): array
    {
        global $DB;

        try {
            $sql = 'SELECT gg.*
                      FROM {grade_grades} gg
                      JOIN {grade_items} gi ON gi.id = gg.itemid
                     WHERE gi.courseid = :courseid AND gg.userid = :userid';

            $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
            $result = [];

            foreach ($records as $record) {
                $entity = Grade::fromRecord($record);
                $result[(int) $record->itemid] = $entity;
            }

            return $result;
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return [];
        }
    }

    /**
     * Whether a course has any gradable items.
     */
    public static function isCourseGradable(int $courseid): bool
    {
        global $DB;

        try {
            return $DB->record_exists('grade_items', ['courseid' => $courseid, 'itemtype' => 'mod']);
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return false;
        }
    }
}
