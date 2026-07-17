---
ref: REF-MDL-005-01
adr: MDL-005
title: 'Tier A Registry, Version Guards & Tier C'
lang: en
---

# REF-MDL-005-01: Tier A Registry, Version Guards & Tier C

> Detail supporting [MDL-005](../decisions/MDL-005-api-coverage-registry.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-205`, `ref-205-01`).

## Tier A — 45 APIs (A01–A45)

Coverage is essentially complete across Moodle's core areas: Access/Capability, Authentication, Backup/Restore, Cache, Config, Context, Course/Category/Cohort, DML, Enrolment, Events, External Services/Web Services, File, Form, Grade, Groups, Hooks (4.4+), HTML Writer, Logging, Message, Navigation, Output/Rendering, Page, Plugin, Privacy (GDPR), Role, Session, Settings (Admin), String/i18n, Task, Upgrade, URL, User, Version, Preference, Calendar, Lock, Custom Fields, Check, Notification, Time, Competency, Routing (5.1+), DI (PSR-11, 4.4+). All entries besides the four version-gated ones below (A01–A17, A19–A39, A41–A43) are available since Moodle 4.5, the framework's general minimum supported version.

## Explicit minimum-version requirements (conditional availability guards)

| API         | min_moodle | Guard mechanism                                                                                  | Behavior if unavailable                                                                                                                                                                                                                           |
|-------------|------------|--------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Hooks       | 4.4        | `min_moodle: '4.4'` on the hook definition                                                       | Hook not registered                                                                                                                                                                                                                               |
| Check       | 4.0        | `class_exists('core\check\check')`                                                               | Check definitions silently ignored                                                                                                                                                                                                                |
| Routing     | 5.1        | `class_exists('core\router\route_loader_interface')` via `router_bridge_support::is_available()` | Proxy inactive below 5.1; routing via MIDDAG entry points only. **Note: on Moodle >= 5.1 the availability/discovery half of the bridge is confirmed active in this repo's code — see MDL-017 and REF-MDL-017-01 for the precise maturity split.** |
| DI (PSR-11) | 4.4        | `version_support::supports('moodle_di_hook')`                                                    | Services not exposed to Moodle DI; framework isolated                                                                                                                                                                                             |

## `@api` / `@internal` classification by artifact (detail not literal in the ADR)

Explicit table from the original REF: `moodle/entity/*`, `moodle/contract/*`, `moodle/definition/*`, `moodle/enum/*`, `moodle/settings/*` are **`@api`**; `moodle/support/*` and `moodle/adapter/*` are **`@internal`** (consume via facade / inject via DI, never import directly). This table is the operational detail that closes the gap between [MDL-004](../decisions/MDL-004-entities-dtos-as-public-api.md) (entity/DTO are Group A) and [MDL-001](../decisions/MDL-001-boundary-consolidation-whitelist.md)/[MDL-002](../decisions/MDL-002-boundary-internal-organization.md) (organization) — it resolves the practical ambiguity of "is an adapter public API?" with a clear no.

## Tier C — 3 out-of-scope APIs

| #   | API                    | Justification                                                                                                                     |
|-----|------------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| C01 | DDL (Data Definition)  | Persistence is governed by persistence families and the repository boundary; tables live in `install.xml`                         |
| C02 | Deprecation            | The framework has its own deprecation model, independent of Moodle's `debugging()`                                                |
| C03 | Search (Global Search) | The framework has its own query engine; global-search indexing is the extension's responsibility via `\core_search\base` directly |

## Findings not captured in the original index

Concrete, dated counts that only surface reading the full ADR/REF pair: 40 legacy support classes (38 original + 2 bridges, see [MDL-003](../decisions/MDL-003-support-layer-pattern.md)/REF-MDL-003-01 for the current 45-class count in this adapter), 45 Tier A APIs plus 3 Tier C, and the `@api`/`@internal` classification table above — none of these appear in the ADR body itself, only in the REF.
