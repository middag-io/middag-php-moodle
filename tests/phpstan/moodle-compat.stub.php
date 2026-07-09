<?php

// PHPStan symbol stub — referenced via `scanFiles` in .phpstan.neon, so PHPStan
// scans it for symbols but never analyses it (no cs-fixer/style rules apply;
// the directory is excluded from the fixer Finder). tests/ is export-ignored
// from the published dist.
//
// Moodle 5.0 registers the legacy global `\action_link` alias at runtime
// (lib/classes/output/action_link.php:
// class_alias(core\output\action_link::class, action_link::class)), but the
// michaelmeneses/moodle-stubs package omits that alias. Without it PHPStan
// cannot resolve the `?\action_link` return type declared by
// core\check\check::getAction_link() (consumed in Support/CheckSupport), so it
// flags the otherwise-correct `->url` access. Model the alias here.

class action_link
{
    public \core\url $url;
}
