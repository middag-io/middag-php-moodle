---
ref: REF-MDL-012-01
adr: MDL-012
title: 'Config Key Inventory & DSL Coverage'
lang: en
---

# REF-MDL-012-01: Config Key Inventory & DSL Coverage

> Detail supporting [MDL-012](../decisions/MDL-012-config-schema-settings-support.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-312`, `ref-312-01`), with one confirmed update against this adapter's real code.

## Legacy config-key inventory (product-level, `local_middag`)

10 of 12 product extensions had a `{slug}_config` enum at the time of the original reading: `core`, `ecommerce`, `videolibrary`, `automessage`, `bigquery`, `opengraph`, `cleaner`, `sentry`, `coursegroup`, `helpdesk`. Totals: **60 keys with storage, 3 storedfile, 1 multiselect, 6 lazy**. `core` alone has 15 keys (the largest), including `authprofilefield` (lazy, resolved via `user_support::get_options_for_user_fields()`) and `authsecretkey` (password type). `bigquery` has the largest concentration of lazy options (4, related to retention/mode). This inventory belongs to the consumer product (`local_middag`'s own extensions), not to this OSS adapter — it is reproduced here as the historical scale the mechanism was built to support.

## DSL type coverage — confirmed current state in this adapter (correction to the legacy gap)

The legacy `ref-312-01` reported only **8 of 22** `setting_type` variants with an implemented DSL class (`text`, `checkbox`, `select`, `password`, `textarea`, `heading`, `description`, `link`), with 14 reserved-but-unimplemented (`autocomplete`, `encrypted_password`, `htmleditor`, `colourpicker`, `duration`, `time`, `multicheckbox`, `multiselect`, `storedfile`, `filepath`, `directory`, `executable`, `iplist`, `portlist`).

**Verified against real code in this repository (2026-07-17):** `src/Settings/Type/` now contains **all 22** DSL classes — the 14 that were reserved-but-unimplemented in the legacy plugin have since been built out in this OSS adapter (`Autocomplete.php`, `EncryptedPassword.php`, `Htmleditor.php`, `Colourpicker.php`, `Duration.php`, `Time.php`, `Multicheckbox.php`, `Multiselect.php`, `Storedfile.php`, `Filepath.php`, `Directory.php`, `Executable.php`, `Iplist.php`, `Portlist.php`, plus the original 8). This closes the gap the legacy ADR/REF pair documented as open — a genuine forward-progress finding, not a contradiction to flag as debt.

## Anti-patterns

- Using `get_config()` directly — bypasses the typed schema.
- Putting resolution logic in the enum — the enum must stay pure; resolution logic belongs to services.
- Creating a config key with no matching enum case — a "ghost" key that never appears in the schema.
- Registering lazy options during `register()` — services are not available at that phase (boot, per [MDL-011](../decisions/MDL-011-admin-settings-declaration-lifecycle.md)).
