---
ref: REF-MDL-014-01
adr: MDL-014
title: 'Cache Chain, Keys, Invalidation & Known Contradiction'
lang: en
---

# REF-MDL-014-01: Cache Chain, Keys, Invalidation & Known Contradiction

> Detail supporting [MDL-014](../decisions/MDL-014-muc-cache-decorator.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-303`, `REF-303-01`).

## Known contradiction — ADR text vs. real implementation (confirmed, not re-litigated here)

The original ADR-303 describes **two** caching layers: a request-level in-memory layer plus MUC persisted across requests, with active invalidation on write and TTL as a safety net. The companion REF-303-01 documents only **one** layer — MUC (`MODE_APPLICATION`, `simplekeys: true`) — with no in-memory request cache, and explicitly lists this in its own "Known limitations" section as a future optimization ("via `get_many()`") that does not exist yet. **The REF is correct; the ADR is outdated.** This is one of three ADR-vs-real-code contradictions confirmed during the legacy vault reading and escalated to the T7 remediation flow — it is recorded here as known debt, not resolved or rewritten in this pass.

## Real decorator chain

`item_repository (DB)` -> `cached_item_repository (MUC)` -> `audited_item_repository (audit)` -> aliased behind `item_repository_interface` via DI. Audit sits **after** cache — cache-hit reads never generate an audit entry.

## Real cache keys

`item_{id}`, `meta_{id}`, `find_meta_{md5(key:value)}`, `type_{type}`.

## Invalidation

Selective on `create()`/`update()`. **Full pool purge** on `delete()` and `save_metadata()` — a deliberate tradeoff: tracking exactly which composite queries a delete could invalidate is hard to get right reliably, so the decorator purges the whole pool instead.

## `cache_support` API

`get`, `set`, `delete`, `delete_many`, `get_many`, `set_many`, `purge`, `get_or_set`. Error policy: return `false`/an empty array, never throw — a cache backend is never assumed to be reliably available.

## Known limitations (REF, §9)

- No in-memory request-level cache (contradicts the ADR's original two-layer design, as above).
- Only the `item` aggregate has a cache decorator today — `job`/`audit`/`activity_feed` do not, by design.
- No cache-key versioning.

## Anti-patterns

- Bypassing the facade and using the concrete `item_repository` directly.
- Ad hoc manual caching inside an extension.
- Hardcoding a per-key TTL — not supported; TTL is owned by the MUC backend configuration.
