<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Enrolment;

use Middag\Moodle\Domain\Enrolment\Contract\EnrolmentServiceInterface as enrolment_service_interface;
use Middag\Moodle\Domain\Enrolment\EnrolmentDto as enrolment_dto;
use Middag\Moodle\Domain\Enrolment\EnrolmentStatus as enrolment_status;
use Middag\Moodle\Domain\Enrolment\UserEnrolment as user_enrolment;
use Middag\Moodle\Support\CourseSupport as course_support;
use Middag\Moodle\Support\DbSupport as db_support;
use Middag\Moodle\Support\EnrolSupport as enrol_support;
use stdClass;

/**
 * Enrolment service — typed enrolment operations composing multiple supports.
 *
 * Moodle-specific service: the vocabulary and return types are Moodle-native.
 * Extensions consume via facade or DI (never instantiate directly).
 *
 * @internal
 *
 * @see enrolment_service_interface
 */
class EnrolmentService implements enrolment_service_interface
{
    public function enrol(int $userid, int $courseid, int $roleid = 5): bool
    {
        return enrol_support::enrolUser($courseid, $userid, $roleid);
    }

    public function isEnrolled(int $userid, int $courseid): bool
    {
        return enrol_support::userIsEnrolled($courseid, $userid);
    }

    public function getEnrolment(int $userid, int $courseid): ?enrolment_dto
    {
        $ue = enrol_support::getEnrol($courseid, $userid);

        if (!$ue instanceof user_enrolment) {
            return null;
        }

        return $this->buildDtoFromUserEnrolment($ue->getId());
    }

    public function getUserEnrolments(int $userid): array
    {
        $records = db_support::getRecords('user_enrolments', ['userid' => $userid]);
        $result = [];

        foreach ($records as $record) {
            $enrol = db_support::getRecord('enrol', ['id' => $record->enrolid]);

            if ($enrol instanceof stdClass) {
                $dto = $this->buildDtoFromRecords($record, $enrol);

                $result[$enrol->courseid] = $dto;
            }
        }

        return $result;
    }

    public function getCourseEnrolments(int $courseid): array
    {
        $enrol_instances = db_support::getRecords('enrol', ['courseid' => $courseid]);
        $result = [];

        foreach ($enrol_instances as $enrol) {
            $user_enrolments = db_support::getRecords('user_enrolments', ['enrolid' => $enrol->id]);

            foreach ($user_enrolments as $ue) {
                $dto = $this->buildDtoFromRecords($ue, $enrol);

                $result[$ue->userid] = $dto;
            }
        }

        return $result;
    }

    public function suspend(int $userid, int $courseid): bool
    {
        return $this->updateStatus($userid, $courseid, enrolment_status::SUSPENDED);
    }

    public function reactivate(int $userid, int $courseid): bool
    {
        return $this->updateStatus($userid, $courseid, enrolment_status::ACTIVE);
    }

    public function countEnrolled(int $courseid, bool $activeonly = true): int
    {
        return course_support::getEnrolledUsersCount($courseid, $activeonly);
    }

    /**
     * Update the status of a user's enrolment.
     */
    private function updateStatus(int $userid, int $courseid, enrolment_status $status): bool
    {
        $ue = enrol_support::getEnrol($courseid, $userid);

        if (!$ue instanceof user_enrolment) {
            return false;
        }

        $record = db_support::getRecord('user_enrolments', ['id' => $ue->getId()]);

        if (!$record instanceof stdClass) {
            return false;
        }

        $record->status = $status->value;
        $record->timemodified = time();

        return db_support::updateRecord('user_enrolments', $record);
    }

    /**
     * Build DTO from a user_enrolment ID with full context.
     */
    private function buildDtoFromUserEnrolment(int $ue_id): ?enrolment_dto
    {
        $ue_record = db_support::getRecord('user_enrolments', ['id' => $ue_id]);
        $enrol_record = $ue_record instanceof stdClass ? db_support::getRecord('enrol', ['id' => $ue_record->enrolid]) : null;

        if (!$ue_record instanceof stdClass || !$enrol_record instanceof stdClass) {
            return null;
        }

        return $this->buildDtoFromRecords($ue_record, $enrol_record);
    }

    /**
     * Build DTO from raw user_enrolment + enrol records.
     */
    private function buildDtoFromRecords(object $ue, object $enrol): enrolment_dto
    {
        return new enrolment_dto(
            userid: (int) $ue->userid,
            courseid: (int) $enrol->courseid,
            enrolid: (int) $enrol->id,
            user_enrolment_id: (int) $ue->id,
            enrol_method: $enrol->enrol ?? 'manual',
            status: enrolment_status::resolve((int) $ue->status),
            roleid: (int) ($enrol->roleid ?? 5),
            timestart: (int) ($ue->timestart ?? 0),
            timeend: (int) ($ue->timeend ?? 0),
            timecreated: (int) ($ue->timecreated ?? 0),
            timemodified: (int) ($ue->timemodified ?? 0),
        );
    }
}
