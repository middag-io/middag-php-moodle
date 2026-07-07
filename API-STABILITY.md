# API Stability

This document defines what is public and supported in `middag-io/moodle`, and
how the public surface may evolve during the current **`1.x`** line, so
consumers — the Moodle plugins built on top of the adapter, and any proprietary
layer above it — can depend on it without guessing.

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
extension base a plugin's external classes subclass. The concrete
implementations of the framework's host-bridge contracts
(`MoodleConnectionAdapter`, `MoodleSqlDialect`, `MoodleTranslator`,
`MoodleConfigResolver`, `MoodleUserContext`, `MoodleMaintenanceGate`,
`MoodleBootstrap`) and the internal HTTP / kernel / support wiring are
`@internal`: consumers depend on the **framework** contract these fulfil, not on
the Moodle concrete.

`Support\CrudConventionResolver`, `Support\TaskDefinitionBuilder`,
`Support\DiBridgeSupport`, and `Support\RouterBridgeSupport` remain internal
wiring/convention helpers. `Middag\DevTools\Moodle\Statics` owns static
`db/*.php` generation; it is not runtime API of this adapter.

## How releases are cut

Releases are cut **exclusively** by
[release-please](https://github.com/googleapis/release-please) from
[Conventional Commits](https://www.conventionalcommits.org/). There are no
manual tags: the version is derived from the commit type (`fix:` → patch,
`feat:` → minor), or set deliberately by a maintainer with a `Release-As:`
footer.

## The `1.x` policy

This mirrors the family-wide policy defined in the framework's
[`API-STABILITY.md`](https://github.com/middag-io/middag-php-framework/blob/main/API-STABILITY.md).
During the `1.x` line the API is **still consolidating**:

- **Patch** (`1.y.Z`) — bug fixes and `@internal`-only changes. Never a breaking
  `@api` change.
- **Minor** (`1.Y.0`) — additive `@api` changes (new helpers, new optional
  parameters, promoting an `@internal` symbol to `@api`). A minor **may also
  carry a breaking `@api` change** while the API consolidates. Every breaking
  change is explicitly marked in the history (`feat!` / a `BREAKING CHANGE:`
  footer) and listed in the CHANGELOG's **⚠ BREAKING CHANGES** section. Such
  releases are cut deliberately by a maintainer with a `Release-As:` footer —
  never as an accidental side effect of merging.

Full strict-semver rigor — breaking changes **only** in major releases — starts
at `2.0`. A major is never cut automatically: only by explicit maintainer
decision, when the break genuinely impacts Composer consumers.

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
| `MaintenanceGateInterface` | `Middag\Moodle\Kernel\MoodleMaintenanceGate` |
| `BootstrapInterface` | `Middag\Moodle\Kernel\MoodleBootstrap` |

The adapter also implements the framework's frozen bridge seams
(`MoodleHostContext`, `MoodleHostEventBridge`, `MoodleComponentNameResolver`) and
the native hookfile loader; the boot-time seams a consumer instantiates and
registers (e.g. `MoodleHostContext`) are `@api`.

## Depending on `middag-io/moodle` safely

- Depend only on `@api` types. If you need behaviour exposed only by an
  `@internal` symbol, open an issue to have it promoted rather than reaching in.
- **Default:** pin a caret range (`^1.0`) and read the CHANGELOG's **⚠ BREAKING
  CHANGES** section before crossing a minor.
- **Zero-surprise upgrades:** pin a tilde patch range (for example `~1.2.2`) to
  receive only patches.
- The dependency direction only points downward: the adapter depends on the OSS
  framework's and ui's published `@api` and never imports the proprietary layer.
