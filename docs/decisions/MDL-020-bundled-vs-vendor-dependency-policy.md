---
id: MDL-020
title: 'Bundled-vs-Vendor Dependency Policy: Global Namespace Collisions with Moodle-Bundled Third-Party Libraries'
status: accepted
date: 2026-07-27
lang: en
domains: [moodle, dependencies]
deciders: ['Michael Meneses']
related: [MDL-019]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
decision: 'This adapter always references its own composer-declared dependencies by their normal namespace; a package this adapter requires that Moodle core ALSO bundles globally (registered in `core_component::$psr4namespaces`, e.g. `firebase/php-jwt`, the `psr/*` family, GuzzleHttp) is a documented "shadow risk" — PHP''s autoloader resolves to whichever registration wins the race, which is host/bootstrap-order-dependent and NOT guaranteed to be this adapter''s own vendor/ copy. Any dependency on this adapter''s shared-namespace list gets its major-version compatibility checked against Moodle''s current bundled copy before a composer.json bump, and an unused shared-namespace dependency is removed rather than left as a dormant collision risk.'
---

# MDL-020: Bundled-vs-Vendor Dependency Policy — Global Namespace Collisions with Moodle-Bundled Third-Party Libraries

## Context

Moodle core does not run its production `lib/` tree through Composer. It
vendors a fixed set of third-party libraries directly under `lib/<name>/`
and registers their namespaces **globally**, for every request, via
`core_component::$psr4namespaces` (`lib/classes/component.php`) — resolved
by Moodle's own autoloader, independent of and typically active before any
plugin's own `vendor/autoload.php` is required. Verified against a Moodle
5.0 checkout, the registered map includes (non-exhaustive):

| Namespace | Moodle-bundled path | Bundled version |
|---|---|---|
| `Firebase\JWT` | `lib/php-jwt/src` | 6.11.0 |
| `Psr\Log` | `lib/psr/log/src` | (PSR interfaces only) |
| `Psr\Container` | `lib/psr/container/src` | (PSR interfaces only) |
| `Psr\SimpleCache` | `lib/psr/simple-cache/src` | (PSR interfaces only) |
| `Psr\Http\Message` | `lib/psr/http-message/src`, `lib/psr/http-factory/src` | (PSR interfaces only) |
| `Psr\Http\Client` | `lib/psr/http-client/src` | (PSR interfaces only) |
| `GuzzleHttp`, `GuzzleHttp\Psr7`, `GuzzleHttp\Promise` | `lib/guzzlehttp/*` | bundled Guzzle stack |
| `PHPMailer\PHPMailer` | `lib/phpmailer/src` | bundled PHPMailer |

This adapter's own `composer.json` independently requires four packages
that land in the **same namespaces** as rows above: `firebase/php-jwt
^7.0`, `psr/container ^2.0`, `psr/log ^3.0`, `psr/simple-cache ^3.0` (plus
`Psr\Http\Message`/`Psr\Http\Client` transitively via `nyholm/psr7` and
`symfony/http-client`). `nyholm/psr7` and `symfony/http-client` themselves
do **not** collide — Moodle bundles GuzzleHttp's PSR-7 implementation
(`GuzzleHttp\Psr7\*`), a different vendor namespace than Nyholm's
(`Nyholm\Psr7\*`); only the shared PSR **interface** namespaces overlap.

