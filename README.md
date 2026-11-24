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

| Item | Value |
|------|-------|
| PHP | `^8.3`: 8.3 to 8.6, within the PHP versions the Moodle version supports; `ext-json` |
| Symfony | `^7.4 \|\| ^8.1` for every Symfony component it requires (the contracts keep their own ranges) |
| Sibling libraries | `middag-io/framework` `^1.24` and `middag-io/ui` `^1.14` |
| Moodle | 4.5 (PHP 8.3), 5.1, 5.2 and 5.3 (PHP 8.4; PHP 8.3 expected, not installed in CI) |
| Database engines | PostgreSQL, MySQL and MariaDB, as supported by the Moodle version; SQL Server and Oracle are outside the range |

The `DbSupport` `sql*` helpers are tested against real databases: the
[Engines workflow](.github/workflows/engines.yml) installs Moodle 4.5, 5.1, 5.2
and 5.3 on PostgreSQL, MySQL and MariaDB and executes the SQL of every helper
([`tests/Integration/Engine`](tests/Integration/Engine)).

The Moodle range follows upstream support and belongs to this adapter: adding a
Moodle version is a minor, and dropping one whose upstream support has ended is
not a breaking change. Moodle 5.0 is outside the range; its stubs stay in the
PHPStan matrix while 4.5 is in range, with no support promise. The `1.x` line is
pre-stable and unsupported; support starts at `2.0.0` (see [Versioning and
compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility)).

## Installation

```bash
composer require middag-io/moodle
```

This pulls `middag-io/framework` and `middag-io/ui` automatically.

> The `middag-io/*` packages are published on [Packagist](https://packagist.org/packages/middag-io/),
> so they resolve from the default Composer registry with no extra
> configuration. No manual `repositories` entry is required.

Moodle 4.5 runs up to PHP 8.3: resolve the `vendor/` you ship to it on PHP 8.3
([Moodle adapter reference](https://docs.middag.dev/framework/reference/adapters/moodle/)).

## Documentation

The architecture and reference of this adapter live on
[docs.middag.dev](https://docs.middag.dev/framework/reference/adapters/moodle/).
This repository keeps the README, [`API-STABILITY.md`](API-STABILITY.md), the
[`CHANGELOG.md`](CHANGELOG.md) and the decision records in
[`docs/decisions/`](docs/decisions/).

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

The platform is pinned to PHP 8.3 (`config.platform.php` `8.3.0`), so a local
install resolves the Symfony 7.4 line whatever PHP runs Composer. CI runs the
checks and the tests on PHP 8.3, 8.4, 8.5 and 8.6 with the pin, a `highest`
job on 8.4, 8.5 and 8.6 without it that asserts the Symfony components resolve
to 8.x, a PHPStan ceiling pass at PHP 8.6 (`composer check:stan:ceiling`), and
PHPStan against the Moodle stubs of 4.5, 5.0 (transition only), 5.1, 5.2 and 5.3
([Platform](https://docs.middag.dev/libraries/reference/platform)).

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

This library is on the pre-stable `1.x` line. From `2.0.0` it joins the
open-source `middag-php-*` version family, which shares only the MAJOR: a
minor never breaks, and a consumer requires each package it imports with
`^2.0`, or with the MINOR whose features it uses.
See [API-STABILITY.md](API-STABILITY.md#versioning) and
[Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).

## Help and status

- **Status:** the `1.x` line is pre-stable and unsupported; support starts at
  `2.0.0`. See
  [Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).
- **Documentation:** [Reference — Moodle Adapter](https://docs.middag.dev/framework/reference/adapters/moodle/)
  on docs.middag.dev.
- **Questions and bugs:** [GitHub issues](https://github.com/middag-io/middag-php-moodle/issues).
- **Security reports:** see [`SECURITY.md`](SECURITY.md). Do not open a public
  issue.
- **Changes:** [`CHANGELOG.md`](CHANGELOG.md).

## License

Licensed under the [Apache License 2.0](LICENSE). See [`NOTICE`](NOTICE) for
attribution.
