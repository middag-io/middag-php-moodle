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

use Middag\Moodle\Domain\Enrolment\Contract\EnrolmentServiceInterface;
use Middag\Moodle\Domain\Enrolment\Enum\EnrolmentStatus;
use Middag\Moodle\Support\CourseSupport;
use Middag\Moodle\Support\DbSupport;
use Middag\Moodle\Support\EnrolSupport;
use stdClass;

/**
 * Enrolment service — typed enrolment operations composing multiple supports.
 *
 * Moodle-specific service: the vocabulary and return types are Moodle-native.
 * Extensions consume via facade or DI (never instantiate directly).
 *
 * @internal
 *
 * @see EnrolmentServiceInterface
 */
class EnrolmentService implements EnrolmentServiceInterface
{
    public function enrol(int $userid, int $courseid, int $roleid = 5): bool
    {
        return EnrolSupport::enrolUser($courseid, $userid, $roleid);
    }

    public function isEnrolled(int $userid, int $courseid): bool
    {
        return EnrolSupport::userIsEnrolled($courseid, $userid);
    }

    public function getEnrolment(int $userid, int $courseid): ?EnrolmentDto
    {
        $ue = EnrolSupport::getEnrol($courseid, $userid);

        if (!$ue instanceof UserEnrolment) {
            return null;
        }

        return $this->buildDtoFromUserEnrolment($ue->getId());
    }

    public function getUserEnrolments(int $userid): array
    {
        $records = DbSupport::getRecords('user_enrolments', ['userid' => $userid]);
        $result = [];

        foreach ($records as $record) {
            $enrol = DbSupport::getRecord('enrol', ['id' => $record->enrolid]);

            if ($enrol instanceof stdClass) {
                $dto = $this->buildDtoFromRecords($record, $enrol);

                $result[$enrol->courseid] = $dto;
            }
        }

        return $result;
    }

    public function getCourseEnrolments(int $courseid): array
    {
        $enrol_instances = DbSupport::getRecords('enrol', ['courseid' => $courseid]);
        $result = [];

        foreach ($enrol_instances as $enrol) {
            $user_enrolments = DbSupport::getRecords('user_enrolments', ['enrolid' => $enrol->id]);

            foreach ($user_enrolments as $ue) {
                $dto = $this->buildDtoFromRecords($ue, $enrol);

                $result[$ue->userid] = $dto;
            }
        }

        return $result;
    }

    public function suspend(int $userid, int $courseid): bool
    {
        return $this->updateStatus($userid, $courseid, EnrolmentStatus::Suspended);
    }

    public function reactivate(int $userid, int $courseid): bool
    {
        return $this->updateStatus($userid, $courseid, EnrolmentStatus::Active);
    }

    public function countEnrolled(int $courseid, bool $activeonly = true): int
    {
        return CourseSupport::getEnrolledUsersCount($courseid, $activeonly);
    }

    /**
     * Update the status of a user's enrolment.
     */
    private function updateStatus(int $userid, int $courseid, EnrolmentStatus $status): bool
    {
        $ue = EnrolSupport::getEnrol($courseid, $userid);

        if (!$ue instanceof UserEnrolment) {
            return false;
        }

        $record = DbSupport::getRecord('user_enrolments', ['id' => $ue->getId()]);

        if (!$record instanceof stdClass) {
            return false;
        }

        $record->status = $status->value;
        $record->timemodified = time();

        return DbSupport::updateRecord('user_enrolments', $record);
    }

    /**
     * Build DTO from a UserEnrolment ID with full context.
     */
    private function buildDtoFromUserEnrolment(int $ue_id): ?EnrolmentDto
    {
        $ue_record = DbSupport::getRecord('user_enrolments', ['id' => $ue_id]);
        $enrol_record = $ue_record instanceof stdClass ? DbSupport::getRecord('enrol', ['id' => $ue_record->enrolid]) : null;

        if (!$ue_record instanceof stdClass || !$enrol_record instanceof stdClass) {
            return null;
        }

        return $this->buildDtoFromRecords($ue_record, $enrol_record);
    }

    /**
     * Build DTO from raw UserEnrolment + enrol records.
     */
    private function buildDtoFromRecords(object $ue, object $enrol): EnrolmentDto
    {
        return new EnrolmentDto(
            userid: (int) $ue->userid,
            courseid: (int) $enrol->courseid,
            enrolid: (int) $enrol->id,
            user_enrolment_id: (int) $ue->id,
            enrol_method: $enrol->enrol ?? 'manual',
            status: EnrolmentStatus::resolve((int) $ue->status),
            roleid: (int) ($enrol->roleid ?? 5),
            timestart: (int) ($ue->timestart ?? 0),
            timeend: (int) ($ue->timeend ?? 0),
            timecreated: (int) ($ue->timecreated ?? 0),
            timemodified: (int) ($ue->timemodified ?? 0),
        );
    }
}
