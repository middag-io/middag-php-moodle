---
id: MDL-017
title: 'Routing Bridge: Framework Router Stays Primary, Native Moodle Router Gets Thin Proxies'
status: accepted
date: 2026-04-08
lang: en
domains: [moodle, http-routing]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-001, MDL-005, MDL-006]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/routing-bridge-maturity]
decision: 'A dual-path routing model: the framework''s own router remains primary for every controller, while Moodle''s native router (5.1+) receives thin, auto-discovered proxy routes converging on the same kernel; proxy request-forwarding is production-quality since the 2026-07-17 fix, with only MiddagProxy blocked on an upstream Moodle trait that does not exist yet.'
---

# MDL-017: Routing Bridge — Framework Router Stays Primary, Native Moodle Router Gets Thin Proxies

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-206`, decided 2026-04-08). This is an archaeology pass, not a new decision — dates and rationale are historical. **Classification correction (2026-07-17):** the original knowledge-consolidation pass had inherited an earlier reading that treated this whole ADR as an inactive "proxy stub" blocked by ADR-208. Reading the real code in this repository (`develop` branch) during this pass showed that framing understated what already exists — see the Enforcement table below for the code-verified maturity split. **Fix landed same day (2026-07-17):** `RouterBridgeSupport::proxyRequest()` no longer blames ADR-208 — the framework's `HttpKernel::handle()` already returns a `ResponseInterface` (that migration shipped), the stub just called the wrong entry point. It now calls the new `Kernel::handleReturning()` and passes the real response through; the output-buffering workaround is gone.

## Context

Moodle 5.1 introduces its own native router (Slim4 + FastRoute, entry point `/r.php`). The framework already has its own routing system serving MIDDAG's controllers through `index.php`/`ajax.php`.

## Considered Options

1. **Ignore Moodle's native router entirely** — rejected: forgoes friendly URLs through `r.php` and visibility in Moodle's native OpenAPI/Swagger UI.
2. **Replace the framework's own router with Moodle's native router** — rejected: gives up a routing system the framework has independent reasons to keep (host-agnostic controllers, its own middleware pipeline).
3. **Dual-path bridge: framework router stays primary, Moodle's native router (5.1+) gets thin, auto-discovered proxy routes converging on the same kernel** ← chosen.

## Decision

A dual-path model: the framework's own router remains the **primary** system for every MIDDAG controller. Moodle's native router (5.1+) optionally receives thin proxy routes that delegate to the same framework kernel. Discovery is automatic via a namespace convention (`local_middag\route\api\*`/`route\controller\*` in the consumer plugin) — the framework generates thin proxies in that namespace; both paths converge on the same kernel. Availability is version-gated: `router_bridge_support::is_available()` returns `false` below Moodle 5.1 (native-router proxy unavailable; routing stays exclusively via the framework's own entry points), `true` at 5.1+ (native-router integration becomes reachable). Extensions may optionally write Moodle-native routes themselves, outside the framework kernel, when they specifically need Moodle middleware (CORS, `wstoken`), exclusive visibility in the Mobile App, or presence in the native OpenAPI spec — consuming MIDDAG services via the DI bridge ([MDL-006](./MDL-006-di-bridge-container-interoperability.md)). **What does not change**: the framework's own routing stays intact; `index.php`, `webhook.php`, `ajax.php` remain the primary entry points; existing controllers need no changes; the framework's container continues resolving controllers; Moodle's middleware pipeline is fixed and out of this decision's control.

## Consequences

- MIDDAG's controllers keep working through the framework's own router regardless of Moodle version — nothing about this decision is a prerequisite for existing functionality.
- On Moodle >= 5.1, MIDDAG's OpenAPI spec is discoverable through Moodle's native Swagger UI, and route availability can be queried programmatically — real capability available today.
- The request-forwarding path (`RouterBridgeSupport::proxyRequest()`) is production-quality as of the 2026-07-17 fix: it dispatches through the framework's `Kernel::handleReturning()` and relays the real `ResponseInterface` (status, headers, body) onto Moodle's Slim response object — no output-buffering hack, no header-conflict risk. See `framework/reference/adapters/moodle/routing-bridge-maturity` (in `docs-middag-dev`) for the full component-by-component verification.
- The "friendly URL via `r.php`" outcome still has one genuine external dependency: `MiddagProxy`, the class Moodle's native router would scan for, stays an intentional no-op until Moodle itself ships `core\router\route_controller` (confirmed absent from the Moodle 5.0.7 checkout audited during this reconstruction). That is an upstream Moodle dependency, not something this repository controls — it activates automatically once the trait exists.
- Extensions needing Moodle-native middleware today write their own native routes directly against the DI bridge ([MDL-006](./MDL-006-di-bridge-container-interoperability.md)) rather than waiting on `MiddagProxy`.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Framework's own router (`MoodleRouter`/`RouteLoader`) stays primary, unrelated to native-router bridge maturity | doc `framework/reference/adapters/moodle/routing-bridge-maturity` | **coded** |
| `RouterBridgeSupport` (`isAvailable`, `register`, `getOpenapiJsonUrl`, `getOpenapiYamlUrl`, `proxyRequest`) is production-quality on Moodle >= 5.1 | `RouterBridgeSupportCoverageTest` + full suite/`composer check` | **coded** |
| Availability gated below Moodle 5.1 (`router_bridge_support::is_available()` returns `false`) | version-guard row in [MDL-005](./MDL-005-api-coverage-registry.md) coverage registry | **coded** |
| `MiddagProxy` activates automatically once Moodle ships `core\router\route_controller` | no automated check — blocked on upstream Moodle; re-verify manually against each Moodle core bump | **planned** |
