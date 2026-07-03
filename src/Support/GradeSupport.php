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
use Middag\Framework\Shared\Util\Typing as typing;
use Middag\Moodle\Domain\Grade\Grade as grade;
use Middag\Moodle\Domain\Grade\GradeItem as grade_item;
use Middag\Moodle\Shared\Util\Debug as debug;

/**
 * Utility functions for Moodle grades.
 *
 * @internal
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
            $sql = "SELECT gg.{$fields}
                    FROM {grade_grades} gg
                    JOIN {grade_items} gi ON gi.id = gg.itemid
                    WHERE gg.userid = :userid AND gi.courseid = :courseid";

            $param = ['courseid' => $courseid, 'userid' => $userid];

            if ($itemtype !== null && $itemtype !== '') {
                $sql .= ' AND gi.itemtype = :itemtype';
                $param['itemtype'] = $itemtype;
            }

            if ($unique) {
                // Para $unique = true, garantimos que $fields represente um único campo simples.
                $trimmed = trim($fields);
                if ($trimmed === '*' || str_contains($trimmed, ',') || str_contains($trimmed, ' ')) {
                    // Campo inválido para retorno único.
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
                // Se o campo parecer numérico, converte para float (ou int se inteiro).
                if (is_numeric($val)) {
                    $f = typing::toFloat($val);
                    $i = typing::toInt($val);

                    return ((float) $i === $f) ? $i : $f;
                }

                return $val;
            }

            $records = $DB->get_records_sql($sql, $param);
            // Normaliza campos comuns quando presentes.
            foreach ($records as $k => $rec) {
                $spec = [];
                if (property_exists($rec, 'itemid')) {
                    $spec['itemid'] = 'int';
                }
                if (property_exists($rec, 'userid')) {
                    $spec['userid'] = 'int';
                }
                if ($spec !== []) {
                    $records[$k] = typing::normalizeRecord($rec, $spec);
                }
            }

            return $records;
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return [];
        }
    }

    /**
     * Retrieves a grade item by ID.
     *
     * @return null|grade_item the grade item entity or null
     */
    public static function getItem(int $itemid): ?grade_item
    {
        global $DB;

        try {
            $record = $DB->get_record('grade_items', ['id' => $itemid]);

            return $record ? grade_item::fromRecord($record) : null;
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Retrieves all grades for a user in a course, indexed by item ID.
     *
     * @return array<int, grade>
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
                $entity = grade::fromRecord($record);
                $result[(int) $record->itemid] = $entity;
            }

            return $result;
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

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
            debug::traceException($dmlexception);

            return false;
        }
    }
}
