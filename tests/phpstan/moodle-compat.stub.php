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

// Moodle registers the legacy global `\moodle_url` name as a runtime
// class_alias() of core\url, which bimoo strips from the stubs (statement
// expressions are not declarations). Stub tags before the v*.*.*.1 rebuilds
// never resolved global names at all, so the gap only surfaced once the stubs
// regained their `use` imports. PHPStan does not register class_alias() from
// scanned files, so model the alias as a subclass — enough for consumers that
// RECEIVE a moodle_url (methods resolve through core\url). Signatures that
// EXPECT the legacy name (context/moodle_url params) cannot be modeled by
// inheritance at all — those carry scoped exemptions in .phpstan.neon.
class moodle_url extends \core\url {}
