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

        // Dynamic constant lookup: the moodle-stubs 4.5 series types the
        // DEBUG_DEVELOPER literal in a way PHPStan can prove always-false
        // against $CFG->debug; constant() keeps the comparison opaque to the
        // analyser and the defined() guard keeps no-host runs safe.
        // DEBUG_DEVELOPER is defined as E_ALL on every supported Moodle branch
        // (4.5–5.2), so E_ALL is the correct no-host fallback — it tracks the
        // running PHP (e.g. 30719 on PHP 8.4, 32767 before) instead of a stale
        // magic number.
        $developerLevel = \defined('DEBUG_DEVELOPER') ? (int) \constant('DEBUG_DEVELOPER') : E_ALL;

        if (isset($CFG->debug) && (int) $CFG->debug === $developerLevel) {
            return self::ENV_DEVELOPMENT;
        }

        return null;
    }
}
