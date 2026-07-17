---
ref: REF-MDL-008-01
adr: MDL-008
title: 'Pluginfile Flow, File Support Inventory & Anti-Patterns'
lang: en
---

# REF-MDL-008-01: Pluginfile Flow, File Support Inventory & Anti-Patterns

> Detail supporting [MDL-008](../decisions/MDL-008-file-areas-pluginfile-routing.md). Reconstructed from the `moodle-local_middag` legacy vault (`ADR-307`, `ref-307-01`).

## Definition example

```php
new file_area(
    name: 'attachments',
    description: 'File attachments for items',
    context_level: context_level::SYSTEM,
    handler: attachments_file_area_handler::class,
    supports_preview: true,
)
```

## Full pluginfile flow

`Browser -> /pluginfile.php/{contextid}/local_middag/{filearea}/... -> local_middag_pluginfile() -> resolve handler -> can_access() -> serve()`

## `file_support` method inventory

`get_file`, `get_area_files`, `create_file_from_string`, `create_file_from_pathname`, `create_file_from_storedfile`, `delete_file`, `delete_area_files`, `get_file_url`, `has_files`, `get_area_size`, `count_area_files`, `is_valid_image`.

`url_support::pluginfile(...)` generates download URLs with the full signature the legacy REF exemplifies in detail.

## Anti-patterns

- `can_access()` always returning `true` (any user can access any file).
- Business logic inside the handler (should delegate to the domain layer).
- Using `send_file()` instead of `send_stored_file()` — bypasses the File API's headers/cache handling.
- Not declaring a file area at all — the pluginfile callback then finds no handler and fails.
