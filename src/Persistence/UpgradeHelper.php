<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Persistence;

use Middag\Moodle\Kernel\Config\ComponentContext;
use moodle_database;
use Throwable;

/**
 * Idempotent upgrade step gate and data-migration utilities.
 *
 * Methods are static because they are called in procedural upgrade.php contexts
 * before the container is fully initialized.
 *
 * @api
 */
class UpgradeHelper
{
    private const STEP_PREFIX = 'upgrade_step_';

    /**
     * Return true when the named step has already been executed.
     */
    public static function stepCompleted(string $step): bool
    {
        return (bool) get_config(self::component(), self::STEP_PREFIX . $step);
    }

    /**
     * Mark a step as done so it is skipped on subsequent upgrade runs.
     */
    public static function stepComplete(string $step): void
    {
        set_config(self::STEP_PREFIX . $step, time(), self::component());
    }

    /**
     * Bulk-rename item types in the given table.
     *
     * Generic executor: the consumer supplies the target table (e.g. its items
     * table), so the adapter holds no product schema reference.
     *
     * @param moodle_database       $DB       active database connection
     * @param array<string, string> $type_map old_type → new_type
     * @param string                $table    target table whose `type` column is renamed
     *
     * @return array{migrated: int, errors: list<string>}
     */
    public static function normalizeExtensionTypes(moodle_database $DB, array $type_map, string $table): array
    {
        $migrated = 0;
        $errors = [];

        foreach ($type_map as $old_type => $new_type) {
            try {
                $affected = $DB->count_records($table, ['type' => $old_type]);

                if ($affected > 0) {
                    $DB->set_field($table, 'type', $new_type, ['type' => $old_type]);
                    $migrated += $affected;
                }
            } catch (Throwable $e) {
                $errors[] = sprintf('Type rename %s→%s failed: %s', $old_type, $new_type, $e->getMessage());
            }
        }

        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Component used to persist upgrade-step markers.
     *
     * Resolved from the composition-root {@see ComponentContext} seam; the
     * consumer plugin must configure it before its upgrade.php runs.
     */
    private static function component(): string
    {
        return ComponentContext::name();
    }
}
