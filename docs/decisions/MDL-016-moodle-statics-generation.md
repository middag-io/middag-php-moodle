---
title: 'Typed definitions generate the Moodle plugin static files'
status: accepted
date: 2026-04-03
last-updated: 2026-11-04
lang: en
domains: [codegen, files]
deciders: [leadership]
decision: 'Plugin static files are generated from typed definitions by external tooling; file areas use a handler contract.'
related: [MDL-002, MDL-005, MDL-007, MDL-008, MDL-011, MDL-015]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# MDL-016: Typed definitions generate the Moodle plugin static files

## Context

Moodle reads a family of PHP array files from each plugin: capabilities, caches, events, hooks, message providers, web services and more. Kept by hand, they drift from the code that relies on them and vary by Moodle version. File areas also need a way to decide who may download a file. The question is where these declarations come from and how files are served.

## Considered Options

1. Keep each static file by hand in every consumer plugin.
2. Generate the files with code that ships inside this adapter.
3. Generate everything, including settings and file-serving routes.
4. Typed definition objects in this adapter describe each kind of static file. Build tooling outside the adapter renders them into the plugin's files. File serving goes through a handler contract resolved at request time, and settings stay a runtime exception. ← chosen

## Decision

Each kind of Moodle plugin static file has a typed definition class in this adapter. A definition knows how to render itself into the array Moodle expects and which Moodle versions it applies to. Consumers declare definitions; build tooling outside this package turns them into the plugin's static files, so the adapter ships descriptions, not a generator. File areas are declared the same way, but serving a file is not generated: at request time the consumer's file-serving callback resolves the area's handler, which decides access and then serves the file. Settings are the documented exception and are resolved at runtime (MDL-011).

## Consequences

- (+) Static files always match what the code declares, and version ranges are applied consistently.
- (+) Definitions are plain typed objects that are tested without Moodle.
- (−) Generating the files needs build tooling this package does not ship. A consumer without it renders the definitions itself.
- (−) File access is only as safe as the consumer's file-serving callback: it must ask the handler for access before it serves anything.
- (−) Two mechanisms exist side by side: generated static files and runtime-resolved settings.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Each definition renders the Moodle array and reports its version range | tests `tests/Definition/*` | coded |
| Definition kinds, generated targets and naming | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/statics-generation.md` | coded |
| File-area handler contract and request flow | doc `docs-middag-dev:docs/framework/reference/adapters/moodle/file-areas-pluginfile-routing.md` | coded |
| The consumer's file-serving callback checks access before serving | test in the consumer plugin | planned |
