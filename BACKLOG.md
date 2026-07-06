# BACKLOG — middag-io/moodle

**Blocked work only.** This file holds items that **cannot be done in the
normal development flow** — they need infrastructure or a decision that does not
exist yet. Anything doable in the flow is done, not parked here. Mirrors the
WordPress lib doctrine; relocated from the monorepo quality-gate tracker
(QG-MDL-03) so the tracker can be retired for coverage items.

Suite is green (1393 tests). The 9 adapter concerns covered under QG-MDL-03
(Database, Form, Hook, Logging, Output, Persistence, Privacy, Settings, Table)
are all **≥80% lines, most 100%**. The only uncovered lines in those concerns
are the unreachable defensive guards below.

---

## Blocked: unreachable defensive guard lines (not a missing assertion)

Each of these is a defensive branch that cannot be driven from a test because
the guarded condition can never hold given the code that precedes it. Covering
them is not an assertion we are missing — it is dead-by-design defensive code
kept for safety.

- **`Persistence/Query/SqlGenerator`** — the `switch` `default` arm. Every enum
  case is handled explicitly above it; the `default` is an exhaustiveness guard
  that no input can reach.
- **`Settings/SettingType`** — same shape: exhaustive `switch` over the setting
  types, `default` arm unreachable.
- **`Output/AbstractBlock`** — the `!class_exists(Widget)` guard. The Moodle
  `Widget` host class is always present when the block renders; the negative
  branch is a host-absence safety net.
- **`Output/NavbarService`** — the empty-items early `return`. The service is
  only invoked with a populated node set; the empty-input branch is defensive.

Unblocking any of these would require either a source refactor to remove the
guard (undesirable — it exists for safety) or a host-absence simulation harness
we do not have.
