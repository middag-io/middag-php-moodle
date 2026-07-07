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
 * Fixture controllers for RouteLoaderCoverageTest.
 *
 * RouteLoader scans a class via Reflection, reads PHP 8 #[Route] attributes off
 * its public methods, and — for external plugin controllers — derives a plugin
 * base entry point from the FQCN (ajax.php for AbstractApiController subclasses,
 * index.php otherwise). Exercising every branch therefore needs real classes in
 * distinct namespaces carrying real #[Route] attributes:
 *
 *   - local_example\*          — the configured ComponentContext component; its
 *                                routes keep the default entry point (_plugin_base
 *                                omitted).
 *   - local_yourplugin\*       — an external plugin: a plain controller resolves
 *                                to /local/yourplugin/index.php, an API controller
 *                                (extends AbstractApiController) to /local/yourplugin/ajax.php.
 *   - App\Controller\*         — a non-"local_" namespace: resolvePluginBase() falls
 *                                through to its terminal null.
 *
 * These are test fixtures (not Moodle symbol stand-ins), but they live in the
 * guarded per-area stub file so the coverage area keeps a single collision-free
 * home, matching the doctrine of the other area stubs. The area stubs are
 * required before the Composer autoloader in tests/bootstrap.php, so the file
 * pulls the autoloader in itself (idempotent require_once) to make the src/
 * AbstractApiController parent resolvable for the API-controller fixture. Each
 * class is guarded with class_exists() so the file stays additive and
 * order-independent.
 */

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// local_example (== ComponentContext::name()) controller — resolvePluginBase()
// returns null for this namespace, so no _plugin_base default is injected. Also
// carries a nameless route (addRoute() early-returns on it) and a non-route
// public method (empty getAttributes()).
if (!class_exists('local_example\RouteLoaderHomeController', false)) {
    eval('namespace local_example; class RouteLoaderHomeController {
        #[\Symfony\Component\Routing\Attribute\Route(path: "/home", name: "rl_home", methods: ["GET"])]
        public function index(): void {}

        #[\Symfony\Component\Routing\Attribute\Route(path: "/nameless")]
        public function nameless(): void {}

        public function helper(): void {}
    }');
}

// External plugin, plain (web/Inertia) controller — resolvePluginBase() → index.php.
// No methods on the attribute, so RouteLoader falls back to the ["GET","POST"] default.
if (!class_exists('local_yourplugin\RouteLoaderWebController', false)) {
    eval('namespace local_yourplugin; class RouteLoaderWebController {
        #[\Symfony\Component\Routing\Attribute\Route(path: "/dashboard", name: "lyp_dashboard")]
        public function dashboard(): void {}
    }');
}

// External plugin, API controller (subclass of AbstractApiController) — resolvePluginBase() → ajax.php.
if (!class_exists('local_yourplugin\RouteLoaderApiController', false)) {
    eval('namespace local_yourplugin; class RouteLoaderApiController extends \Middag\Moodle\Http\Controller\AbstractApiController {
        #[\Symfony\Component\Routing\Attribute\Route(path: "/api/ping", name: "lyp_ping", methods: ["GET", "POST"])]
        public function ping(): void {}
    }');
}

// Non-"local_" namespace — resolvePluginBase() falls through to its terminal null.
if (!class_exists('App\Controller\RouteLoaderExternalController', false)) {
    eval('namespace App\Controller; class RouteLoaderExternalController {
        #[\Symfony\Component\Routing\Attribute\Route(path: "/external", name: "app_external")]
        public function ext(): void {}
    }');
}
