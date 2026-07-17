---
id: MDL-010
title: 'Backup/Restore Bypasses Domain Rules by Design, via an Import Repository'
status: accepted
date: 2026-03-29
domains: [moodle, backup]
related: [MDL-001, MDL-009]
supersedes: []
superseded_by: null
lang: en
---

# MDL-010: Backup/Restore Bypasses Domain Rules by Design, via an Import Repository

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-310`, decided 2026-03-29, with `ref-310-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle backup/restore requires platform-specific adapters. Restoring a course is reconstituting historical state exactly as it existed on the source site — not a new business event — so running restored data through the normal domain-creation path would distort timestamps, diffs, and every other behavior that assumes "created just now."

## Decision

**Restore is faithful import of historical state, not the creation of new business intent** — the bypass of normal domain rules at this boundary is deliberate and restricted to it. Moodle-side adapters delegate to a specialized **import repository** that performs persistent rehydration without going through the domain-oriented creation flow. The central handler's scope is limited to structures persisted by the framework itself (EAV, core MIDDAG tables). Extensibility: a plugin or integration with its own storage participates via `backup_steps_provider_interface` (Group A); integrations that only use the framework's central persistence need no provider of their own.

```php
interface backup_steps_provider_interface {
    public function get_backup_steps(): array;   // backup_step instances
    public function get_restore_steps(): array;  // restore_step instances
}

interface import_repository_interface extends repository_interface {
    public function import_item(array $data): int;      // raw item insert
    public function import_itemmeta(array $data): int;   // raw metadata insert
}
```

The import repository's design is deliberate: it strips the `id` field before insertion (Moodle Restore always remaps IDs), inserts directly via `db_support::insert_record()` with **no** domain events, entity hydration, or validation, and preserves `timecreated`, `timemodified`, and `guid` exactly as they appear in the `.mbz` file. ID remapping happens via `$this->get_mappingid('course'|'user', $old_id)` inside the extension's own `restore_step`.

## Consequences

- Restored data reproduces the source site's history faithfully — timestamps, GUIDs, and identifiers are not silently regenerated.
- The deliberate bypass of domain validation means restore can accept historical data that would fail today's rules — by design, not by oversight; this must never be "fixed" by adding validation back in.
- Asynchronous jobs/work restored this way may need their own governance after import (tracked separately, out of scope here).

## Out of scope

- The full contract detail, the import repository's field-level design rationale, and the restore anti-pattern list — see REF-MDL-010-01.
- The Privacy API's structurally similar "platform contract meets repository boundary" problem — see [MDL-009](./MDL-009-privacy-provider-repository-delegation.md).

## Links

- [REF-MDL-010-01 — Import Repository Contract & Operational Detail](../ref/REF-MDL-010-01-backup-restore-detail.md)
- [MDL-009 — Privacy Provider Delegates to a Specialized Repository](./MDL-009-privacy-provider-repository-delegation.md)
- [MDL-001 — Consolidate the Moodle Boundary Behind a Physical Whitelist](./MDL-001-boundary-consolidation-whitelist.md)
