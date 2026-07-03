<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Http\Routing;

/**
 * Placeholder for the Moodle 5.1+ Symfony HttpKernel bridge (ADR-208).
 *
 * Reserves the namespace path mirroring the eventual plugin location
 * `local_example/classes/route/api/middag_proxy.php`, where Moodle 5.1+
 * scans for `#[route]`-annotated controllers and dispatches them through
 * `core\router`.
 *
 * Activation plan (post Moodle 5.1 GA):
 *   - Adopt `use \core\router\route_controller;` once the trait ships in core.
 *   - Declare `#[route(path: ..., method: [...])]` methods delegating into
 *     `Middag\Moodle\Http\Routing\Router` (existing internal router).
 *   - Wire registration via the plugin shim under
 *     `local_example/classes/route/api/middag_proxy.php` (Moodle scans plugin
 *     `classes/route/api/`, not Composer namespaces directly).
 *
 * Until then this class is intentionally inert — no traits, no routes,
 * no DI. Instantiating it under Moodle 5.0.0 is a no-op and Moodle's
 * router does not see it.
 *
 * @internal placeholder for ADR-208; not part of the public API surface
 */
final class MiddagProxy {}
