<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Moodle function stub for Middag\Moodle\Kernel\MoodleMaintenanceGate coverage.
 *
 * The gate probes during_initial_install() (true while the site has no config
 * table yet — the very first install run). Moodle declares this in
 * lib/setuplib.php; moodle-stubs provide it for PHPStan only, so it is not
 * autoloadable at runtime. A behavioural stand-in driven by
 * $GLOBALS['__middag_test_during_initial_install'] (default false) lets the
 * gate's second probe be exercised without a Moodle runtime.
 *
 * Guarded with !function_exists so the file is order-independent, purely
 * additive, and collision-free with parallel writers.
 */

if (!function_exists('during_initial_install')) {
    function during_initial_install(): bool
    {
        return (bool) ($GLOBALS['__middag_test_during_initial_install'] ?? false);
    }
}
