# API Stability

This document defines what is public and supported in `middag-io/moodle`, and
how the public surface may evolve on the current **`1.x`** line and from
`2.0.0`, so consumers — the Moodle plugins built on top of the adapter, and any
proprietary layer above it — can depend on it without guessing.

`middag-io/moodle` is a **host adapter**: it implements the host-bridge
contracts defined by `middag-io/framework` (and consumes `middag-io/ui`) against
Moodle's native APIs (`$DB` / `moodle_database`, `get_string()`, the capability
and file subsystems, XMLDB, scheduled tasks, external services), so domain code
written against the framework contracts runs unchanged on Moodle. It is
Apache-2.0 OSS and imports no proprietary MIDDAG code — enforced by
`tests/AdapterPluginIsolationTest.php`, which fails the build if `src/` imports
any non-OSS MIDDAG namespace.

## Stability levels

Every type carries a class-level annotation that states its stability:

| Annotation | Meaning |
|---|---|
| `@api` | **Public, supported surface.** Consumer plugins may implement, extend, type-hint, instantiate and catch these. Changes follow the versioning policy below. |
| `@internal` | **Implementation detail.** May change or be removed in any release, including patches. Do not depend on these from outside the package. |

If a type has neither annotation, treat it as `@internal`. Every type in `src/`
carries exactly one of the two tags.

The public surface is the set of `@api`-annotated types: the adapter's
consumer-facing API — the `Domain\*` entities, DTOs and enums; the domain
`Contract/` service interfaces; the `Settings\*` and `Definition\*` builders; the
`Security` value objects and `Contract/` interfaces; the `Privacy` provider
contracts; `Http\Contract\MoodleControllerInterface`; the `Support\Moodle`
façade and the public `Support\*` Moodle API wrappers; the adapter-specific
`Exception\*` types; the `Output\Table` helpers; and the `WebService\AbstractExternal`
extension base a plugin's external classes subclass.

**Composition-root concretes are public too.** This adapter deliberately ships
no container builder (D-FACADE-SEAM): whoever starts the kernel supplies its own
via `Runtime\ContainerFactory::setBuilder()`. A consumer therefore *has* to name
the host-bridge concretes it registers or instantiates at boot, so those are
`@api` — `Runtime\Kernel`, `Runtime\ContainerFactory`,
`Runtime\MoodleComponentNameResolver`, `Runtime\Loader\FacadeLoader`,
`Config\ComponentContext`, `Bus\MoodleUserContext`, `Bus\MoodleHostEventBridge`,
`Logging\MoodleLogger`,
`Logging\MoodleActorResolver`, `Logging\MoodleOriginResolver`,
`Translation\MoodleTranslator`, `Output\MoodleView`,
`Database\Schema\XmldbSchemaAdapter`, `Persistence\VersionTracker`,
`Persistence\Query\SqlGenerator`, `Settings\SettingsResolver` and
`Http\Contract\RouterInterface`. Prefer the **framework** contract as the type in
your own code — the concrete is public because you must *name* it when wiring,
not because you should depend on its shape. (`SqlGenerator` is the exception that
proves the rule: nothing binds `ConditionCompilerInterface` on Moodle, so a
container autowires the concrete off the type-hint itself.)

`Http\Routing\PluginAwareUrlGenerator` is `@api` for a narrower reason still: the
router instantiates it, but its constructor takes a `RouteCollection` and a
`RequestContext`, so a consumer that auto-discovers this package's `src/` has to
name the class in order to *exclude* it from autowiring. Naming it is the whole
contract — do not extend or type-hint it.

What stays `@internal` is the wiring a consumer never names: the remaining
host-bridge concretes (`MoodleConnectionAdapter`, `MoodleSqlDialect`,
`MoodleConfigResolver`, `MoodleMaintenanceGate`, `MoodleBootstrap`), and the
internal HTTP / kernel / support plumbing — including `Http\Routing\MoodleRouter`,
which the kernel builds and hands out as `Http\Contract\RouterInterface`; depend
on the interface instead.

`Support\CrudConventionResolver`, `Support\TaskDefinitionBuilder`,
`Support\DiBridgeSupport`, and `Support\RouterBridgeSupport` remain internal
wiring/convention helpers. `Middag\DevTools\Moodle\Statics` owns static
`db/*.php` generation; it is not runtime API of this adapter.

## How releases are cut

