---
id: MDL-017
title: 'Routing Bridge: Framework Router Stays Primary, Native Moodle Router Gets Thin Proxies'
status: accepted
date: 2026-04-08
domains: [moodle, http-routing]
related: [MDL-001, MDL-005, MDL-006]
supersedes: []
superseded_by: null
lang: en
---

# MDL-017: Routing Bridge — Framework Router Stays Primary, Native Moodle Router Gets Thin Proxies

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-206`, decided 2026-04-08). This is an archaeology pass, not a new decision — dates and rationale are historical. **Classification correction (2026-07-17):** the original knowledge-consolidation pass had inherited an earlier reading that treated this whole ADR as an inactive "proxy stub" blocked by ADR-208. Reading the real code in this repository (`develop` branch) during this pass shows that framing understates what already exists — see REF-MDL-017-01 for the precise, code-verified maturity split this ADR now records. **Fix landed same day (2026-07-17):** `RouterBridgeSupport::proxyRequest()` no longer blames ADR-208 — the framework's `HttpKernel::handle()` already returns a `ResponseInterface` (that migration shipped), the stub just called the wrong entry point. It now calls the new `Kernel::handleReturning()` and passes the real response through; the output-buffering workaround is gone. See REF-MDL-017-01 for the updated maturity split.

## Context

Moodle 5.1 introduces its own native router (Slim4 + FastRoute, entry point `/r.php`). The framework already has its own routing system serving MIDDAG's controllers through `index.php`/`ajax.php`. Ignoring Moodle's native router entirely would forgo friendly URLs through `r.php` and visibility in Moodle's native OpenAPI/Swagger UI; replacing the framework's own router with Moodle's would mean giving up a routing system this framework has other reasons to keep (host-agnostic controllers, its own middleware pipeline).

## Decision

A dual-path model: the framework's own router remains the **primary** system for every MIDDAG controller. Moodle's native router (5.1+) optionally receives thin proxy routes that delegate to the same framework kernel. Discovery is automatic via a namespace convention (`local_middag\route\api\*`/`route\controller\*` in the consumer plugin) — the framework generates thin proxies in that namespace; both paths converge on the same kernel. Availability is version-gated: `router_bridge_support::is_available()` returns `false` below Moodle 5.1 (native-router proxy unavailable; routing stays exclusively via the framework's own entry points), `true` at 5.1+ (native-router integration becomes reachable). Extensions may optionally write Moodle-native routes themselves, outside the framework kernel, when they specifically need Moodle middleware (CORS, `wstoken`), exclusive visibility in the Mobile App, or presence in the native OpenAPI spec — consuming MIDDAG services via the DI bridge ([MDL-006](./MDL-006-di-bridge-container-interoperability.md)). **What does not change**: the framework's own routing stays intact; `index.php`, `webhook.php`, `ajax.php` remain the primary entry points; existing controllers need no changes; the framework's container continues resolving controllers; Moodle's middleware pipeline is fixed and out of this decision's control.

**Code-verified maturity split (updated 2026-07-17, see REF-MDL-017-01 for detail):** the framework's own router (`MoodleRouter`/`RouteLoader`, Symfony Routing–based) is fully real and active today — it was never in question and is unrelated to the "stub" framing. Within the actual native-router bridge (`RouterBridgeSupport`), availability detection, OpenAPI-URL exposure, **and now request-forwarding** are real and active — `proxyRequest()` was fixed same-day to call the framework's PSR-15 kernel directly instead of the abandoned output-buffering workaround. The one piece that remains a genuine, externally-blocked placeholder is `MiddagProxy`: it stays inert until Moodle itself ships the 5.1 native-router trait (`core\router\route_controller`) it needs to register against — confirmed absent from the Moodle 5.0.7 checkout checked in this pass.

## Consequences

- MIDDAG's controllers keep working through the framework's own router regardless of Moodle version — nothing about this decision is a prerequisite for existing functionality.
- On Moodle >= 5.1, MIDDAG's OpenAPI spec is already discoverable through Moodle's native Swagger UI, and route availability can already be queried programmatically — real capability available today.
- The request-forwarding path (`RouterBridgeSupport::proxyRequest()`) is now production-quality: it dispatches through the framework's `Kernel::handleReturning()` and relays the real `ResponseInterface` (status, headers, body) onto Moodle's Slim response object, with no output-buffering hack and no header-conflict risk.
- The actual "friendly URL via `r.php`" outcome still has one genuine external dependency left: `MiddagProxy`, the class Moodle's native router would scan for, stays an intentional no-op until Moodle 5.1 ships `core\router\route_controller`. That is an upstream Moodle dependency, not something this repository controls.
- Extensions needing Moodle-native middleware today write their own native routes directly against the DI bridge rather than waiting on `MiddagProxy`.

## Out of scope

- Moodle 5.1 itself shipping `core\router\route_controller` — an upstream dependency this repository does not control; `MiddagProxy` activates automatically once it exists.
- The DI bridge that extensions use to reach MIDDAG services from Moodle-native routes — see [MDL-006](./MDL-006-di-bridge-container-interoperability.md).
- File-line-level code confirmation of the maturity split — see REF-MDL-017-01.

## Links

- [REF-MDL-017-01 — Code-Verified Maturity Split: MoodleRouter, RouterBridgeSupport & MiddagProxy](../ref/REF-MDL-017-01-routing-bridge-code-verification.md)
- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [MDL-005 — API Coverage Registry / Tier Model](./MDL-005-api-coverage-registry.md)
- [MDL-006 — DI Bridge / Container Interoperability](./MDL-006-di-bridge-container-interoperability.md)
