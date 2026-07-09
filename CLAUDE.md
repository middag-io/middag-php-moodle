# CLAUDE.md — middag-io/moodle

Agent guide: conventions and gotchas for working on this repo. Contributor
setup lives in `README.md` / `CONTRIBUTING.md`.

## What this package is

OSS (Apache-2.0) Moodle host adapter for the MIDDAG framework. It implements
the framework's adapter contracts on top of Moodle's runtime APIs — kernel and
bootstrap, config resolution, bus transport, database, logging, mail,
filesystem, translation, HTTP/Inertia — plus stateless Moodle API wrappers
(`Support/`), host domain services and DTOs (`Domain/`), declarative plugin
definitions (`Definition/`), and admin settings types (`Settings/`).

- **Depends on** `middag-io/framework` (`^1.0.2`) and `middag-io/ui` (`^1.2`;
  `Form/` implements the ui form contracts).
- **Consumed by** a Moodle host plugin (any `local_*`/`mod_*`) that provides
  its own composition root. No plugin is bundled here.
- **Never imports non-OSS MIDDAG namespaces or product code** — enforced by
  `tests/AdapterPluginIsolationTest.php` (part of `composer test`).

## Structure (`src/`)

| Directory | Contents |
|-----------|----------|
| `Bus/` | `MoodleAdhocTransport` (framework `TransportInterface` → adhoc tasks), `MoodleHostEventBridge`, `MoodleUserContext` |
| `Config/` | `MoodleConfigResolver` (framework `ConfigResolverInterface`), `ComponentContext` (running-component name) |
| `Database/` | `MoodleConnectionAdapter`, `MoodleSqlDialect`, `TransactionManager` (+ local `Contract/`), `Schema/XmldbSchemaAdapter` |
| `Definition/` | Declarative plugin definitions, all suffixed `*Definition` (Cache, Capability, Check, Event, FileArea, Hook, Message, Service, WebService) + `Contract/DefinitionInterface` |
| `Domain/` | 16 host capability areas (Backup, Calendar, Completion, Context, Course, CustomField, Enrolment, Event, File, Grade, Group, Message, Platform, Role, Task, User): entities, DTOs, enums, services. Services implement local `*/Contract/` interfaces (host domain, not framework ports). `AbstractMoodleEntity` implements the framework `EntityInterface` |
| `Exception/` | Adapter-specific exception hierarchy for configuration, transport, version and host failures |
| `Filesystem/` | `MoodledataFilesystem` (framework `FilesystemInterface` → dataroot) |
| `Form/` | `MformRenderer` (ui `FormRendererInterface` → mforms), `MformFieldMapper`, `MformElementSpec` |
| `Hook/` | `AbstractExtendExtensions` (Moodle hook API base) |
| `Http/` | `MoodleHttpKernel` (extends framework `HttpKernel`; applies `#[Sesskey]`), `Client/HttpClientAdapter`, `Concerns/`, `Controller/{AbstractController,AbstractApiController}`, `Inertia/`, `Routing/{MoodleRouter,RouteLoader,MiddagProxy,PluginAwareUrlGenerator}` |
| `Runtime/` | `Kernel` (singleton, framework `KernelInterface`), `ContainerFactory`, `MoodleBootstrap`, `MoodleHostContext`, `MoodleComponentNameResolver`, `MoodleMaintenanceGate`, `Facade/AbstractFacade`, `Loader/{FacadeLoader,MoodleHookfileLoader}` |
| `Logging/` | `MoodleLogger` (PSR-3 `AbstractLogger`), `MoodleActorResolver`, `MoodleOriginResolver` |
| `Mail/` | `MoodleMailer` (framework `MailerInterface` → `email_to_user`) |
| `Output/` | `MoodleView` (local `ViewAdapterInterface`), `MoodleRenderer` (extends `plugin_renderer_base`), `AbstractBlock`, `NavbarService`, `Widget`, `Table/UsersTable`, `Table/UsersFilterset` |
| `Persistence/` | `UpgradeHelper`, `VersionTracker` (framework `VersionTrackerInterface`), `Query/SqlGenerator` |
| `Privacy/` | `PrivacyProvider` + `Contract/` (Moodle privacy API) |
| `Security/` | `Authentication`, `Authorizer`, `Capability` (+ `Contract/`, `Enum/`, `ValueObject/`, `Attribute/Sesskey`) |
| `Settings/` | Settings-as-code family: root keeps the mechanism (`AbstractSetting`, `Page`, `SettingsNamingPolicy`, `SettingsResolver`, `Enum/SettingType`); `Settings/Type/` holds the 22 DSL classes mirroring Moodle's `admin_setting_*` types |
| `Shared/` | Closed vocabulary: `Concerns/`, `Enum/`, `Util/` only |
| `Support/` | 45 stateless Moodle API wrappers, named `*Support` + `Moodle` (static aggregator facade), `TaskDefinitionBuilder`, `CrudConventionResolver`, `CacheSupportPsr16` (PSR-16) |
| `Translation/` | `MoodleTranslator` (framework `TranslatorInterface` → `get_string`) |
| `WebService/` | `AbstractExternal` (Moodle external API base) |

