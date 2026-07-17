---
ref: REF-MDL-018-01
adr: MDL-018
title: 'Delegation Pattern Detail & Rendering-Path Verification'
lang: en
---

# REF-MDL-018-01: Delegation Pattern Detail & Rendering-Path Verification

> Detail supporting [MDL-018](../decisions/MDL-018-moodle-navigation-integration.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-308`, no REF companion), with one confirmation against this adapter's real code.

## Migration in progress

`navigation_registry_interface` (introduced by the broader frontend architecture decision, [MDL-019](../decisions/MDL-019-frontend-moodle-integration.md) and the `ui`-lib decisions record) replaces `get_quick_access_links()` as the primary navigation-registration mechanism for extensions; extensions migrate progressively. The four `lib.php` callbacks in the ADR are independent of this migration and are not candidates for replacement by a native Moodle hook.

## Verified against real code (2026-07-17)

`src/Output/NavbarService.php` in this repository renders the navbar dropdown using Mustache/native Moodle HTML output — **not** Inertia — even though this same package also contains real, active Inertia infrastructure elsewhere (`src/Http/Inertia/InertiaSharedProps.php`, `src/Http/Inertia/MoodleInertiaBootstrap.php`) used by other product pages. `NavbarService`'s own docblock confirms the boundary discipline directly: *"Uses only moodle/support/ dependencies (deptrac: MoodleInternal layer). Extensibility via hooks is handled by the caller (lib.php) which is outside the framework boundary."* These are two separate, coexisting rendering paths by design: institutional navigation (Mustache, this ADR) versus product pages (Inertia, the frontend architecture decisions in the `ui` library). **This confirmation does not change the classification** — the callback-to-internal-mechanism delegation pattern is generic Moodle boundary behavior, independent of which rendering technology a given extension chooses internally.

## Delegation pattern, restated

1. Capability/auth check via `capability_interface`/`authentication_interface`.
2. Resolve navigation items via an extension or service.
3. Render via Moodle's navigation API (`navigation_node`, `pix_icon`, etc.).

No callback skips step 1 — this is the boundary's authorization guarantee for navigation entries specifically.
