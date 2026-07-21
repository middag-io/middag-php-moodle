---
id: MDL-010
title: 'Backup/Restore Bypasses Domain Rules by Design, via an Import Repository'
status: accepted
date: 2026-03-29
lang: en
domains: [moodle, backup]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-001, MDL-009]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/import-repository-contract]
decision: 'Moodle backup/restore persists restored data through a specialized import repository that performs direct, unvalidated insertion — preserving source timestamps/GUIDs and remapping IDs via Moodle''s own restore machinery — deliberately bypassing normal domain-creation rules, restricted to this restore boundary.'
---

# MDL-010: Backup/Restore Bypasses Domain Rules by Design, via an Import Repository

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-310`, decided 2026-03-29, with `ref-310-01` as companion). This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle backup/restore requires platform-specific adapters. Restoring a course is reconstituting historical state exactly as it existed on the source site — not a new business event — so running restored data through the normal domain-creation path would distort timestamps, diffs, and every other behavior that assumes "created just now."

## Considered Options

1. **Persist restored data through the normal domain-creation path** (e.g. `item_repository::create()`). Rejected — it generates new timestamps, domain events, and a new GUID on every call, which is exactly wrong for reproducing historical state faithfully.
2. **Validate restored data against today's domain rules** before persisting. Rejected — historical data legitimately may not satisfy current validation; the bypass is deliberate, not an oversight to "fix" later by adding validation back in.
3. **Preserve the source site's primary keys (`id`) on insert.** Rejected — Moodle Restore always remaps IDs on the target site, so keeping the original `id` would either collide with auto-increment or simply be meaningless; ID remapping via `$this->get_mappingid('course'|'user', $old_id)` was adopted instead.

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
- Asynchronous jobs/work restored this way may need their own governance after import — tracked separately in the framework's own decisions record, out of scope for this boundary lib.
- The Privacy API faces a structurally similar "platform contract meets repository boundary" problem — see [MDL-009](./MDL-009-privacy-provider-repository-delegation.md), which delegates GDPR read/export/anonymize/delete to its own specialized repository for the same reason this one delegates restore.
- The import repository itself still lives inside the physical Moodle boundary established by [MDL-001](./MDL-001-boundary-consolidation-whitelist.md) — bypassing domain rules is not the same as bypassing the boundary; the raw insert still goes through `db_support`, not ad-hoc `$DB` calls from outside the adapter.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Restored data must never go through the normal domain-creation flow (e.g. `item_repository::create()`) | Code review; no automated check found | planned |
| Import repository strips the `id` field and inserts directly via `db_support::insert_record()`, with no domain events, entity hydration, or validation | Code review; no automated check found | planned |
| `timecreated`, `timemodified`, `guid` are preserved exactly from the `.mbz` file; ID remapping goes through `get_mappingid()`, never a hand-rolled lookup | Code review; no automated check found | planned |
| Full contract detail, field-level design rationale, and the restore anti-pattern list | [Import Repository Contract & Operational Detail](https://docs.middag.dev/framework/reference/adapters/moodle/import-repository-contract) | coded |