## Framework contracts bridge

| Framework port | Moodle implementation |
|---|---|
| `Kernel\Contract\KernelInterface` | `Runtime\Kernel` |
| `Kernel\Contract\BootstrapInterface` | `Runtime\MoodleBootstrap` |
| `Kernel\Contract\MaintenanceGateInterface` | `Runtime\MoodleMaintenanceGate` |
| `Kernel\Contract\HostComponentContextInterface` | `Runtime\MoodleHostContext` |
| `Kernel\Contract\ComponentNameResolverInterface` | `Runtime\MoodleComponentNameResolver` |
| `Kernel\Contract\ConfigResolverInterface` | `Config\MoodleConfigResolver` |
| `Kernel\Contract\HostEventBridgeInterface` | `Bus\MoodleHostEventBridge` |
| `Kernel\Contract\FacadeInterface` | `Runtime\Facade\AbstractFacade` |
| `Kernel\Contract\FacadeLoaderInterface` | `Runtime\Loader\FacadeLoader` |
| `Kernel\Loader\HookfileLoader` (extends) | `Runtime\Loader\MoodleHookfileLoader` |
| `Bus\Contract\TransportInterface` | `Bus\MoodleAdhocTransport` |
| `Bus\Contract\UserContextResolverInterface` | `Bus\MoodleUserContext` |
| `Database\Contract\ConnectionAdapterInterface` | `Database\MoodleConnectionAdapter` |
| `Database\Contract\SqlDialectInterface` | `Database\MoodleSqlDialect` |
| `Database\Contract\SchemaBuilderAdapterInterface` | `Database\Schema\XmldbSchemaAdapter` |
| `Database\Contract\VersionTrackerInterface` | `Persistence\VersionTracker` |
| `Logging\Contract\ActorResolverInterface` | `Logging\MoodleActorResolver` |
| `Logging\Contract\OriginResolverInterface` | `Logging\MoodleOriginResolver` |
| `Mail\Contract\MailerInterface` | `Mail\MoodleMailer` |
| `Filesystem\Contract\FilesystemInterface` | `Filesystem\MoodledataFilesystem` |
| `Translation\Contract\TranslatorInterface` | `Translation\MoodleTranslator` |
| `Persistence\Contract\EntityInterface` | `Domain\AbstractMoodleEntity` |
| `Http\HttpKernel` (extends) | `Http\MoodleHttpKernel` |
| `Http\Contract\RouteLoaderInterface` | `Http\Routing\RouteLoader` |
| `Http\Contract\ControllerInterface` | `Http\Contract\MoodleControllerInterface` (extends it; implemented by `Http\Controller\AbstractController`) |
| ui `Form\Contract\FormRendererInterface` | `Form\MformRenderer` |

PSR ports: `Logging\MoodleLogger` (PSR-3), `Support\CacheSupportPsr16` (PSR-16).

## Naming rules

- **Host prefix `Moodle*`** when the basename would otherwise be generic or
  collide across libs: `MoodleTranslator`, `MoodleLogger`, `MoodleRouter`,
  `MoodleView`, `MoodleRenderer`, `MoodleMailer`, `MoodleConnectionAdapter`, …
- **No prefix** for names faithful to a Moodle API surface (`Mform*`,
  `Xmldb*`, `UsersTable`) and for low-ambiguity adapters (`RouteLoader`,
  `HttpClientAdapter`, `TransactionManager`, `VersionTracker`,
  `PrivacyProvider`).
- `Definition/` classes are suffixed `*Definition`; `Support/` wrappers are
  suffixed `*Support`; local interfaces live in `Contract/` subdirectories.

## Guard tests (run under `composer test`)

- `tests/ClassLoadabilityTest.php` — every PSR-4 symbol under `src/` (228)
  must autoload **without a Moodle runtime**. 11 documented host-only
  exclusions (classes requiring Moodle files at file scope or extending core
  classes). If you add a class that cannot load standalone, add it to the
  documented exclusion list with the reason — otherwise the test fails.
- `tests/AdapterPluginIsolationTest.php` — source-scans `src/` for non-OSS
  MIDDAG namespaces and MIDDAG gold-table string literals; the OSS adapter
  must never reference either.

Tests run without Moodle: `tests/bootstrap.php` provides minimal Moodle
function stubs whose return values are driven via `$GLOBALS`
(e.g. `$GLOBALS['__middag_test_component_dir']` for the component registry).
PHPStan resolves Moodle symbols through `michaelmeneses/moodle-stubs`.

## Gotchas

1. **Host paths: use `Kernel::hostDirectory()`**, which resolves the consumer
   plugin's directory through Moodle's component registry
   (`core_component::get_component_directory()`). `Kernel::PROJECT_ROOT` is
   `@deprecated` — as a Composer dependency it resolves inside `vendor/`, not
   the consumer plugin. There are no package-relative monolith fallbacks.
