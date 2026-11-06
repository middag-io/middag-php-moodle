---
title: 'CSRF: sesskey verification on by default, verified in preHandle()'
status: accepted
date: 2026-11-05
last-updated: 2026-11-05
lang: en
domains: [moodle, http, security]
deciders: [leadership]
decision: 'Non-idempotent requests riding a Moodle session must carry a valid sesskey. The check runs in preHandle() before the action, accepts the token from a header, the payload or the query, and is waived only for public routes, wstoken-authenticated requests and routes marked #[Sesskey(require: false)].'
related: [MDL-017, MDL-019]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-022: CSRF default-on in the controller base classes

## Context

`AbstractController` and `AbstractApiController` shipped sesskey verification as
opt-in (`protected bool $requireSesskey = false`), read from the `#[Sesskey]`
attribute. No consumer opted in: `moodle-local_middag` has 86 session-authenticated
mutating routes and zero occurrences of `#[Sesskey]`.

Four mechanics made "just flip the flag" insufficient:

1. **The check never ran on the routes it was meant to protect.** It lived in
   `requireLogin()`, which `handle()` calls — and `handle()` only runs from
   `render()`/`inertia()`. `inertiaRedirect()`, the return of every Inertia
   mutation, does not call it. The kernel, by contrast, always calls
   `preHandle()` before the action.
2. **The API wiring was unreachable.** `authenticateApiRequest()` returned early
   for any logged-in session, above the `setRequireSesskey()` call. Only a
   not-logged-in request reached it, and that one fails on login anyway.
3. **The exemption used the wrong signal.** `isWsRequest()` reports that an
   `Authorization` or `wstoken` value is *present*, not that the request
   authenticated with one. A session request carrying any junk Bearer header
   exempted itself — which is exactly the shape of every HÉLICO admin SPA call.
4. **The transport did not match the clients.** Moodle's `require_sesskey()`
   reads `required_param('sesskey')`, i.e. `$_GET`/`$_POST` only (identical in
   4.5 through 5.3). A token sent as a header or inside a JSON body was invisible
   to it, and a page controller's failure surfaced as a raw `moodle_exception`,
   which the kernel maps to 500 rather than 403.

## Decision

- `$requireSesskey` defaults to **true**.
- Verification moves to `preHandle()` (`verifyCsrf()`), before the action.
- The token is read, in order, from `X-CSRF-Token`, `X-XSRF-TOKEN`, the
  `sesskey` payload/query field (Moodle's own transport, posted by formslib)
  and `_token` (the framework's field name).
- Comparison goes through `AuthenticationInterface::confirmSesskey()`, which
  compares in constant time against the session's sesskey. `require_sesskey()`
  stays for callers that want Moodle's parameter-only behaviour.
- Failure throws `MiddagAuthorizationException` → **403**.
- The token is published two ways: the `csrf_token` Inertia shared prop (the
  name the `@middag-io/react` contract already declares) and a JS-readable
  `XSRF-TOKEN` cookie (`SameSite=Lax`, not httpOnly) set by `MoodleHttpKernel`.
  The axios instance behind the Inertia client echoes that cookie back as
  `X-XSRF-TOKEN`, so Inertia visits satisfy the gate with no client change.

### Exemptions

| Exemption | Signal | Why |
|---|---|---|
| Public routes | `requiresAuthentication() === false` | No session; the route's own HMAC/shared secret is the gate. This is the hook that works — `#[Auth(login: false)]` alone only reaches controllers implementing `PublicRouteAwareInterface`. |
| Web-service calls | flag set inside `authenticateViaWstoken()` | No session cookie, so nothing to ride. Deliberately **not** `isWsRequest()`. |
| Opt-out | `#[Sesskey(require: false)]` | Escape hatch for a route that must stay open, declared at the route. |
| No session | `isLoggedIn() === false` | A caller with no session cookie cannot be forged into one. |

## Considered Options

1. **Keep opt-in and annotate all 86 plugin routes** — rejected: a new route is
   unprotected by default, which is the failure mode that produced this issue.
2. **Verify in `handle()` with the flag flipped** — rejected: `handle()` never
   runs on the mutating page routes (see Context 1), so the gate would be a no-op
   exactly where it matters.
3. **Register the framework's `VerifyCsrfMiddleware`** — rejected: it mints its
   own token through `CsrfTokenManager`, while the Moodle host already has one
   (`sesskey`) that formslib, `M.cfg.sesskey` and every core URL already carry.
   Two tokens on one page is worse than one.
4. **Answer 419 like the framework middleware** — rejected for the Moodle host:
   403 is what the rest of this adapter returns for an authorization failure, and
   what the consumer UIs already handle. The divergence is deliberate.

## Consequences

- Every session-authenticated `POST`/`PUT`/`PATCH`/`DELETE` reaching these base
  classes now needs a token. Consumers that send none get 403 until their client
  sends one — `moodle-local_middag` (plain `fetch` call sites) and
  `moodle-local_helico` (two axios instances) both need an interceptor.
- The sesskey now also travels in a JS-readable cookie. It is not a new secret:
  Moodle already exposes the same value in page URLs, form fields and
  `M.cfg.sesskey`.
- `Http/Concerns/InteractsWithAuth` (an unused legacy mixin, kept for BC) stays
  opt-in. The canonical path is the controller base classes.
