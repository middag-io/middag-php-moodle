# middag-io/moodle

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

MIDDAG Moodle adapter — platform bindings, ACL layer, and Moodle-specific
implementations of the [`middag-io/framework`](https://github.com/middag-io/middag-php-framework)
contracts.

> **License:** Apache-2.0.

## What this package is

`middag-io/moodle` is the Moodle host adapter for the MIDDAG framework. It
provides the Moodle-side implementations of the framework's adapter contracts —
bootstrap, config resolution, signal dispatch, command bus, outbox, user
context — plus Moodle API wrappers (`Support/`), entities, DTOs, settings types,
and PDF / HTTP / Inertia infrastructure.

It binds the framework to a Moodle site. A Moodle plugin provides the
composition root that wires it in; any `local_*` or `mod_*` plugin can play that
role.

### What it does not include

- No product features, business rules, or governed domain capabilities.
- No dependency on any non-OSS MIDDAG package — the adapter builds only on the
  OSS framework and the host platform. Importing any non-OSS MIDDAG namespace or
  package is forbidden and enforced by `composer check:boundaries` and the
  adapter isolation tests.
- No bundled Moodle plugin. You wire the adapter into your own plugin.

## Requirements

- PHP `^8.2` (tested on 8.2, 8.3, 8.4)
- `ext-json`
- A Moodle site (the adapter targets Moodle's runtime APIs)

## Installation

```bash
composer require middag-io/moodle
```

This pulls `middag-io/framework` and `middag-io/ui` automatically.

> The `middag-io/*` packages are published under the `middag-io` GitHub
> organization. If they are not available on your default Composer registry yet,
> add the source repositories to your project's `composer.json`:
>
> ```json
> {
>     "repositories": [
>         { "type": "vcs", "url": "https://github.com/middag-io/middag-php-moodle" },
>         { "type": "vcs", "url": "https://github.com/middag-io/middag-php-framework" },
>         { "type": "vcs", "url": "https://github.com/middag-io/middag-php-ui" }
>     ]
> }
> ```

## Development

```bash
git clone https://github.com/middag-io/middag-php-moodle
cd middag-php-moodle
composer install
```

Run the quality gates and the test suite:

```bash
composer check   # boundaries + PHPStan + PHP-CS-Fixer + Rector (dry-run)
composer test    # PHPUnit
```

Git hooks are configured automatically via `post-install-cmd`. The `commit-msg`
hook enforces [Conventional Commits](https://www.conventionalcommits.org/).

### Working against a sibling framework checkout

During development the adapter can resolve the OSS `middag-io/framework` package
from a sibling path repository (`../middag-php-framework`, symlinked) declared in
`composer.json`. This is a **development-only** convenience for editing the
framework and the adapter side by side. Published releases resolve the dependency
through the normal Composer registry — the path repository has no effect on
consumers.

### `composer.lock` is gitignored

Like a typical library, this repo does not commit `composer.lock`; consumers pin
versions in their own application. Because the development setup may use a path
repository for the framework, a **local** `composer.lock` can show path or dev
references. That is expected local development state and **not** a defect in the
released package.

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the full contributor setup,
including the dependency-resolution notes.

### Commit format

```
type(scope): description

Types: feat, fix, chore, docs, style, refactor, perf, test, build, ci, revert
```

### Releases

Releases are managed by [release-please](https://github.com/googleapis/release-please).
Conventional commits merged to `main` open a Release PR automatically.

## License

Licensed under the [Apache License 2.0](LICENSE). See [`NOTICE`](NOTICE) for
attribution.