**Confirmed concrete case:** `firebase/php-jwt`. Moodle 5.0 bundles
`Firebase\JWT` v6.11.0; this adapter's `composer.json` requires `^7.0`.
v6→v7 changed the `JWT`/`Key` API. A `grep` of `src/` at the time of this
decision finds **no usage** of `Firebase\JWT\*` anywhere in this adapter —
the dependency is declared but dead code today, which is exactly why the
collision is dormant rather than already-triggered: nothing here currently
calls `use Firebase\JWT\JWT;` to race the two autoloaders. The moment any
consumer layer does, which autoloader wins is a function of bootstrap
order (does the host plugin `require` `vendor/autoload.php` before or
after Moodle's own `lib/setup.php` registers `core_component`'s PSR-4
map?) — which is not something a single package's composer.json can pin;
it varies by hosting layout (bundled plugin vs. Composer-managed site vs.
project-root vendor in a monorepo Docker setup). `local_middag/lib.php`'s
own `local_middag_autoload()` already documents this exact fragility for
`Middag\*` classes ("this only controls which autoloader local_middag
registers. It cannot unload a `Middag\*` class another autoloader
resolved earlier in the request.") — the same physics apply to any
third-party namespace shared with Moodle's bundled set; that comment just
never generalized past `Middag\*`.

## Considered Options

1. **Vendor-prefix/scope every shared dependency (e.g. via a build-time
   namespace rewrite tool)** — rejected: adds a build step and a
   maintenance burden to an OSS host adapter for a risk that is only live
   for four packages today, three of which are pure-interface PSR
   packages where a major bump is rare and loudly breaking by design.
2. **Vendor nothing that Moodle bundles; always delegate to Moodle's
   bundled copy instead of a composer dependency** — rejected: makes this
   adapter's own composer.json a lie about what it actually runs against,
   and ties this OSS adapter's dependency versions to whatever a given
   Moodle version happens to bundle, defeating independent versioning.
3. **Do nothing — treat it as a generic "Composer vs. plugin autoloading"
   problem every Moodle plugin author already knows about** — rejected:
   the risk is real, concrete (see the `firebase/php-jwt` case above), and
   specific enough (an exact list of colliding namespaces, an exact
   version mismatch) that leaving it undocumented means every future
   contributor re-derives it from scratch, or worse, ships the collision
   silently.

## Decision

- This adapter keeps declaring its own composer dependencies normally —
  no namespace rewriting, no delegating to Moodle's bundled copy.
- The table above is the canonical, living list of **shared-namespace
  packages**: any composer.json dependency whose namespace also appears in
  Moodle core's `core_component::$psr4namespaces` map. Before bumping a
  shared-namespace package's constraint to a new major, or before raising
  this adapter's minimum-supported Moodle version, check the new major
  against what the target Moodle version(s) bundle for that namespace.
- An unused shared-namespace dependency (the `firebase/php-jwt` case) is a
  standing collision risk with zero present benefit — it gets removed from
  `composer.json` the next time this file is touched for another reason,
  rather than kept "just in case" (tracked as follow-up cleanup, not part
  of this decision's enforcement — a removal is a `composer.json` change,
  which belongs in its own PR).
- Autoloader race order (this adapter's `vendor/autoload.php` vs. Moodle's
  `core_component` PSR-4 map) is explicitly **not** something this
  adapter's own code can pin — it is a property of how the consuming
  product bootstraps. This ADR documents the risk surface; it does not
  (and cannot, from this layer) resolve the race.

## Consequences

- This adapter and its consumers now have a single, explicit reference
  for "which third-party namespaces does the OSS Moodle adapter share
  with Moodle core's bundled globals, and what is the known version
  delta" — instead of re-discovering it (or not) independently.
- The dormant `firebase/php-jwt` collision is now a tracked, visible fact
  instead of a silent landmine — a future contributor adding real JWT
  usage to this adapter will find this table before shipping it.
- No automated enforcement exists yet (no CI check diffs this adapter's
  composer.json namespaces against a Moodle core checkout's
  `core_component::$psr4namespaces`) — see Enforcement below.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Shared-namespace table stays accurate (`firebase/php-jwt`, `psr/log`, `psr/container`, `psr/simple-cache`, `psr/http-message`, `psr/http-client`) | No automated check found | planned |
| Major-version bump of a shared-namespace package is checked against Moodle's bundled version before merge | No automated check found | planned |
| Unused shared-namespace dependency (`firebase/php-jwt`) removed from `composer.json` | Tracked as follow-up cleanup, separate PR | planned |
