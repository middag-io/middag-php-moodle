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

use core\cron;
use stdClass;

/**
 * Thin host adapter for Moodle cron process context.
 *
 * @api
 */
final class CronSupport
{
    public static function setupUser(?stdClass $user = null): void
    {
        cron::setup_user($user);
    }

    public static function prepareCoreRenderer(bool $restore = false): void
    {
        cron::prepare_core_renderer($restore);
    }

    public static function resetUserCache(): void
    {
        cron::reset_user_cache();
    }
}
