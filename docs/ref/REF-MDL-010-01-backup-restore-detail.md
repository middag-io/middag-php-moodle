---
ref: REF-MDL-010-01
adr: MDL-010
title: 'Import Repository Contract & Operational Detail'
lang: en
---

# REF-MDL-010-01: Import Repository Contract & Operational Detail

> Detail supporting [MDL-010](../decisions/MDL-010-backup-restore-import-repository.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-310`, `ref-310-01`).

## Tables involved

Core EAV tables: `middag_items`, `middag_itemmeta`.

## Design rationale (more detailed in the REF than the ADR)

- The `id` field is stripped before insertion — Moodle Restore always remaps IDs on the target site, so preserving the source `id` would collide with auto-increment or simply be meaningless.
- Insertion goes directly through `db_support::insert_record()` — no domain events, no entity hydration, no validation. This is the deliberate bypass the ADR calls out, spelled out here as an implementation-level rule so it cannot be "cleaned up" by someone unaware of the rationale.
- `timecreated`, `timemodified`, and `guid` are preserved exactly as read from the `.mbz` file.
- ID remapping uses `$this->get_mappingid('course'|'user', $old_id)` inside the extension's `restore_step`.

## Anti-patterns

- Using `item_repository::create()` during restore — it generates new timestamps/events/GUID, which is exactly wrong for historical import.
- Not remapping IDs — the source site's IDs do not exist on the destination.
- Inserting with the `id` field present — an auto-increment collision risk; the import repository already strips it, so this anti-pattern is really "bypassing the import repository and inserting directly."
- Validating data during restore — historical data may not satisfy today's domain rules, and the bypass is deliberate, not a bug to fix.

## Documented downstream consequence

Asynchronous jobs restored this way may require their own governance pass after import (tracked separately in the framework's own decisions record — out of scope for this boundary lib).
