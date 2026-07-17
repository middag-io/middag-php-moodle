---
id: MDL-014
title: 'MUC Cache Decorator over the Item Repository'
status: accepted
date: 2026-03-29
domains: [moodle, cache]
related: [MDL-013, MDL-003]
supersedes: []
superseded_by: null
lang: en
---

# MDL-014: MUC Cache Decorator over the Item Repository

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-303`, decided 2026-03-29, with `REF-303-01` as companion). Relocated in the source consolidation from a "core" grouping into this Moodle-boundary document, because the decorator is a Moodle-cache-specific mechanism, not generic domain/persistence code. This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Repeated reads of the same aggregate through the plain repository were paying the full database cost every time, with no caching layer in front of it that respected the repository boundary rather than bypassing it.

## Decision

A decorator over `item_repository` uses Moodle's MUC (Moodle Universal Cache), preserving the repository boundary — callers still depend on `item_repository_interface`, unaware a cache decorator sits in front of the database-backed implementation. The real decorator chain is `item_repository (DB)` -> `cached_item_repository (MUC)` -> `audited_item_repository (audit)`, aliased behind `item_repository_interface` via DI — audit sits **after** cache, so cache-hit reads never trigger an audit entry. Cache keys follow `item_{id}`, `meta_{id}`, `find_meta_{md5(key:value)}`, `type_{type}`. Invalidation is selective on `create()`/`update()`, but a **full pool purge** on `delete()`/`save_metadata()` — a deliberate tradeoff, since selectively invalidating composite queries reliably is difficult to get right. `cache_support`'s API (`get/set/delete/delete_many/get_many/set_many/purge/get_or_set`) treats cache unavailability as a normal case: it returns `false`/an empty array rather than throwing, because a cache backend is never assumed to always be available.

## Consequences

- Reads that hit the cache skip both the database and the audit trail entirely — a deliberate design, not an oversight, since audit sits downstream of cache in the decorator chain.
- The full-pool purge on delete/metadata-save is a blunt instrument that trades some cache-hit rate for not having to reason about which composite queries a given delete could have affected.
- Today only the `item` aggregate has a cache decorator — `job`/`audit`/`activity_feed` do not, by design, not because of an oversight.
- **The real implementation has one caching layer (MUC only), not the two layers (request-local in-memory plus MUC) this decision originally specified** — a confirmed contradiction between this ADR and the current code/REF, tracked as known technical debt rather than rewritten here; see REF-MDL-014-01 for the detail.

## Out of scope

- The full cache-key inventory, the ADR-vs-real-code contradiction detail, and the anti-pattern list — see REF-MDL-014-01.
- The transaction manager that shares the same adapter layer — see [MDL-013](./MDL-013-transaction-manager-aggregate-atomicity.md).

## Links

- [REF-MDL-014-01 — Cache Chain, Keys, Invalidation & Known Contradiction](../ref/REF-MDL-014-01-cache-decorator-detail.md)
- [MDL-013 — Transaction Manager & Aggregate Atomicity](./MDL-013-transaction-manager-aggregate-atomicity.md)
- [MDL-003 — Support Layer Pattern](./MDL-003-support-layer-pattern.md)
