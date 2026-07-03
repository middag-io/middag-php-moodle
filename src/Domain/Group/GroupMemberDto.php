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
 * Group membership record.
 *
 * @api
 */
final class GroupMemberDto extends abstract_dto
{
    public function __construct(
        public int $groupid = 0,
        public int $userid = 0,
        public int $timeadded = 0,
        public string $component = '',
        public int $itemid = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'groupid' => $this->groupid,
            'userid' => $this->userid,
            'timeadded' => $this->timeadded,
            'component' => $this->component,
            'itemid' => $this->itemid,
        ];
    }
}
