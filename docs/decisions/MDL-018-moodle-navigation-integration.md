---
id: MDL-018
title: 'Navigation Integration: Thin lib.php Callbacks Delegating to Internal Mechanisms'
status: accepted
date: 2026-04-04
lang: en
domains: [moodle, navigation]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-017, MDL-019]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/navigation-integration-detail]
decision: 'Moodle navigation integration happens exclusively through four thin lib.php callbacks that delegate to internal mechanisms (capability check, then item resolution, then rendering via Moodle''s navigation API) — permanent scaffolding, independent of the ongoing migration to navigation_registry_interface as the primary extension-registration path.'
---

# MDL-018: Navigation Integration — Thin `lib.php` Callbacks Delegating to Internal Mechanisms

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-308`, decided 2026-04-04, no REF companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle exposes navigation extension points only through procedural callbacks in `lib.php`. Left unconstrained, those callbacks are exactly the kind of place navigation logic tends to accumulate directly, in violation of the boundary rule that `lib.php` may only act as a thin adapter/callback ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md)).

## Considered Options

1. **Let the `lib.php` navigation callbacks carry business logic directly** (capability checks, item resolution, rendering, all inline). Rejected — this is precisely the accumulation pattern the boundary rule in [MDL-001](./MDL-001-boundary-consolidation-whitelist.md) exists to prevent; `lib.php` may only act as a thin adapter/callback.
2. **Retire the four `lib.php` navigation callbacks as `navigation_registry_interface` adoption grows**, treating them as legacy scaffolding to be phased out alongside `get_quick_access_links()`. Rejected — Moodle's plugin API provides no native-hook alternative for global navigation, course navigation, profile navigation, or the navbar dropdown; these four entry points are structurally mandatory regardless of how extensions register their own items.

## Decision

Navigation integration happens through thin `lib.php` callbacks that delegate to internal mechanisms rather than carrying logic themselves:

| Callback                                  | Moodle hook point | Use                            |
|-------------------------------------------|-------------------|--------------------------------|
| `local_middag_extend_navigation()`        | Global navigation | MIDDAG main menu               |
| `local_middag_extend_navigation_course()` | Course navigation | Per-course items                |
| `local_middag_myprofile_navigation()`     | User profile      | MIDDAG section on the profile  |
| `local_middag_render_navbar_output()`     | Navbar            | MIDDAG dropdown on the top bar |

Each callback follows the same delegation pattern: (1) check capability/auth through `capability_interface`/`authentication_interface`; (2) resolve navigation items via an extension or service; (3) render through Moodle's navigation API (`navigation_node`, `pix_icon`, etc.). A separate hook, `local_middag_extend_block_middag_content()`, lets extensions contribute content into the MIDDAG block. A registry-based mechanism (`navigation_registry_interface`, from the broader frontend architecture decision) is progressively replacing `get_quick_access_links()` as the primary navigation-registration path for extensions — but the four `lib.php` callbacks above stay regardless of that migration, since Moodle's plugin API gives no native-hook alternative for them.

## Consequences

- Navigation logic stays inside testable internal mechanisms; `lib.php` never grows business logic just because Moodle's plugin API forces the entry point to live there.
- The four callbacks are permanent scaffolding — they do not go away as `navigation_registry_interface` adoption grows, because Moodle offers no alternative hook for them.
- Capability/auth checking is consistently the first step in every callback, so navigation entries can never bypass the authorization layer by construction.
- The routing bridge ([MDL-017](./MDL-017-routing-bridge-coexistence.md)) faces a structurally similar dual-path coexistence: a framework-owned primary mechanism (there, the framework's own router; here, `navigation_registry_interface`) permanently coexisting with Moodle-mandated entry points that have no native-hook alternative.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Each of the four `lib.php` navigation callbacks checks capability/auth via `capability_interface`/`authentication_interface` before resolving or rendering anything | Code review; no automated check found | planned |
| Navigation callbacks only delegate (resolve via extension/service, render via Moodle's navigation API) — they never carry business logic directly | Code review; no automated check found (shares the boundary rule enforced for [MDL-001](./MDL-001-boundary-consolidation-whitelist.md)) | planned |
| The four callbacks remain permanent regardless of `navigation_registry_interface` adoption elsewhere | Code review; no automated check found | planned |
| Delegation-pattern detail, `navigation_registry_interface` migration status, and the code-verified rendering-path split (Mustache vs. Inertia) per navigation surface | [Navigation Delegation & Rendering-Path Detail](https://docs.middag.dev/framework/reference/adapters/moodle/navigation-integration-detail) | coded |
