<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Group;

use Middag\Framework\Shared\Dto\AbstractDto as abstract_dto;

/**
 * Cohort membership record.
 *
 * @api
 */
final class CohortMemberDto extends abstract_dto
{
    public function __construct(
        public int $cohortid = 0,
        public int $userid = 0,
        public int $timeadded = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'cohortid' => $this->cohortid,
            'userid' => $this->userid,
            'timeadded' => $this->timeadded,
        ];
    }
}
