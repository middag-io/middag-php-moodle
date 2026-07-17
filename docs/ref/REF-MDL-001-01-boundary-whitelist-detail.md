---
ref: REF-MDL-001-01
adr: MDL-001
title: 'Boundary Whitelist Detail'
lang: en
---

# REF-MDL-001-01: Boundary Whitelist Detail

> Detail supporting [MDL-001](../decisions/MDL-001-boundary-consolidation-whitelist.md). Reconstructed from the `moodle-local_middag` legacy vault (`ref-201-01`).

## Per-support-class whitelist highlights

The original REF lists, per support class, exactly which Moodle calls are permitted, the correct usage (always via facade in the legacy plugin, or via direct injection in this OSS adapter), and the matching anti-pattern. Highlights that go beyond the ADR text:

- `db_support` — `$DB->get_record/get_records/insert_record/update_record/delete_records/execute`.
- `cache_support` — `cache::make()`, `cache_store::MODE_*`.
- `context_support` — `context_system::instance()`, `context_course::instance()`, `context_module::instance()`.
- `custom_field_support` — `\core_customfield\handler`, `field_controller`, `data_controller`.
- `competency_support` — `\core_competency\api`, `competency`, `competency_framework`, `evidence`.
- `router_bridge_support` — bridge between the framework's Symfony-based router and the Moodle native router, active only on Moodle >= 5.1 (`proxyRequest()`); the anti-pattern is manipulating `r.php` manually. See [MDL-017](../decisions/MDL-017-routing-bridge-coexistence.md) and REF-MDL-017-01 for the current code-verified maturity split between the availability/discovery mechanism (real, active) and the proxy-forwarding method (still a documented stub).
- `di_bridge_support` — `\core\hook\di_configuration`, `\core\di`; the anti-pattern is any framework layer calling `\core\di::get()` directly.
- `task_support`, `time_support`, `lock_support`, `check_support` — cover the Task API, `\core\clock`/`usertime()`, `\core\lock\lock_factory`, and `\core\check\check` respectively.

The whitelist also documents that `moodle/adapter/*`-equivalent classes may delegate to `debugging()` and to the already-encapsulated auth/capability APIs, and that Moodle-definition classes access pure constants (`cache_store::MODE_*`, `RISK_*`, `CONTEXT_*`) for static generation.

## Controlled exceptions — justification and anti-pattern

For each controlled exception (`lib.php`, `db/install.php`/`upgrade.php`, `settings.php`, `external.php`, `classes/event|task|privacy/*`), the original REF pairs the plugin-API obligation with the concrete anti-pattern to avoid. Example: `settings.php` must be a thin orchestrator via `settings_resolver`, never defining settings directly — the obligation to exist outside the boundary does not license business logic inside it.

## Why this stays a REF, not the ADR

The whitelist is a living inventory (it grows as new Moodle subsystems are wrapped); the ADR captures the durable rule (whitelist model, controlled-exceptions list, three-tool enforcement). This document is where the per-API detail belongs so the ADR itself does not need a new revision every time a support class is added.
