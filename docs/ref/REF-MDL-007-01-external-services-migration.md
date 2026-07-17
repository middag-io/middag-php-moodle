---
ref: REF-MDL-007-01
adr: MDL-007
title: 'Legacy Migration Status & Mobile API Detail'
lang: en
---

# REF-MDL-007-01: Legacy Migration Status & Mobile API Detail

> Detail supporting [MDL-007](../decisions/MDL-007-external-services-mobile-api.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-306`, `ref-306-01`).

## Legacy pattern still in migration (active technical debt)

Old-style externals live at `classes/extensions/{slug}/legacy/classes/external.php`, declared manually in `legacy/db/services.php`, sometimes under an older namespace convention (`mtool_*`), outside the `build_statics` pipeline entirely. Documented migration status at time of reading: `helpdesk`, `trilha`, `studyplan` still **Legacy**; only `core` has migrated to **New-style**. This is live roadmap-relevant debt, not merely historical trivia.

## Migration steps, legacy to new-style

1. Move the external class out of `legacy/`.
2. Rename the namespace to the current convention.
3. Declare the function via `get_service_definitions()` using the typed `service` definition.
4. Remove `legacy/db/services.php`.
5. Regenerate `db/services.php` via the CLI.
6. **Preserve the public function name** `local_middag_{name}` so existing clients do not break.

## Mobile API

Including `MOODLE_OFFICIAL_MOBILE_SERVICE` in a service definition's `services` array exposes that function to Moodle's official mobile app.

## Anti-patterns

- Editing `db/services.php` by hand — the generation pipeline overwrites it.
- Business logic living inside the external class instead of delegating to a service/command.
- Adding a new external under `legacy/`.
- Forgetting `self::validate_parameters()` — a security vulnerability, not just a style issue.
- Using `type: 'read'` for an operation that mutates state — Moodle permits a `read`-typed call without a sesskey, so any mutation must be `write`.

## Scope note (evaluation requested during the LIB-KNOWLEDGE consolidation pass)

The API coverage registry ([MDL-005](../decisions/MDL-005-api-coverage-registry.md)) already lists "External Services / Web Services" as Tier A (A13), with its artifact living in the boundary's `definition/` subdirectory — i.e. the registry itself already treats this as boundary work (translating a native Moodle mechanism into a typed definition), not core business logic. The content of this ADR is entirely about the integration with Moodle's native External Functions API (`services.php`, `external_api`, `MOODLE_OFFICIAL_MOBILE_SERVICE`) — the `moodle` classification (rather than a hypothetical `core` one) is correct and self-consistent with the rest of this boundary documentation set.
