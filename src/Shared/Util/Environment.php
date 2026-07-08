<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Shared\Util;

use Middag\Framework\Shared\Util\Environment as BaseEnvironment;

/**
 * Environment detection (Moodle-flavor).
 *
 * Extends the framework `Environment` to plug Moodle-native signals into
 * the resolution chain — `$CFG->middag_env` config + `DEBUG_DEVELOPER`
 * inference from `$CFG->debug`.
 *
 * Public host-environment accessor: plugins and composition-roots call the
 * inherited is{Development,Testing,Production}() to branch on the resolved
 * Moodle environment, so this is supported public surface.
 *
 * @api
 */
final class Environment extends BaseEnvironment
{
    /**
     * Read Moodle-native environment signals.
     *
     * Order: `$CFG->middag_env` config → `$CFG->debug === DEBUG_DEVELOPER`
     * inference → null (let framework resolution default to production).
     *
     * NOTE: Direct `$CFG` access is an accepted boundary exception —
     * environment detection runs during early bootstrap, before the
     * container exists.
     */
    protected static function detectHostEnvironment(): ?string
    {
        global $CFG;

        if (!empty($CFG->middag_env)) {
            return $CFG->middag_env;
        }

        if (isset($CFG->debug) && $CFG->debug === DEBUG_DEVELOPER) {
            return self::ENV_DEVELOPMENT;
        }

        return null;
    }
}