Releases are cut **exclusively** by
[release-please](https://github.com/googleapis/release-please) from
[Conventional Commits](https://www.conventionalcommits.org/). There are no
manual tags. On `1.x` the version comes from the commit type (`fix:` → patch,
`feat:` → minor). From `2.0.0`, commit types only produce patches, and a family
minor or major comes from a coordinated `Release-As: X.Y.0` footer in every
library of the family (see below).

The policy below is decided in ADR-032 of the internal `docs-middag-ops`
(it amends ADR-003 for the `middag-php-*` libraries) and mirrored in
the framework's
[`API-STABILITY.md`](https://github.com/middag-io/middag-php-framework/blob/main/API-STABILITY.md).

## The `1.x` policy (until the `2.0.0` cut)

During the `1.x` line the API is **still consolidating**:

- **Patch** (`1.y.Z`) — bug fixes and `@internal`-only changes. Never a breaking
  `@api` change.
- **Minor** (`1.Y.0`) — additive `@api` changes (new helpers, new optional
  parameters, promoting an `@internal` symbol to `@api`), cut by release-please
  from `feat:`. Until **2026-11-30** a minor may also carry a breaking `@api`
  change, under a recorded exception that ends on that date and is not renewed.
  The break ships as `feat:` (so it cuts a minor, never a patch) with the break
  and the migration described in the commit body, so it still appears in the
  CHANGELOG. It never ships as `fix:`, `refactor:` or `chore:`.
- **Never** `!` before the colon or a `BREAKING CHANGE:` footer on `1.x`:
  release-please would cut `2.0.0` in this library alone, and the
  [version family check](#the-version-family-check) refuses it.
- **From 2026-12-01**, if the family has not reached `2.0.0`, no break lands on
  `1.x`: the line is frozen to non-breaking changes until the cut.

There is no `1.x` major. The family crosses `2.0.0` together, on a date the
product owner decides.

## From `2.0.0`: the version family

From `2.0.0`, all `middag-php-*` libraries (`framework`, `core`, `ui`,
`moodle`, `wordpress`, `licensing`, `dev-tools`, `demo-standalone`) form one
**version family**:

- **Shared MAJOR.MINOR.** Every library carries the same `MAJOR.MINOR`; the
  patch number is per library. A family minor or major goes out in every
  library, changed or not.
- **Patch** (`2.y.Z`) — fixes only (`fix:`). release-please never cuts a minor
  or a major by itself.
- **Minor** (`2.Y.0`) — new features, deprecations and new supported ranges.
  **A minor never breaks.** A `feat:` waits on its pull request until the
  product owner opens a family minor window.
- **Major** (`X.0.0`) — breaking changes, each one deprecated first (with a
  runtime notice) in an earlier minor. `!` or `BREAKING CHANGE:` is accepted
  only while a family major window is open.
- **Who decides.** The product owner decides each family minor (on a new Moodle
  version to support, a MIDDAG release that needs a feature, a deprecation or a
  new range, or a new Symfony minor) and every family major.
- **Previous major.** The last minor of the previous major gets security fixes
  only, for 6 months after the next major ships.

## The version family check

Every pull request runs the check `php-version-family-check / check`
(`.github/workflows/family-window.yml`). It reads the pull request title and
every non-merge commit (the header type, and any `BREAKING CHANGE:` or
`BREAKING-CHANGE:` footer) against the window in the organization variable
`PHP_LIBS_OPEN_RELEASE`, set and cleared on the order of the product owner. When
it is empty the window is closed, `X.Y` with `Y > 0` opens a family minor
window, and `X.0` opens a family major window. The `2.0.0` cut is window `2.0`.

| Change | Passes when |
|---|---|
| `fix:`, `docs:`, `refactor:`, … | always |
| `feat:` | this library is below `2.0.0` (on `1.x`), or a window is open |
| `!` or a `BREAKING CHANGE:` footer | a family major window is open |
| `Release-As:` footer | a window is open and the footer names its `X.Y.0` |

To pass with a held feature, leave the pull request open and keep it current by
merging `main` into it (never rebase or force-push). Once the window opens,
re-run the check.

- A window counts as open only when it is ahead of this library's current
  version and the pull request targets `main`; a value left set after its
  family release reads as closed.
- While a window is open, this library's Release PR is merged only at the
  coordinated `X.Y.0`.
- A re-run reads the base of the original event. After the `2.0.0` cut, a pull
  request opened before the cut must merge `main` into it so that the check
  reads the new version.

> Historical note: `1.1.1` shipped the audit-consolidation breaking changes (the
> batch-A renames, the framework Translation port replacing the local interface,
> and the PDFTk adapter removal) as a patch by explicit maintainer decision,
> closing the OSS audit before external consumers existed. From this document on,
> a breaking `@api` change never lands in a patch.

## The contracts this adapter fulfils

The adapter does not define the host-bridge contracts — it implements the ones
declared (and frozen) in `middag-io/framework`. Depend on the **framework**
`@api` interface, not on the Moodle concrete:

| Framework contract (`@api` in framework) | Moodle implementation (`@internal` here) |
|---|---|
| `ConnectionAdapterInterface` | `Middag\Moodle\Database\MoodleConnectionAdapter` |
| `SqlDialectInterface` | `Middag\Moodle\Database\MoodleSqlDialect` |
| `TranslatorInterface` | `Middag\Moodle\Translation\MoodleTranslator` |
| `ConfigResolverInterface` | `Middag\Moodle\Config\MoodleConfigResolver` |
| `UserContextResolverInterface` | `Middag\Moodle\Bus\MoodleUserContext` |
| `MaintenanceGateInterface` | `Middag\Moodle\Runtime\MoodleMaintenanceGate` |
| `BootstrapInterface` | `Middag\Moodle\Runtime\MoodleBootstrap` |

The adapter also implements the framework's frozen bridge seams
(`MoodleHostContext`, `MoodleHostEventBridge`, `MoodleComponentNameResolver`) and
the native hookfile loader; the boot-time seams a consumer instantiates and
registers (e.g. `MoodleHostContext`) are `@api`.

## Depending on `middag-io/moodle` safely

- Depend only on `@api` types. If you need behaviour exposed only by an
  `@internal` symbol, open an issue to have it promoted rather than reaching in.
- **On `1.x`:** pin a caret range (`^1.0`) and read the CHANGELOG entries
  before crossing a minor; until the `2.0.0` cut a minor may carry a described
  break (see the `1.x` policy above).
- **From `2.0.0`:** declare one family range (`^2.N`) for every `middag-io/*`
  library you depend on (`moodle`, `framework`, `ui`, …). Minor upgrades
  inside the range do not break.
- **Zero-surprise upgrades:** pin a tilde patch range (for example `~1.2.2`) to
  receive only patches.
- The dependency direction only points downward: the adapter depends on the OSS
  framework's and ui's published `@api` and never imports the proprietary layer.
