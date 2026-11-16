# API Stability

This document defines what is public and supported in `middag-io/moodle`, and
how the public surface may evolve, so consumers — the Moodle plugins built on
top of the adapter, and any proprietary layer above it — can depend on it
without guessing.

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
| `@api` | **Public, supported surface.** Consumer plugins may implement, extend, type-hint, instantiate and catch these. Changes follow [Versioning](#versioning). |
| `@internal` | **Implementation detail.** May change or be removed in any release, including patches. Do not depend on these from outside the package. |

If a type has neither annotation, treat it as `@internal`. Every type in `src/`
carries exactly one of the two tags.

The public surface is the set of `@api`-annotated types: the adapter's
consumer-facing API — the `Domain\*` entities, DTOs and enums; the domain
`Contract/` service interfaces; the `Settings\*` and `Definition\*` builders; the
`Security` value objects and `Contract/` interfaces, plus `Security\Attribute\Sesskey`,
`Security\Enum\CapabilityRisk` and `Security\Enum\CapabilityType`; the `Privacy` provider
contracts and `Privacy\PrivacyProvider`; `Output\MoodleRenderer` and
`Output\Contract\ViewAdapterInterface`; `Http\Contract\MoodleControllerInterface`; the `Support\Moodle`
façade and the public `Support\*` Moodle API wrappers; the adapter-specific
`Exception\*` types; the `Output\Table` helpers; `Form\DraftFileValueMapper`,
which maps the `uploadToken` of a `middag-io/ui` `FileValue` to a Moodle draft
area and back (on save, the submitted list decides which files are kept, and a
cleared field leaves the stored files in place); and the `WebService\AbstractExternal` extension base a plugin's
external classes subclass.

**Composition-root concretes are public too.** This adapter deliberately ships
no container builder: whoever starts the kernel supplies its own
via `Runtime\ContainerFactory::setBuilder()`. A consumer therefore *has* to name
the host-bridge concretes it registers or instantiates at boot, so those are
`@api` — `Runtime\Kernel`, `Runtime\ContainerFactory`,
`Runtime\MoodleComponentNameResolver`, `Runtime\Loader\FacadeLoader`,
`Config\ComponentContext`, `Bus\MoodleUserContext`, `Bus\MoodleHostEventBridge`,
`Logging\MoodleLogger`,
`Logging\MoodleActorResolver`, `Logging\MoodleOriginResolver`,
`Translation\MoodleTranslator`, `Output\MoodleView`,
`Database\Schema\XmldbSchemaAdapter`, `Persistence\VersionTracker`,
`Persistence\Query\SqlGenerator`, `Persistence\UpgradeHelper`,
`Runtime\MoodleHostContext`, `Runtime\Loader\MoodleHookfileLoader`,
`Bus\MoodleAdhocTransport`, `Mail\MoodleMailer`, `Filesystem\MoodledataFilesystem`,
`Hook\AbstractExtendExtensions`, `Shared\Enum\TextFormat`, `Shared\Util\Debug`,
`Shared\Util\Environment`, `Settings\SettingsResolver`,
`Support\DiBridgeSupport` (the composition root lists the services it exports
to Moodle's DI container with `registerExport()`) and
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

`Support\CrudConventionResolver`, `Support\TaskDefinitionBuilder` and
`Support\RouterBridgeSupport` remain internal wiring/convention helpers. `Middag\DevTools\Moodle\Statics` owns static
`db/*.php` generation; it is not runtime API of this adapter.

## Versioning

The support rules of the `middag-php-*` version family (lifecycle, what a
patch, minor and major may carry, the supported surface and the compatibility
matrix) are on
[Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).
The platform and the CI jobs, the family-window check included, are on
[Platform](https://docs.middag.dev/libraries/reference/platform).

What applies in this repository:

- Releases are cut only by
  [release-please](https://github.com/googleapis/release-please) from
  [Conventional Commits](https://www.conventionalcommits.org/). There are no
  manual tags.
- **On `1.x`:** `fix:` cuts a patch and `feat:` cuts a minor. A break of the
  `@api` surface ships as `feat:`, never as `fix:`, `refactor:` or `chore:`,
  and never with `!` or a `BREAKING CHANGE:` footer (release-please would cut
  `2.0.0` in this lib alone, and the family-window check refuses both). The
  1.x line is pre-stable and unsupported, and the move to `2.0.0` is a clean
  cut.
- **From `2.0.0`:** every `middag-php-*` library shares `MAJOR.MINOR`. A patch
  carries fixes only, a minor never breaks, and a major carries the breaks,
  each one deprecated first in an earlier minor.
- Every pull request runs the family-window check
  `php-version-family-check / check` (`.github/workflows/family-window.yml`).

## The contracts this adapter fulfils

The adapter does not define the host-bridge contracts — it implements the ones
declared in `middag-io/framework`. Depend on the **framework**
`@api` interface, not on the Moodle concrete:

| Framework contract (`@api` in framework) | Moodle implementation | Stability here |
|---|---|---|
| `ConnectionAdapterInterface` | `Middag\Moodle\Database\MoodleConnectionAdapter` | `@internal` |
| `SqlDialectInterface` | `Middag\Moodle\Database\MoodleSqlDialect` | `@internal` |
| `TranslatorInterface` | `Middag\Moodle\Translation\MoodleTranslator` | `@api` |
| `ConfigResolverInterface` | `Middag\Moodle\Config\MoodleConfigResolver` | `@internal` |
| `UserContextResolverInterface` | `Middag\Moodle\Bus\MoodleUserContext` | `@api` |
| `MaintenanceGateInterface` | `Middag\Moodle\Runtime\MoodleMaintenanceGate` | `@internal` |
| `BootstrapInterface` | `Middag\Moodle\Runtime\MoodleBootstrap` | `@internal` |

The adapter also implements the framework's bridge seams
(`MoodleHostContext`, `MoodleHostEventBridge`, `MoodleComponentNameResolver`) and
the native hookfile loader; the boot-time seams a consumer instantiates and
registers (e.g. `MoodleHostContext`) are `@api`, by the same rule that makes
the `@api` rows above public. Type-hint the framework contract; name the
concrete only to wire it.

## Depending on `middag-io/moodle` safely

- Depend only on `@api` types. If you need behaviour exposed only by an
  `@internal` symbol, open an issue to have it promoted rather than reaching in.
- **On `1.x`:** the line is pre-stable and unsupported. It carries no
  compatibility promise, and a `1.x` minor may carry a break (see
  [Versioning](#versioning)). Support starts at `2.0.0`, and the move from
  `1.x` is a clean cut.
- **From `2.0.0`:** declare one `^2.N` range, with the same `N`, for this
  adapter and every other `middag-php-*` library you depend on (`moodle`,
  `framework`, `ui`, …).
- The dependency direction only points downward: the adapter depends on the OSS
  framework's and ui's published `@api` and never imports the proprietary layer.
