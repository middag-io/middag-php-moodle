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
- **Resolution is per PHP line by design** (a committed lock would break this):
  on PHP 8.2/8.3 Composer resolves Symfony 7.x; on PHP 8.4 it may pick
  Symfony 8.x (requires PHP >= 8.4.1). Moodle 4.5 caps PHP at 8.3, so a 4.5
  host needs a vendor resolved on the 8.3 line — if your workstation runs
  PHP 8.4, do not ship its `vendor/` to an older host. The CI test matrix
  resolves fresh per cell and asserts the 8.2/8.3 cells stay on Symfony 7.x.
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
  from commits merged to `main`. Versioning follows the `middag-php-*` family
  policy in this package's [API-STABILITY.md](API-STABILITY.md) (decided in
  ADR-032 of the internal `docs-middag-ops`, mirrored
  in the framework's
  [API-STABILITY.md](https://github.com/middag-io/middag-php-framework/blob/main/API-STABILITY.md)):
  - **On `1.x`:** `feat:` cuts a minor. Until 2026-11-30 a minor may carry a
    break, shipped as `feat:` (never `fix:`, so never in a patch) with the
    break described in the commit body. Never use `!` or a `BREAKING CHANGE:` footer: that would cut `2.0.0`
    in this lib alone. From 2026-12-01 no break lands on `1.x`.
  - **From `2.0.0`:** the libraries share `MAJOR.MINOR`. A patch carries fixes
    only, a `feat:` waits for a family minor window, and a break waits for a
    family major window after a deprecation in an earlier minor. The product
    owner decides every family minor and major.
  - **The check:** `php-version-family-check / check` reads the PR title and
    every non-merge commit against the organization variable
    `PHP_LIBS_OPEN_RELEASE` (empty = closed, `X.Y` = family minor window,
    `X.0` = family major window). A held feature PR stays open and is kept
    current by merging `main` into it (never rebase or force-push). Re-run the
    check once the window opens. See
    [API-STABILITY.md](API-STABILITY.md#the-version-family-check).

> Historical note: `1.1.1` shipped the audit-consolidation breaking changes
> (the batch-A renames, the framework Translation port replacing the local
> interface, and the PDFTk adapter removal) as a patch by explicit maintainer
> decision, closing the OSS audit before external consumers existed. The
> policy above applies from that release onward.

## Code of conduct

This project has a [Code of Conduct](CODE_OF_CONDUCT.md). By participating you
agree to uphold it; the reporting contact is listed there.