2. **`SettingsSupport` derives the config slug from a `{slug}_config` enum's
   short name** (with a `framework → core` special case → `mdg_core_*` keys).
   PascalCase spellings are normalised to snake_case first, and an enum whose
   name cannot be mapped onto `{slug}_config` is rejected with
   `InvalidArgumentException` — it never silently resolves a dead key. The
   adapter no longer ships a `framework_config` enum: those framework-tier keys
   are owned by the consumer product (e.g. `Middag\Core\Config\FrameworkConfig`);
   this lib only provides the generic resolution mechanism.
3. **`Settings/Type/Storedfile` vs `Domain/File/StoredFile`** differ only by
   case — an accepted divergence: `Settings/Type/` mirrors Moodle's
   `admin_setting_*` naming (class names stay Moodle-cased, e.g. `Storedfile`,
   `Htmleditor`), `Domain/` is the entity. Case-sensitive Linux CI guards
   collisions.
4. **There is no PDF surface in this adapter.** `Pdf/PdftkAdapter` (and the
   `mikehaertl/php-pdftk` dependency) moved to the proprietary MIDDAG core
   package. Do not reintroduce PDF tooling here.
5. **Translation:** `MoodleTranslator` implements the framework Translation
   port (`get()`/`has()`). Framework `%name%` params map onto Moodle's `$a`
   placeholder object (`'%count%'` → `$a->count`); an empty `$component`
   falls back to `ComponentContext::name()`. Host errors propagate — the
   adapter never swallows Throwables. There is no local translator interface.
6. **`Form/MformRenderer` is the only form-subsystem file allowed to reference
   `moodleform`** (it `require_once`s formslib at file scope — hence a
   documented loadability exclusion).
7. **Facades belong to consumer products, not this lib.** `Support/Moodle` is
   a static aggregator returning fresh `*Support` instances; MIDDAG product
   facades are generated by dev-tools inside the consumer plugin
   (`{component}\facade\...`), never added here.
   **The facade mechanism itself is OSS-generic and bootless by design
   (D-FACADE-SEAM, 2026-07-08):** the loader + `Facade/AbstractFacade` proxy
   + dev-tools generator need no middag-io/core. Whoever "starts" the kernel
   (the product OR a third-party plugin) supplies the container builder via
   `ContainerFactory::setBuilder()` and registers the accessor's service —
   `ContainerFactory::getInstance()` throws until then (empty seam; do NOT
   ship a default OSS builder). A facade whose accessor is a core/premium
   service therefore requires core; one whose accessor is a service the
   plugin registers itself does not
   (`tests/Runtime/Facade/ThirdPartyFacadeResolutionTest.php` proves the
   no-core path end-to-end; `FacadeLoaderCoverageTest` proves
   `new FacadeLoader()` with a null root scans the third party's own
   `/facade` dir through `Kernel::hostDirectory()`).
   `FacadeLoader::load()`/`getDefinitions()` are **warm-up/discovery only**:
   facades resolve via autoload + container, nothing consumes the returned
   map at runtime today (the core-side caller passing a package root instead
   of null is tracked as core/§P work in the root backlog).
8. **Domain services implement local `Domain/*/Contract/` interfaces** — host
   domain surface, intentionally not framework ports.
9. `symfony/event-dispatcher-contracts` (interfaces only) is the declared
   dependency — do not reintroduce the full `symfony/event-dispatcher`
   implementation.
10. **Product container caches must chain into the reset seam.** If the
    builder registered via `ContainerFactory::setBuilder()` delegates to a
    caching factory of its own, that factory must also call
    `ContainerFactory::registerResetCallback()` — otherwise
    `Kernel::shutdown()` + re-init hands back a stale container built for a
    previous kernel/router pair (symptom: default routes like
    `route_not_found` missing). Callbacks are keyed (idempotent) and survive
    `reset()`, like the builder itself.

## Composer scripts

- `composer check` — `check:style` → `check:rector` → `check:stan`
- `composer fix` / `composer fix:all` — auto-fix style + rector (`fix:all`
  re-runs style after rector)
- `composer test` / `composer test:cov` — PHPUnit (coverage via
  `XDEBUG_MODE=coverage`, text report)
- `composer lint:php82` — PHP 8.2 parse-level lint (`bin/lint-php82.sh`)
- Git hooks are registered by `post-install-cmd`/`post-update-cmd`
  (`core.hooksPath .githooks`); `commit-msg` enforces Conventional Commits.

## Releases

Managed by release-please from Conventional Commits merged to `main` (work
lands on `develop` first). **Never create git tags manually.** Versioning
follows the family-wide `1.x` policy (framework
[`API-STABILITY.md`](https://github.com/middag-io/middag-php-framework/blob/main/API-STABILITY.md)):
during `1.x` a minor may carry a breaking change, always explicitly marked
(`feat!` / `BREAKING CHANGE:` footer, surfaced in the CHANGELOG's breaking
section) and cut deliberately via a `Release-As` footer — never in a patch.
Strict semver (breaking only in majors) starts at `2.0`. A major release is
**never cut automatically**: it requires an explicit maintainer decision,
taken only when the break genuinely impacts Composer consumers; a release PR
proposing a major bump is not merged without that sign-off.
