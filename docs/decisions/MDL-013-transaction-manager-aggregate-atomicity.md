---
id: MDL-013
title: 'Transaction Manager: One Aggregate per Transaction, No Cross-Aggregate Atomicity by Default'
status: accepted
date: 2026-03-29
domains: [moodle, persistence]
related: [MDL-014]
supersedes: []
superseded_by: null
lang: en
---

# MDL-013: Transaction Manager — One Aggregate per Transaction, No Cross-Aggregate Atomicity by Default

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-302`, decided 2026-03-29, no REF companion). Realocated in the source consolidation from a "core" grouping into this Moodle-boundary document, because the mechanism lives structurally in the boundary's adapter layer, not in domain/aggregate/persistence code. This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle's transaction API and the framework's own expected transaction flow do not line up one-to-one, and rollback semantics need adapting between the two. Left unconstrained, multi-aggregate transactions would let a single database transaction span concerns that should stay decoupled, coupling their failure modes together.

## Decision

A dedicated transaction manager in the Moodle boundary's adapter layer encapsulates rollback and adapts between Moodle's transaction API and the framework's expected flow. **Rule: multi-aggregate transactions are forbidden by default** — each aggregate is its own atomic unit; coordination between aggregates uses signals/events with eventual consistency. The only exception is when Moodle's own platform semantics require atomicity across the boundary, and any such exception must be documented explicitly in the code with its justification.

## Consequences

- Each aggregate's failure mode stays local — a rollback in one aggregate's transaction never silently drags another aggregate's data along with it.
- Cross-aggregate consistency becomes eventual (via signals/events) rather than transactional, which is a real tradeoff, not free — accepted because it matches how the aggregates are meant to be decoupled in the first place.
- The rare platform-mandated exception carries a documentation obligation, so it cannot silently become the default path over time.

## Out of scope

- The cache decorator that also lives in the adapter layer and interacts with the same repository boundary — see [MDL-014](./MDL-014-muc-cache-decorator.md).
- Audit/transaction-strength policy for higher-assurance operations — belongs to the framework's own persistence/audit decisions record, not this boundary lib.

## Links

- [MDL-014 — MUC Cache Decorator](./MDL-014-muc-cache-decorator.md)
- [CLAUDE.md](../../CLAUDE.md) — current implementation map (`src/Database/TransactionManager`)
