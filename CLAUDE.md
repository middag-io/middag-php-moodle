# CLAUDE.md — middag-io/moodle

## O que é este pacote

Adapter Moodle para o framework MIDDAG. Platform bindings, ACL layer, Inertia adapter, Moodle API wrappers (Support/), signal bridge, outbox store, e implementacoes dos adapter contracts do framework.

- **Depende de** `middag-io/framework`
- **Consumido por:** um plugin Moodle host (qualquer `local_*`/`mod_*`) que fornece a composition root própria

## Estado

Aligned — implementa todos os adapter contracts do framework. Adapter OSS standalone (Apache-2.0); sem sincronização a partir de plugin/produto.

## Estrutura

| Diretorio | Conteudo |
|-----------|----------|
| `src/Adapter/` | 7 platform bindings (Auth, Authorizer, Capability, Logger, Translator, TransactionManager, View) |
| `src/Config/` | MoodleConfigResolver (ConfigResolverInterface) |
| `src/Contract/` | 22 interfaces + Attributes/MoodleEvent, Core/MoodleExtensionInterface |
| `src/Definition/` | 10 Moodle definition builders (Capability, Cache, Hook, Service, etc.) |
| `src/Domain/` | ActivityFeed, Audit, Item, ItemRevision, Job repositories + services |
| `src/Dto/` | 21 DTOs (User, Course, Enrolment, Grade, Task, etc.) |
| `src/Entity/` | 18 Moodle entities (User, Course, Context, Role, etc.) |
| `src/Enum/` | 20 enums (ContextLevel, EnrolmentStatus, Visibility, etc.) |
| `src/Form/` | MformRenderer, MformFieldMapper, MformElementSpec |
| `src/Infrastructure/Bus/` | CommandBus, SignalDispatcher, OutboxStore, MoodleEventBridge, MoodleAsyncDispatcher, MoodleUserContext |
| `src/Infrastructure/` | Adapter (HTTP, Inertia, PDF), CDN, Logging, Persistence, QueryEngine |
| `src/Kernel/` | Kernel (singleton), ContainerFactory, MoodleBootstrap, Extension, Facade, Http, Loaders, Router |
| `src/Service/` | 16 services (Auth, Enrolment, File, Message, Platform, Scheduled, etc.) |
| `src/Settings/` | 26 Moodle admin setting types + SettingsResolver |
| `src/Support/` | 42 stateless Moodle API wrappers |
| `src/ValueObject/` | 5 value objects (Capability, FileReference, Frankenstyle, MoodleVersion, Sesskey) |

## Contracts bridge

| Framework Contract | Moodle Implementation |
|---|---|
| BootstrapInterface | MoodleBootstrap |
| ConfigResolverInterface | MoodleConfigResolver |
| DispatcherInterface | SignalDispatcher |
| OutboxStoreInterface | OutboxStore |
| AsyncCommandDispatcherInterface | MoodleAsyncDispatcher |
| UserContextResolverInterface | MoodleUserContext |
| CommandBusInterface | CommandBus |
| KernelInterface | Kernel |

## Composer scripts

- `composer check` — PHPStan
- `composer check:style` — PHP CS Fixer dry-run
- `composer check:rector` — Rector dry-run
- `composer fix` — Auto-fix style + rector
- `composer test` — PHPUnit
