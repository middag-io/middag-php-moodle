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

/**
 * Backup/restore steps provider for extensions with own storage.
 *
 * Extensions and plugins that manage data outside the framework's
 * central persistence implement this interface to contribute
 * backup and restore steps to the Moodle .mbz flow.
 *
 * The framework discovers implementations and aggregates their
 * steps into the official backup/restore pipeline.
 *
 * @api
 */
interface BackupStepsProviderInterface
{
    /**
     * Return backup steps for this provider's data.
     *
     * @return array list of backup_step instances
     */
    public function getBackupSteps(): array;

    /**
     * Return restore steps for this provider's data.
     *
     * @return array list of restore_step instances
     */
    public function getRestoreSteps(): array;
}
