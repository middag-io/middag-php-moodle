# Contributing

Thanks for your interest in contributing to `middag-io/moodle`, the Moodle host
adapter for the [MIDDAG framework](https://github.com/middag-io/middag-php-framework).

## Getting started

Requirements: PHP 8.2+ and Composer 2.

```bash
git clone https://github.com/middag-io/middag-php-moodle
cd middag-php-moodle
composer install
```

`composer install` registers the Git hooks (`core.hooksPath .githooks`); the
`commit-msg` hook enforces Conventional Commits.

## Dependency resolution

This adapter builds on the OSS `middag-io/*` packages (`framework`, `ui`) and on
`michaelmeneses/moodle-stubs` for static analysis. All dependencies are OSS or
host-provided; no private infrastructure is required.

- **Local development** against a sibling framework checkout is wired in the
  consuming project, not here: this package declares no `repositories` entries.
  To edit the framework and the adapter together, clone `middag-php-framework`
  next to this repo and declare a path repository
  (`{"type": "path", "url": "../middag-php-framework", "options": {"symlink": true}}`)
  in the **consumer/root** `composer.json`. Composer only reads the root
  package's `repositories` — entries inside a dependency are ignored — so this
  is a development-only convenience with no effect on published releases.
- `composer.lock` is **gitignored**. A local lock that references path or dev
  versions of the framework is expected development state — **not** a defect in
  the released package.
- CI and external consumers resolve the `middag-io/*` packages from
  [Packagist](https://packagist.org/packages/middag-io/) — no private mirror and
  no credentials. They install with `composer require` from the default Composer
  registry; no manual `repositories` entry is required.

## Quality gates

Every change must pass:

```bash
composer check   # import boundaries + PHPStan + PHP-CS-Fixer (dry-run) + Rector (dry-run)
composer test    # PHPUnit
```

Auto-fix style and Rector findings with:

```bash
composer fix
```

The adapter isolation guard test (`tests/AdapterPluginIsolationTest.php`, run as
part of `composer test`) enforces that the adapter never imports any non-OSS
MIDDAG namespace or package. Keep `src/` free of those imports — the adapter
must remain consumable on its own.

## Commit and PR conventions

- [Conventional Commits](https://www.conventionalcommits.org/): `type(scope): description`.
  Types: `feat`, `fix`, `chore`, `docs`, `style`, `refactor`, `perf`, `test`,
  `build`, `ci`, `revert`.
- Keep pull requests focused. Update tests and docs alongside code.
- Releases are automated by [release-please](https://github.com/googleapis/release-please)
  from commits merged to `main`.

## Code of conduct

This project has a [Code of Conduct](CODE_OF_CONDUCT.md). By participating you
agree to uphold it; the reporting contact is listed there.
