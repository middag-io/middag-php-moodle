---
id: MDL-013
title: 'Transaction Manager: One Aggregate per Transaction, No Cross-Aggregate Atomicity by Default'
status: accepted
date: 2026-03-29
lang: en
domains: [moodle, persistence]
deciders: ['PENDING — original decider not recorded during the legacy-vault reconstruction; confirm with Michael Meneses before ratifying']
related: [MDL-014]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
decision: 'A dedicated transaction manager adapts Moodle''s transaction API to the framework''s expected flow, and multi-aggregate transactions are forbidden by default — each aggregate commits atomically on its own, cross-aggregate coordination goes through signals/events with eventual consistency, and the only escape hatch is a narrow, code-documented exception for cases where Moodle''s own platform semantics force atomicity across the boundary.'
---

# MDL-013: Transaction Manager — One Aggregate per Transaction, No Cross-Aggregate Atomicity by Default

> [!NOTE]
> **Provenance.** Reconstructed from the `moodle-local_middag` legacy vault (`ADR-302`, decided 2026-03-29, no REF companion). Realocated in the source consolidation from a "core" grouping into this Moodle-boundary document, because the mechanism lives structurally in the boundary's adapter layer, not in domain/aggregate/persistence code. This is an archaeology pass, not a new decision — dates and rationale are historical.

## Context

Moodle's transaction API and the framework's own expected transaction flow do not line up one-to-one, and rollback semantics need adapting between the two. Left unconstrained, multi-aggregate transactions would let a single database transaction span concerns that should stay decoupled, coupling their failure modes together.

## Considered Options

1. **Allow unconstrained multi-aggregate transactions**, letting a single database transaction span multiple aggregates' concerns. Rejected — this is precisely the state described in Context: it would couple the failure modes of concerns that are meant to stay decoupled from each other.
2. **Forbid cross-aggregate atomicity with no exceptions whatsoever**, even where Moodle's own platform semantics force atomicity across the boundary. Rejected — Moodle's plugin API can mandate such cases in practice, so a blanket "no exceptions" rule would be unworkable against platform behavior this project doesn't control. A narrow, obligation-bound exception (documented in code with its justification) was adopted instead.

## Decision

A dedicated transaction manager in the Moodle boundary's adapter layer encapsulates rollback and adapts between Moodle's transaction API and the framework's expected flow. **Rule: multi-aggregate transactions are forbidden by default** — each aggregate is its own atomic unit; coordination between aggregates uses signals/events with eventual consistency. The only exception is when Moodle's own platform semantics require atomicity across the boundary, and any such exception must be documented explicitly in the code with its justification.

## Consequences

- Each aggregate's failure mode stays local — a rollback in one aggregate's transaction never silently drags another aggregate's data along with it.
- Cross-aggregate consistency becomes eventual (via signals/events) rather than transactional, which is a real tradeoff, not free — accepted because it matches how the aggregates are meant to be decoupled in the first place.
- The rare platform-mandated exception carries a documentation obligation, so it cannot silently become the default path over time.
- The cache decorator that also lives in the adapter layer and interacts with the same repository boundary is a separate, related decision — see [MDL-014](./MDL-014-muc-cache-decorator.md).
- Audit/transaction-strength policy for higher-assurance operations belongs to the framework's own persistence/audit decisions record, not this boundary lib.
- The current implementation lives at `src/Database/TransactionManager` (see the repo's `CLAUDE.md` for the up-to-date map).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Multi-aggregate transactions are forbidden by default — each `executeAtomic`/`executeGraceful` call touches one aggregate's own atomic unit | No automated check found — would require a custom static-analysis rule inspecting call sites for cross-aggregate repository usage inside the callable | planned |
| Cross-aggregate coordination goes through signals/events with eventual consistency, not a shared transaction | No automated check found | planned |
| Any exception where Moodle's platform semantics force cross-boundary atomicity must be documented explicitly in code with its justification | Code review; no automated check found | planned |
