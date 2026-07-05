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

use core\context\system as context_system;
use dml_exception;
use Middag\Framework\Shared\Util\Typing;
use Middag\Moodle\Domain\Group\Cohort;
use Middag\Moodle\Domain\Group\CohortMemberDto;
use Middag\Moodle\Shared\Util\Debug;
use stdClass;

global $CFG;

require_once $CFG->dirroot . '/cohort/lib.php';

/**
 * Utility functions for Moodle cohorts.
 *
 * @internal
 */
class CohortSupport
{
    /**
     * Retrieves cohorts for a given context with pagination and optional search.
     *
     * @param int    $contextid Context ID
     * @param int    $page      page number (0-based)
     * @param int    $perpage   items per page
     * @param string $search    optional search term
     *
     * @return array<int, object> list of cohort objects (as returned by cohort_get_cohorts)
     */
    public static function getCohorts(int $contextid, int $page = 0, int $perpage = 25, string $search = ''): array
    {
        return cohort_get_cohorts($contextid, $page, $perpage, $search);
    }

    /**
     * Retrieves cohorts with normalization and total count.
     *
     * Some Moodle versions return an associative array with keys like 'cohorts' and 'totalcohorts'.
     * This method abstracts that variation and always returns a normalized structure.
     *
     * @param int    $contextid Context ID
     * @param int    $page      page number (0-based)
     * @param int    $perpage   items per page
     * @param string $search    optional search term
     *
     * @return array{items: array<int, Cohort>, total: int} normalized items and total count
     */
    public static function getCohortsWithTotal(int $contextid, int $page = 0, int $perpage = 25, string $search = ''): array
    {
        $raw = self::getCohorts($contextid, $page, $perpage, $search);

        // Possible cases: plain list or structure with keys.
        if (is_array($raw)) {
            if (array_key_exists('cohorts', $raw) && array_key_exists('totalcohorts', $raw)) {
                $items = $raw['cohorts'] ?? [];
                $total = Typing::toInt($raw['totalcohorts'] ?? count($items));
            } else {
                $items = $raw;
                $total = count($items);
            }
        } else {
            $items = [];
            $total = 0;
        }

        // Normalize items to entities.
        $normalized = [];
        foreach ($items as $it) {
            if (is_object($it)) {
                $normalized[] = Cohort::fromRecord((object) $it);
            }
        }

        return [
            'items' => $normalized,
            'total' => Typing::toInt($total),
        ];
    }

    /**
     * Retrieves all visible cohorts as an options list [id => name].
     *
     * @return array<int, string> Map of cohort ID to cohort name
     */
    public static function getAll(): array
    {
        global $DB;

        try {
            $cohorts = $DB->get_records('cohort', ['visible' => 1]);
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);
            $cohorts = [];
        }
        $options = [];
        foreach ($cohorts as $cohort) {
            $options[Typing::toInt($cohort->id)] = Typing::toString($cohort->name);
        }

        return $options;
    }

    /**
     * Creates a new cohort if a cohort with the given idnumber does not exist.
     *
     * @param string $name     The name of the cohort to create
     * @param string $idnumber The unique identifier for the cohort
     */
    public static function createCohort(string $name, string $idnumber): void
    {
        global $DB;

        if (!$DB->record_exists('cohort', ['idnumber' => $idnumber])) {
            $cohort = new stdClass();
            $cohort->idnumber = $idnumber;
            $cohort->name = $name;
            $cohort->contextid = context_system::instance()->id;

            cohort_add_cohort($cohort);
        }
    }

    /**
     * Returns cohort members as typed DTOs.
     *
     * @return array<int, CohortMemberDto> indexed by user ID
     */
    public static function getMembers(int $cohortid): array
    {
        global $DB;

        try {
            $records = $DB->get_records('cohort_members', ['cohortid' => $cohortid]);
            $result = [];

            foreach ($records as $record) {
                $result[(int) $record->userid] = new CohortMemberDto(
                    cohortid: (int) $record->cohortid,
                    userid: (int) $record->userid,
                    timeadded: (int) $record->timeadded,
                );
            }

            return $result;
        } catch (dml_exception) {
            return [];
        }
    }
}
