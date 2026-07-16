<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Moodle function/class stubs for the config/context/session/page/theme/auth
 * Support wrappers. Guarded with !function_exists / !class_exists so the file
 * is order-independent and purely additive (mirrors tests/stubs/support/request.php).
 * Dynamic returns are driven via $GLOBALS['__middag_test_*'] so tests stay in control.
 */

// --- Session / auth functions (SessionSupport, AuthSupport) ---

if (!function_exists('confirm_sesskey')) {
    function confirm_sesskey(?string $sesskey = null): bool
    {
        return $GLOBALS['__middag_test_sesskey_valid'] ?? true;
    }
}

if (!function_exists('require_sesskey')) {
    function require_sesskey(): void
    {
        $GLOBALS['__middag_test_require_sesskey_called'] = true;
    }
}

if (!function_exists('sesskey')) {
    function sesskey(): string
    {
        return $GLOBALS['__middag_test_sesskey'] ?? 'sesskey000';
    }
}

if (!function_exists('isloggedin')) {
    function isloggedin(): bool
    {
        return $GLOBALS['__middag_test_isloggedin'] ?? true;
    }
}

if (!function_exists('isguestuser')) {
    function isguestuser(mixed $user = null): bool
    {
        return $GLOBALS['__middag_test_isguest'] ?? false;
    }
}

if (!function_exists('require_login')) {
    function require_login(mixed $courseorid = null, bool $autologinguest = true, mixed $cm = null, bool $setwantsurltome = true, bool $preventredirect = false): void
    {
        $GLOBALS['__middag_test_require_login'] = [$courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect];
    }
}

if (!function_exists('complete_user_login')) {
    function complete_user_login(object $user): mixed
    {
        return $GLOBALS['__middag_test_complete_login'] ?? $user;
    }
}

if (!function_exists('get_admin')) {
    function get_admin(): mixed
    {
        // Real Moodle returns stdClass|false — false (not null) when there is
        // no site admin. Default to false so the no-admin branch exercises the
        // adapter's false-normalisation instead of hiding it (LB-MDL-SUP-002).
        return $GLOBALS['__middag_test_admin'] ?? false;
    }
}

// --- core\session\manager (SessionSupport::destroyUserSessions / setUser) ---

if (!class_exists('core\session\manager', false)) {
    eval('namespace core\session; class manager {
        public static function destroy_user_sessions($userid, $except = "") { $GLOBALS["__middag_test_destroyed_sessions"] = [$userid, $except]; }
        public static function set_user($user) { $GLOBALS["__middag_test_session_user"] = $user; }
    }');
}

// --- Page / output functions (PageSupport) ---

if (!function_exists('markdown_to_html')) {
    function markdown_to_html(string $text, bool $filter = true): string
    {
        return '<markdown>' . $text . '</markdown>';
    }
}

if (!function_exists('admin_externalpage_setup')) {
    function admin_externalpage_setup(string $section, mixed $extrapageparams = '', mixed $extraurlparams = null, mixed $actualurl = '', mixed $options = []): void
    {
        $GLOBALS['__middag_test_admin_externalpage'] = $section;
    }
}

if (!function_exists('admin_get_root')) {
    function admin_get_root(bool $fromcache = true, bool $requirefulltree = true): mixed
    {
        return $GLOBALS['__middag_test_admin_root'] ?? null;
    }
}

// --- core\context\* namespaced classes (4.2+) used by ContextSupport ---
// core\context (base) and core\context\system are provided by tests/bootstrap.php;
// the remaining typed subclasses are added here so the wrappers' declared return
// types resolve and ::instance() yields an instance of the correct subtype.

foreach (['course', 'module', 'coursecat', 'user', 'block'] as $middagContextType) {
    if (!class_exists('core\context\\' . $middagContextType, false)) {
        eval('namespace core\context; class ' . $middagContextType . ' extends \core\context {
            public static function instance($id = 0, $strictness = \MUST_EXIST) { if (!empty($GLOBALS["__middag_test_throw_context_instance"])) { throw new \moodle_exception("invalidcontext"); } if (in_array((int) $id, $GLOBALS["__middag_test_context_course_throw_ids"] ?? [], true)) { throw new \moodle_exception("invalidcontext"); } return new self((int) $id); }
        }');
    }
}
