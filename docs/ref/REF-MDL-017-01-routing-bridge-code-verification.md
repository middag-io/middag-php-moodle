---
ref: REF-MDL-017-01
adr: MDL-017
title: 'Code-Verified Maturity Split: MoodleRouter, RouterBridgeSupport & MiddagProxy'
lang: en
---

# REF-MDL-017-01: Code-Verified Maturity Split — `MoodleRouter`, `RouterBridgeSupport` & `MiddagProxy`

> Detail supporting [MDL-017](../decisions/MDL-017-routing-bridge-coexistence.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-206`, no REF companion), then verified line-by-line against this repository's real code (`develop` branch, 2026-07-17) — this REF exists specifically to carry that verification. **Updated same day:** `RouterBridgeSupport::proxyRequest()`'s stub status (Component 2, third bullet below) was fixed as part of this verification pass, not just documented — see the Conclusion section for the corrected split.

## Component 1 — the framework's own router (real, active, not part of the "stub" question at all)

`src/Http/Routing/MoodleRouter.php` (`Middag\Moodle\Http\Routing\MoodleRouter`, implements `Middag\Moodle\Http\Contract\RouterInterface`) is a genuine Symfony Routing–based router: it holds a `RouteCollection`, resolves the request context from globals, registers a default `route_not_found` route plus global regex requirements, and generates URLs through `PluginAwareUrlGenerator`. It derives its entry point from the running host component via `ComponentContext::baseUrlPath()` (e.g. `/local/middag/index.php`), so the adapter never hardcodes a product. `src/Http/Routing/RouteLoader.php` (implements the framework's `RouteLoaderInterface`) reflects controller classes for `#[Route]` attributes and builds the `RouteCollection`; it also detects external-plugin controllers by namespace and injects `_plugin_base` (`ajax.php` for `AbstractApiController` subclasses, `index.php` otherwise) so `PluginAwareUrlGenerator` produces correct cross-plugin URLs. **This pair is not the Moodle-native-router bridge described by the ADR at all** — it is the framework's own primary routing mechanism, adapted to the Moodle host, and it was never a stub. Confusing it with the ADR-206 "coexistence" concept (because of the `MoodleRouter` name) would be a misreading.

## Component 2 — the actual Moodle-native-router bridge: `RouterBridgeSupport` (mixed maturity, verified method by method)

`src/Support/RouterBridgeSupport.php` (`@internal`, final class) is the real bridge the ADR calls "coexistence with the native Moodle router." Verified per method:

- `isAvailable(): bool` — real and active. Checks `VersionSupport::supports('moodle_router', FEATURE_MATRIX)` (gated `since => '5.1'`) plus `VersionSupport::symbolExists('core\router\route_loader_interface')`. No stub language in its docblock.
- `register(): void` — real, and correctly a no-op from this adapter's side: the docblock explains Moodle 5.1+ auto-discovers routes from the **consumer plugin's** `route\api\*`/`route\controller\*` namespaces, so there is nothing for this OSS adapter itself to register programmatically. This is accurate behavior, not an unfinished implementation.
- `getOpenapiJsonUrl(): string` / `getOpenapiYamlUrl(): string` — real and active, both build a URL against `$CFG->wwwroot` and `ComponentContext::baseUrlPath()`. No availability guard inside these two methods themselves (the guard is `isAvailable()`, called by the consumer before relying on native-router-specific URLs).
- `proxyRequest(object $request, object $response, string $path = ''): object` — **fixed 2026-07-17, no longer a stub.** The docblock previously blamed a pending ADR-208 migration ("`http_kernel::handle()` calls `Response::send()` internally"), but that migration had already shipped: `middag-php-framework`'s `HttpKernel::handle(): ResponseInterface` returns a clean PSR-7 response and never calls `send()`. The stale part was that `proxyRequest()` called `Middag\Moodle\Runtime\Kernel::handle()` — a *different*, void-returning wrapper that emits the response (echoes headers/body) for normal Moodle page loads — and then tried to recover the emitted output via `ob_start()`/`ob_get_clean()`. The fix adds `Kernel::handleReturning(): ResponseInterface` (same dispatch path as `handle()`, minus the emit step) and rewires `proxyRequest()` to call it directly, copying status/headers/body onto the Slim-supplied response object. No output buffering, no header-conflict risk. Covered by the existing `RouterBridgeSupportCoverageTest` (updated to assert the deterministic 404 status and to send a realistic `Accept: application/json` header, since the framework kernel's JSON-vs-HTML error rendering depends on it) — full suite (3140 + 8 tests) and `composer check` (style/rector/stan) pass clean.

## Component 3 — `MiddagProxy` (fully inert placeholder, confirmed)

`src/Http/Routing/MiddagProxy.php` is a 37-line file whose entire body is `final class MiddagProxy {}`. Its docblock is explicit: *"Placeholder for the Moodle 5.1+ Symfony HttpKernel bridge (ADR-208)"*, reserving the namespace path that mirrors where the eventual consumer-plugin route class will live (`local_example/classes/route/api/middag_proxy.php`, since Moodle scans a plugin's own `classes/route/api/`, not this package's Composer namespace, directly). The docblock states plainly: *"Until then this class is intentionally inert — no traits, no routes, no DI. Instantiating it under Moodle 5.0.0 is a no-op and Moodle's router does not see it."*

## Conclusion — the precise, code-verified classification

Do not describe this ADR as "a stub" wholesale — that was the pre-existing misreading this pass corrects. As of the 2026-07-17 fix, only one genuine gap remains, and it is an external dependency, not unfinished MIDDAG work:

1. **Framework's own primary router** (`MoodleRouter`/`RouteLoader`) — real, active, always in production, unrelated to the stub question.
2. **The entire native-router bridge** (`RouterBridgeSupport`: `isAvailable()`, `register()`, `getOpenapiJsonUrl()`, `getOpenapiYamlUrl()`, and now `proxyRequest()`) — real, active, production-quality on Moodle >= 5.1.
3. **`MiddagProxy`** — still an intentional no-op, and correctly so: it exists to be scanned by Moodle 5.1's native router via a trait (`core\router\route_controller`) that does not exist yet upstream. Confirmed absent from the Moodle 5.0.7 checkout at `/private/var/www/docker-moodle-helico/moodle` (no `core/router/` directory at all). This is the one real remaining gap in ADR-206, and it is blocked on Moodle core shipping a feature, not on any MIDDAG-side implementation work.

## Version-guard cross-reference

Ties to the Routing row of the API coverage registry ([MDL-005](../decisions/MDL-005-api-coverage-registry.md), REF-MDL-005-01): `min_moodle: 5.1`, guard `class_exists('core\router\route_loader_interface')` via `router_bridge_support::is_available()`. That guard governs components 2 and 3 above; it does not gate component 1, which has no Moodle-version dependency of its own.
