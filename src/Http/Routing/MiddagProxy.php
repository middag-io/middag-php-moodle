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
 * Placeholder for the Moodle native-router proxy (MDL-017).
 *
 * Reserves the namespace of the proxy that would forward a Moodle native
 * route to the framework kernel. MDL-017 records the routing decision, the
 * proxy's current state and what adopting it requires; this class does not
 * restate it.
 *
 * Intentionally inert: no traits, no routes, no DI. Moodle's router does not
 * see it, and instantiating it is a no-op on every Moodle version.
 *
 * @internal placeholder for MDL-017; not part of the public API surface
 */
final class MiddagProxy {}
