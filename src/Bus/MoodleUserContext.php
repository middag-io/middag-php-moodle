<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Bus;

use Middag\Framework\Bus\Contract\UserContextResolverInterface;

/**
 * Moodle user context resolver.
 *
 * Resolves the current user ID from the Moodle global $USER.
 * Returns null when running in CLI/cron without an authenticated user.
 */
final class MoodleUserContext implements UserContextResolverInterface
{
    public function getCurrentUserId(): ?int
    {
        global $USER;

        if (!isset($USER->id) || (int) $USER->id === 0) {
            return null;
        }

        return (int) $USER->id;
    }
}
