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
use core_competency\api;
use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * Utility wrapper for Moodle's Competency API.
 *
 * Provides a stable, static interface for querying competency frameworks,
 * competencies, user competency progress and evidence recording, converting
 * Moodle persistent objects into plain arrays at the boundary.
 *
 * @internal
 */
class CompetencySupport
{
    /** @var int Evidence action: log only, no proficiency change. */
    public const ACTION_LOG = 0;

    /** @var int Evidence action: mark competency as complete. */
    public const ACTION_COMPLETE = 1;

    /** @var int Evidence action: override current proficiency grade. */
    public const ACTION_OVERRIDE = 2;

    /**
     * Checks whether the competency subsystem is enabled site-wide.
     *
     * @return bool true if competencies are enabled, false otherwise
     */
    public static function isEnabled(): bool
    {
        try {
            return api::is_enabled();
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Lists competency frameworks visible to the current user.
     *
     * @param int $skip  number of records to skip
     * @param int $limit maximum number of records to return (0 = no limit)
     *
     * @return array list of framework arrays with keys: id, shortname, idnumber,
     *               description, visible, scaleid, timecreated, timemodified
     */
    public static function listFrameworks(int $skip = 0, int $limit = 0): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        try {
            $frameworks = api::list_frameworks('shortname', 'ASC', $skip, $limit, context_system::instance());

            return array_map(
                fn (object $fw): array => self::mapFramework($fw),
                array_values($frameworks)
            );
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Retrieves a single competency framework by its ID.
     *
     * @param int $id the framework ID
     *
     * @return null|array framework array or null if not found or disabled
     */
    public static function getFramework(int $id): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }

        try {
            $framework = api::read_framework($id);

            return self::mapFramework($framework);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Lists competencies belonging to a given framework.
     *
     * @param int $frameworkid the competency framework ID
     * @param int $skip        number of records to skip
     * @param int $limit       maximum number of records to return (0 = no limit)
     *
     * @return array list of competency arrays with keys: id, shortname, idnumber,
     *               description, parentid, path, sortorder, competencyframeworkid
     */
    public static function listCompetencies(int $frameworkid, int $skip = 0, int $limit = 0): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        try {
            $competencies = api::list_competencies(
                ['competencyframeworkid' => $frameworkid],
                '',
                'ASC',
                $skip,
                $limit
            );

            return array_map(
                fn (object $c): array => self::mapCompetency($c),
                array_values($competencies)
            );
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Retrieves a single competency by its ID.
     *
     * @param int $id the competency ID
     *
     * @return null|array competency array or null if not found or disabled
     */
    public static function getCompetency(int $id): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }

        try {
            $competency = api::read_competency($id);

            return self::mapCompetency($competency);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Retrieves a user's status for a specific competency.
     *
     * @param int $userid       the user ID
     * @param int $competencyid the competency ID
     *
     * @return null|array user competency array with keys: id, userid, competencyid,
     *                    proficiency, grade, status, reviewerid, timecreated, timemodified;
     *                    or null if disabled or on error
     */
    public static function getUserCompetency(int $userid, int $competencyid): ?array
    {
        if (!self::isEnabled()) {
            return null;
        }

        try {
            $uc = api::get_user_competency($userid, $competencyid);

            return self::mapUserCompetency($uc);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Lists a user's competency progress within a specific course.
     *
     * @param int $courseid the course ID
     * @param int $userid   the user ID
     *
     * @return array list of user competency arrays with keys: id, userid, competencyid,
     *               courseid, proficiency, grade, timecreated, timemodified
     */
    public static function listUserCompetenciesInCourse(int $courseid, int $userid): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        try {
            $records = api::list_user_competencies_in_course($courseid, $userid);

            return array_map(
                fn (object $uc): array => [
                    'id' => (int) $uc->get('id'),
                    'userid' => (int) $uc->get('userid'),
                    'competencyid' => (int) $uc->get('competencyid'),
                    'courseid' => (int) $uc->get('courseid'),
                    'proficiency' => $uc->get('proficiency'),
                    'grade' => $uc->get('grade'),
                    'timecreated' => (int) $uc->get('timecreated'),
                    'timemodified' => (int) $uc->get('timemodified'),
                ],
                array_values($records)
            );
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Records evidence for a user's competency.
     *
     * @param int         $userid                the user ID
     * @param int         $competencyid          the competency ID
     * @param int         $action                one of ACTION_LOG, ACTION_COMPLETE, ACTION_OVERRIDE
     * @param string      $description_key       language string key for the evidence description
     * @param string      $description_component component owning the language string
     * @param null|string $note                  optional free-text note
     * @param null|int    $grade                 optional grade value (required for ACTION_OVERRIDE)
     *
     * @return null|int evidence ID on success, null on failure or if disabled
     */
    public static function addEvidence(
        int $userid,
        int $competencyid,
        int $action,
        string $description_key,
        string $description_component,
        ?string $note = null,
        ?int $grade = null,
    ): ?int {
        if (!self::isEnabled()) {
            return null;
        }

        try {
            $evidence = api::add_evidence(
                $userid,
                $competencyid,
                context_system::instance(),
                $action,
                $description_key,
                $description_component,
                null,
                false,
                null,
                $grade,
                null,
                $note
            );

            return $evidence ? (int) $evidence->get('id') : null;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Lists evidence records for a user+competency pair.
     *
     * @param int $userid       the user ID
     * @param int $competencyid the competency ID
     * @param int $skip         number of records to skip
     * @param int $limit        maximum number of records to return (0 = no limit)
     *
     * @return array list of evidence arrays with keys: id, userid, competencyid, action,
     *               actionuserid, description, grade, note, url, timecreated, timemodified
     */
    public static function listEvidence(int $userid, int $competencyid, int $skip = 0, int $limit = 0): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        try {
            $records = api::list_evidence($userid, $competencyid, 0, 'timecreated', 'DESC', $skip, $limit);

            return array_map(
                fn (object $ev): array => self::mapEvidence($ev),
                array_values($records)
            );
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Maps a Moodle competency framework persistent to a plain array.
     *
     * @param object $fw the framework persistent object
     *
     * @return array associative array with framework properties
     */
    private static function mapFramework(object $fw): array
    {
        return [
            'id' => (int) $fw->get('id'),
            'shortname' => (string) $fw->get('shortname'),
            'idnumber' => (string) $fw->get('idnumber'),
            'description' => (string) $fw->get('description'),
            'visible' => (bool) $fw->get('visible'),
            'scaleid' => (int) $fw->get('scaleid'),
            'timecreated' => (int) $fw->get('timecreated'),
            'timemodified' => (int) $fw->get('timemodified'),
        ];
    }

    /**
     * Maps a Moodle competency persistent to a plain array.
     *
     * @param object $c the competency persistent object
     *
     * @return array associative array with competency properties
     */
    private static function mapCompetency(object $c): array
    {
        return [
            'id' => (int) $c->get('id'),
            'shortname' => (string) $c->get('shortname'),
            'idnumber' => (string) $c->get('idnumber'),
            'description' => (string) $c->get('description'),
            'parentid' => (int) $c->get('parentid'),
            'path' => (string) $c->get('path'),
            'sortorder' => (int) $c->get('sortorder'),
            'competencyframeworkid' => (int) $c->get('competencyframeworkid'),
        ];
    }

    /**
     * Maps a Moodle user_competency persistent to a plain array.
     *
     * @param object $uc the user_competency persistent object
     *
     * @return array associative array with user competency properties
     */
    private static function mapUserCompetency(object $uc): array
    {
        return [
            'id' => (int) $uc->get('id'),
            'userid' => (int) $uc->get('userid'),
            'competencyid' => (int) $uc->get('competencyid'),
            'proficiency' => $uc->get('proficiency'),
            'grade' => $uc->get('grade'),
            'status' => (int) $uc->get('status'),
            'reviewerid' => (int) $uc->get('reviewerid'),
            'timecreated' => (int) $uc->get('timecreated'),
            'timemodified' => (int) $uc->get('timemodified'),
        ];
    }

    /**
     * Maps a Moodle evidence persistent to a plain array.
     *
     * @param object $ev the evidence persistent object
     *
     * @return array associative array with evidence properties
     */
    private static function mapEvidence(object $ev): array
    {
        return [
            'id' => (int) $ev->get('id'),
            'userid' => (int) $ev->get('userid'),
            'competencyid' => (int) $ev->get('competencyid'),
            'action' => (int) $ev->get('action'),
            'actionuserid' => (int) $ev->get('actionuserid'),
            'description' => (string) $ev->get('description'),
            'grade' => $ev->get('grade'),
            'note' => $ev->get('note'),
            'url' => $ev->get('url'),
            'timecreated' => (int) $ev->get('timecreated'),
            'timemodified' => (int) $ev->get('timemodified'),
        ];
    }
}
