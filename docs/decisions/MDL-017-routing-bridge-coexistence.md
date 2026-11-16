---
title: "The framework router is primary; Moodle's native router only gets proxies"
status: accepted
date: 2026-04-08
last-updated: 2026-11-16
lang: en
domains: [routing]
deciders: [leadership]
decision: "The framework router serves every controller; on Moodle 5.1+ native routes only forward to the same kernel."
related: [MDL-001, MDL-005, MDL-006, middag-php-framework:FW-002]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-017: The framework router is primary; Moodle's native router only gets proxies

## Context

Moodle ships a native router (`r.php`, `core\router`) with friendly URLs and its own OpenAPI listing; a Moodle 5.0 build already contains it. The adapter gates its bridge to that router at Moodle 5.1. The framework already routes every controller through its own kernel, with attribute routes, authorization and middleware that work the same on every host. The question is how the two routers coexist.

## Considered Options

1. Ignore Moodle's native router.
2. Replace the framework router with Moodle's native router on Moodle.
3. The framework router stays primary for every controller; on Moodle 5.1+ native routes are thin proxies that forward to the same kernel. ← chosen

## Decision

Every controller is routed by the framework router through the adapter's HTTP kernel, on every Moodle version. On Moodle 5.1 and later, routes registered with Moodle's native router are thin proxies: they forward the request to the same kernel and relay its response, so the controller, its authorization and its middleware are the same whichever router the request came through. Below Moodle 5.1 the bridge does nothing. A consumer that specifically needs Moodle's own middleware may write native routes itself and reach its services through the DI bridge (MDL-006).

## Consequences

- (+) Controllers are written once and behave the same on every Moodle version.
- (+) On Moodle 5.1+ the consumer's API can appear in Moodle's native route and OpenAPI listings.
- (−) A request through the native router passes through two routing layers.
- (−) The native proxy class `MiddagProxy` is still inert. The adapter has not adopted `core\router\route_controller`, which Moodle core already ships, and the 5.1 gate may be stale.
- (+) Forwarding carries the whole request (method, query, headers, cookies, body, uploaded files, attributes) and relays the whole response (status, every header value, body). Round-trip tests drive real PSR-7 objects through the real kernel.
- (−) Moodle's router validates the request before the proxy runs. It drops undeclared query params and empties an undeclared body; the proxy rebuilds the query from the URI and re-parses a url-encoded body (multipart POST falls back to `$_POST`/`$_FILES`). It also decodes the path; the proxy forwards the raw path so the framework decodes it once.
- (−) On `r.php`, Moodle core intercepts `Authorization: Bearer`, so token clients must call the plugin's own endpoint. Moodle also sets `Access-Control-Allow-Origin: *` and the native route has no OPTIONS method, so a CORS preflight never reaches the framework and its CORS rules do not apply there.
- (−) Forwarding is not yet tested against a real Moodle 5.1 router.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| The bridge is off below 5.1 | test `tests/Support/RouterBridgeSupportCoverageTest.php` | coded |
| Forwarding keeps the full request and the full response | test `tests/Support/RouterBridgeSupportProxyTest.php` | coded |
| Forwarding verified against a real Moodle 5.1 router | integration test | planned |
