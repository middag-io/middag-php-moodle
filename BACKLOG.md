# BACKLOG — middag-io/moodle

**Blocked work only.** This file holds items that **cannot be done in the
normal development flow** — they need infrastructure or a decision that does not
exist yet. Anything doable in the flow is done, not parked here. Mirrors the
WordPress lib doctrine; relocated from the monorepo quality-gate tracker
(QG-MDL-03) so the tracker can be retired for coverage items.

Suite is green (2110 tests). The 9 QG-MDL-03 adapter concerns (Database, Form,
Hook, Logging, Output, Persistence, Privacy, Settings, Table) and all 45
`Support/` wrappers are now at **≥80% lines, most 100%**. The only uncovered
lines are the unreachable/dead defensive guards below.

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

## Blocked: unreachable guard lines in the `Support/` wrappers

Follow-up coverage pass (2026-07-06) took every `Support/` wrapper to ≥80%
lines, most to 100%. The classes below sit between 80% and 100%; each uncovered
line is a defensive/dead branch that cannot be driven without a real Moodle
runtime (or is dead-by-construction):

- **`Support/RouterBridgeSupport`** (86.67%) — the `if (!class_exists(Kernel::class))`
  503 branch in `proxyRequest()`. `Middag\Moodle\Kernel\Kernel` is always
  Composer-autoloadable, so the guard is never true.
- **`Support/ContextSupport`** (90%) — three Moodle version-compat fallbacks
  (`instanceById` legacy path, `classFor` unknown-type + legacy fallbacks). The
  namespaced 4.2+ classes always exist under test, so the legacy arms are dead.
- **`Support/LangSupport`** (80%) — the `catch (Exception)` arms of `getString()`
  / `getStringOrIdentifier()`. They only wrap `self::get()` / `self::stringExists()`,
  which already catch internally and never re-throw, so the outer catch is dead.
- **`Support/UrlSupport`** (90%) — the `get()` object-input branch. `core\url`'s
  ctor is typed `string`, so a non-string cannot be exercised in strict mode.
- **`Support/CohortSupport`** (95.65%) — the `else` in `getCohortsWithTotal`.
  `getCohorts()` is typed `: array`, so `is_array()` is always true.
- **`Support/CompletionSupport`** (99.48%) — the `return null` after
  `isEnabledCourse()` already returned true (which required a non-null
  `completion_info`); the second lookup returns the same valid object.
- **`Support/CourseSupport`** (95.12%) — the `catch` in `getCourseContext`; its
  only `try` statement is `context_course::instance()`, whose shared stub never
  throws.
- **`Support/DiBridgeSupport`** (91.67%) — the extension-exports `foreach` body
  in `configure()`. `getExtensionExports()` is hardcoded to `[]` ("reserved for
  future use"), so its loop body never executes; the product-exports loop and
  the catch are both covered.

Unblocking these would need source refactors (remove the guards) or a
Moodle-runtime harness — out of scope for a coverage pass.
