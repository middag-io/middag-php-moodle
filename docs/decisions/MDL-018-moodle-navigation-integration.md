---
id: MDL-018
title: 'Navigation Integration: Thin lib.php Callbacks Delegating to Internal Mechanisms'
status: accepted
date: 2026-04-04
domains: [moodle, navigation]
related: [MDL-017, MDL-019]
supersedes: []
superseded_by: null
lang: en
---

# MDL-018: Navigation Integration — Thin `lib.php` Callbacks Delegating to Internal Mechanisms

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-308`, decided 2026-04-04, no REF companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle exposes navigation extension points only through procedural callbacks in `lib.php`. Left unconstrained, those callbacks are exactly the kind of place navigation logic tends to accumulate directly, in violation of the boundary rule that `lib.php` may only act as a thin adapter/callback ([MDL-001](./MDL-001-boundary-consolidation-whitelist.md)).

## Decision

Navigation integration happens through thin `lib.php` callbacks that delegate to internal mechanisms rather than carrying logic themselves:

| Callback                                  | Moodle hook point | Use                            |
|-------------------------------------------|-------------------|--------------------------------|
| `local_middag_extend_navigation()`        | Global navigation | MIDDAG main menu               |
| `local_middag_extend_navigation_course()` | Course navigation | Per-course items               |
| `local_middag_myprofile_navigation()`     | User profile      | MIDDAG section on the profile  |
| `local_middag_render_navbar_output()`     | Navbar            | MIDDAG dropdown on the top bar |

Each callback follows the same delegation pattern: (1) check capability/auth through `capability_interface`/`authentication_interface`; (2) resolve navigation items via an extension or service; (3) render through Moodle's navigation API (`navigation_node`, `pix_icon`, etc.). A separate hook, `local_middag_extend_block_middag_content()`, lets extensions contribute content into the MIDDAG block. A registry-based mechanism (`navigation_registry_interface`, from the broader frontend architecture decision) is progressively replacing `get_quick_access_links()` as the primary navigation-registration path for extensions — but the four `lib.php` callbacks above stay regardless of that migration, since Moodle's plugin API gives no native-hook alternative for them.

## Consequences

- Navigation logic stays inside testable internal mechanisms; `lib.php` never grows business logic just because Moodle's plugin API forces the entry point to live there.
- The four callbacks are permanent scaffolding — they do not go away as `navigation_registry_interface` adoption grows, because Moodle offers no alternative hook for them.
- Capability/auth checking is consistently the first step in every callback, so navigation entries can never bypass the authorization layer by construction.

## Out of scope

- The `navigation_registry_interface` migration mechanism itself and the rendering-technology detail per navigation surface — see REF-MDL-018-01.
- The routing bridge's own coexistence model, a structurally similar dual-path situation — see [MDL-017](./MDL-017-routing-bridge-coexistence.md).

## Links

- [REF-MDL-018-01 — Delegation Pattern Detail & Rendering-Path Verification](../ref/REF-MDL-018-01-navigation-integration-detail.md)
- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
- [MDL-017 — Routing Bridge Coexistence with the Native Moodle Router](./MDL-017-routing-bridge-coexistence.md)
- [MDL-019 — Frontend Moodle Integration: Theme Bridge & AMD Build](./MDL-019-frontend-moodle-integration.md)
