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
 * Moodle function stubs for the Http\Controller\AbstractController coverage
 * area. Guarded with !function_exists so the file is order-independent and
 * purely additive (mirrors tests/stubs/support/*). Every other Moodle symbol
 * the controller touches ($PAGE/$OUTPUT doubles, core\context\*, moodle_url,
 * get_string, admin_externalpage_setup, admin_get_root, …) is already provided
 * by tests/bootstrap.php or the support stubs; only Moodle's s() escaper is
 * missing, and AbstractController::errorPage() calls it on the debug_output
 * branch.
 */

// Stub: s() — Moodle's HTML-entity escaper (lib/weblib.php). errorPage() wraps
// the request's debug_output in <pre>s($debug_output)</pre>.
if (!function_exists('s')) {
    function s($var): string
    {
        return htmlspecialchars((string) $var, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
