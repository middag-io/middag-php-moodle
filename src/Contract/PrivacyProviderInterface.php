<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Contract;

use core\context;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

/**
 * Privacy provider contract for extensions with own storage.
 *
 * Extensions and plugins that store personal data outside the
 * framework's central persistence implement this interface to
 * participate in the Moodle Privacy API (LGPD/GDPR).
 *
 * The framework discovers implementations and delegates during
 * export and deletion flows.
 *
 * @api
 */
interface PrivacyProviderInterface
{
    /**
     * Add contexts where the user has data managed by this provider.
     */
    public function addContextsForUserid(int $userid, contextlist $contextlist): void;

    /**
     * Export personal data for the approved contexts.
     */
    public function exportUserData(approved_contextlist $contextlist): void;

    /**
     * Delete all personal data in the given context.
     */
    public function deleteDataForAllUsersInContext(context $context): void;

    /**
     * Delete personal data for a specific user in the approved contexts.
     */
    public function deleteDataForUser(approved_contextlist $contextlist): void;
}
