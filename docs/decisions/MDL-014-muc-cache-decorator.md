---
id: MDL-014
title: 'MUC Cache Decorator over the Item Repository'
status: accepted
date: 2026-03-29
lang: en
domains: [moodle, cache]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-013, MDL-003]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: [framework/reference/adapters/moodle/cache-decorator]
decision: 'A decorator (`cached_item_repository`) wraps `item_repository` with Moodle''s MUC, aliased behind `item_repository_interface` via DI in the chain `item_repository (DB) -> cached_item_repository (MUC) -> audited_item_repository (audit)`, with selective invalidation on create/update and a full pool purge on delete/save_metadata.'
---

# MDL-014: MUC Cache Decorator over the Item Repository

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-303`, decided 2026-03-29, with `REF-303-01` as companion). Relocated in the source consolidation from a "core" grouping into this Moodle-boundary document, because the decorator is a Moodle-cache-specific mechanism, not generic domain/persistence code. This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Repeated reads of the same aggregate through the plain repository were paying the full database cost every time, with no caching layer in front of it that respected the repository boundary rather than bypassing it.

## Considered Options

1. **Bypass the repository boundary with ad hoc caching at call sites** instead of a decorator. Rejected — it would leave callers responsible for cache correctness individually, defeating the point of hiding the cache behind `item_repository_interface`.
2. **Expose the cache decorator as its own directly-injected type**, rather than aliasing it behind `item_repository_interface`. Rejected — callers must stay unaware a cache sits in front of the database-backed implementation; depending on the concrete decorator would leak that detail.
3. **Order audit before cache** (audit wraps cache), so every read is audited regardless of cache hit. Rejected in favor of `DB -> cache -> audit` — cache-hit reads never generate an audit entry, a deliberate ordering, not an oversight.
4. **Selectively invalidate composite queries** (`find_meta_*`, `type_*`) on `delete()`/`save_metadata()` instead of a full pool purge. Rejected — reliably tracking which composite queries a given delete could affect is difficult to get right; a full purge trades some cache-hit rate for not having to solve that problem.
5. **Throw an exception when the cache backend is unavailable.** Rejected — a cache backend is never assumed to always be available, so `cache_support`'s API returns `false`/an empty array instead.

## Decision

A decorator over `item_repository` uses Moodle's MUC (Moodle Universal Cache), preserving the repository boundary — callers still depend on `item_repository_interface`, unaware a cache decorator sits in front of the database-backed implementation. The real decorator chain is `item_repository (DB)` -> `cached_item_repository (MUC)` -> `audited_item_repository (audit)`, aliased behind `item_repository_interface` via DI — audit sits **after** cache, so cache-hit reads never trigger an audit entry. Cache keys follow `item_{id}`, `meta_{id}`, `find_meta_{md5(key:value)}`, `type_{type}`. Invalidation is selective on `create()`/`update()`, but a **full pool purge** on `delete()`/`save_metadata()`. `cache_support`'s API (`get/set/delete/delete_many/get_many/set_many/purge/get_or_set`) treats cache unavailability as a normal case: it returns `false`/an empty array rather than throwing, because a cache backend is never assumed to always be available.

## Consequences

- Reads that hit the cache skip both the database and the audit trail entirely — a deliberate design, not an oversight, since audit sits downstream of cache in the decorator chain.
- The full-pool purge on delete/metadata-save is a blunt instrument that trades some cache-hit rate for not having to reason about which composite queries a given delete could have affected.
- Today only the `item` aggregate has a cache decorator — `job`/`audit`/`activity_feed` do not, by design, not because of an oversight.
- **Known contradiction, tracked as debt, not rewritten here:** the real implementation has one caching layer (MUC only), not the two layers (request-local in-memory plus MUC) this decision originally specified — see the Enforcement row below for the full detail.
- The transaction manager that shares the same adapter layer is a separate decision — see [MDL-013](./MDL-013-transaction-manager-aggregate-atomicity.md). The support-layer pattern this decorator builds on is documented in [MDL-003](./MDL-003-support-layer-pattern.md).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Decorator chain stays `item_repository (DB)` -> `cached_item_repository (MUC)` -> `audited_item_repository (audit)`, aliased behind `item_repository_interface` via DI | Code review; no automated check found | planned |
| Cache keys follow `item_{id}`, `meta_{id}`, `find_meta_{md5(key:value)}`, `type_{type}` | Code review; no automated check found | planned |
| Invalidation is selective on `create()`/`update()`; full pool purge on `delete()`/`save_metadata()` | Code review; no automated check found | planned |
| `cache_support` never throws on cache unavailability — returns `false`/an empty array | Code review; no automated check found | planned |
| Full cache-key inventory, decorator-chain detail, invalidation rationale, and the known ADR-vs-code contradiction | [Cache Decorator — Chain, Keys & Invalidation](https://docs.middag.dev/framework/reference/adapters/moodle/cache-decorator) | coded |
