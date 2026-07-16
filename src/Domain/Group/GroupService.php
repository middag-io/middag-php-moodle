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

use core\exception\moodle_exception;
use Middag\Moodle\Domain\Group\Contract\GroupServiceInterface;
use Middag\Moodle\Shared\Util\Debug;
use Middag\Moodle\Support\GroupSupport;

/**
 * Group membership service — typed group operations composing GroupSupport.
 *
 * Moodle-specific service: the vocabulary and return types are Moodle-native.
 * Owns the ensure-membership orchestration that used to live in
 * GroupSupport::addUserInGroup; GroupSupport keeps only the primitives.
 * Extensions consume via facade or DI (never instantiate directly).
 *
 * @internal
 *
 * @see GroupServiceInterface
 */
class GroupService implements GroupServiceInterface
{
    public function addUserToGroup(int $courseid, int $userid, string $groupname): bool
    {
        try {
            $groupid = GroupSupport::getGroupByName($courseid, $groupname);
            if ($groupid === false || $groupid === 0) {
                $groupid = GroupSupport::createGroup($courseid, $groupname);
            }

            if (!is_int($groupid) || $groupid <= 0) {
                return false;
            }

            // Idempotent behavior: if already a member, consider success.
            if (GroupSupport::isMember($groupid, $userid)) {
                return true;
            }

            return GroupSupport::addMember($groupid, $userid);
        } catch (moodle_exception $moodleexception) {
            Debug::traceException($moodleexception);

            return false;
        }
    }
}
