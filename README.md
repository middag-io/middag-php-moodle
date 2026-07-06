# middag-io/moodle

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

MIDDAG Moodle adapter — platform bindings, ACL layer, and Moodle-specific
implementations of the [`middag-io/framework`](https://github.com/middag-io/middag-php-framework)
contracts.

> **License:** Apache-2.0.

## What this package is

`middag-io/moodle` is the Moodle host adapter for the MIDDAG framework. It
provides the Moodle-side implementations of the framework's adapter contracts —
bootstrap, config resolution, signal dispatch, command bus, user context — plus
Moodle API wrappers (`Support/`), entities, DTOs, settings types, and HTTP /
Inertia infrastructure.

It binds the framework to a Moodle site. A Moodle plugin provides the
composition root that wires it in; any `local_*` or `mod_*` plugin can play that
role.

### What it does not include

- No product features, business rules, or governed domain capabilities.
- No dependency on any non-OSS MIDDAG package — the adapter builds only on the
  OSS framework and the host platform. Importing any non-OSS MIDDAG namespace or
  package is forbidden and enforced by the adapter isolation guard test
  (`tests/AdapterPluginIsolationTest.php`, part of `composer test`).
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

> The `middag-io/*` packages are published on [Packagist](https://packagist.org/packages/middag-io/),
> so they resolve from the default Composer registry with no extra
> configuration. No manual `repositories` entry is required.

## Development

```bash
git clone https://github.com/middag-io/middag-php-moodle
cd middag-php-moodle
composer install
```

Run the quality gates and the test suite:

```bash
composer check   # PHP-CS-Fixer (dry-run) + Rector (dry-run) + PHPStan
composer test    # PHPUnit (includes the isolation and loadability guard tests)
```

Git hooks are configured automatically via `post-install-cmd`. The `commit-msg`
hook enforces [Conventional Commits](https://www.conventionalcommits.org/).

### Working against a sibling framework checkout

This package declares **no** `repositories` entries: `middag-io/framework` (and
every other dependency) resolves from the default Composer registry. To edit
the framework and the adapter side by side, declare the path repository in the
**consuming (root) project's** `composer.json` — Composer only reads the root
package's `repositories`; entries inside a dependency would be ignored anyway:

```json
{
    "repositories": [
        {"type": "path", "url": "../middag-php-framework", "options": {"symlink": true}}
    ]
}
```

This is a **development-only** convenience in the consumer. Published releases
of this package always resolve through the normal Composer registry.

### `composer.lock` is gitignored

Like a typical library, this repo does not commit `composer.lock`; consumers pin
versions in their own application. Because local development may resolve the
framework through a consumer-declared path repository, a **local**
`composer.lock` can show path or dev references. That is expected local
development state and **not** a defect in the released package.

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
