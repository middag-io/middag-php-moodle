---
ref: REF-MDL-006-01
adr: MDL-006
title: 'DI Bridge Mechanics & Consumption Patterns'
lang: en
---

# REF-MDL-006-01: DI Bridge Mechanics & Consumption Patterns

> Detail supporting [MDL-006](../decisions/MDL-006-di-bridge-container-interoperability.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-207`, `ref-207-01`).

## Consumption by external code (detail only in the REF, not the ADR)

```php
// Via Moodle DI (recommended, plugins >= 4.4):
$middag = \core\di::get(\local_middag\middag::class);
$service = $middag->container()->get(SomePublicService::class);

// Auto-wiring in native Moodle controllers (>= 4.4):
class my_controller {
    public function __construct(private \local_middag\middag $middag) {}
}

// Fallback for Moodle < 4.4 (only inside the plugin itself):
if (di_bridge_support::is_available()) {
    $middag = \core\di::get(\local_middag\middag::class);
} else {
    $middag = middag::get_instance();
}
```

`get_extension_exports()` is reserved for future use — it returns an empty array today; when extensions need to export their own services, a concrete consumer must justify the addition first.

## Error isolation (operational detail, REF only)

Exceptions thrown inside `configure()` are caught and traced, never propagated into the Moodle DI boot sequence. This is not mentioned in the ADR text at all — it is the kind of detail that only matters when something goes wrong, which is exactly why it belongs in the REF rather than the terse decision record.

## Anti-patterns

- An external plugin resolving an `@internal` service via `$middag->container()->get()`.
- The framework resolving anything via `\core\di::get()` directly.
- Exporting a service with no concrete consumer.
- Ignoring `is_available()` before calling into Moodle DI — a fatal error on Moodle < 4.4.
- Auto-discovery of exports (the curated, manual `EXPORTS` list is deliberate, not an oversight to fix later).
