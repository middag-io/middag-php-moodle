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
 * Moodle function/class stubs for the version / plugin / check / user / role
 * Support wrappers. Guarded with !function_exists / !class_exists / !defined so
 * the file is order-independent and purely additive (mirrors
 * tests/stubs/support/request.php, config-env.php and course.php). Dynamic
 * returns are driven via $GLOBALS['__middag_test_*'] so tests stay in control;
 * stubs throw when a $GLOBALS['__middag_test_throw_<fn>'] flag is set, letting
 * tests cover the wrappers' catch branches.
 *
 * dml_exception is provided by tests/stubs/support/course.php (loaded earlier in
 * the glob); it is intentionally not redefined here.
 */

// --- Plugin API (PluginSupport) ---
// core\plugin_manager is a version-stable facade over Moodle's plugin registry.
// plugin_name() and get_plugin_info() are the only surfaces PluginSupport uses;
// get_plugin_info() maps component -> plugininfo object built by the test.
// NOTE: core\component::get_plugin_types() / get_plugin_list() are NOT provided
// here — that class is a central bootstrap stub (locked before this glob), so
// the plugin-type-driven PluginSupport methods stay blocked until it grows them.

if (!class_exists('core\plugin_manager', false)) {
    eval('namespace core; class plugin_manager {
        public static function instance() { return new self(); }
        public function plugin_name($component) { return $GLOBALS["__middag_test_plugin_name"] ?? $component; }
        public function get_plugin_info($component) { return $GLOBALS["__middag_test_plugin_info"][$component] ?? null; }
    }');
}

// --- Check API (CheckSupport) ---
// core\check\manager::get_checks() returns the registered checks (test-driven),
// or throws when its flag is set so getChecks()'s catch branch is reachable.
// core\check\result exposes the status constants CheckSupport::getResultStatusLabel
// matches against.

if (!class_exists('core\check\manager', false)) {
    eval('namespace core\check; class manager {
        public static function get_checks($type = "status") {
            if (!empty($GLOBALS["__middag_test_throw_get_checks"])) {
                throw new \RuntimeException("get_checks failed");
            }

            return $GLOBALS["__middag_test_checks"] ?? [];
        }
    }');
}

if (!class_exists('core\check\result', false)) {
    eval('namespace core\check; class result {
        public const NA = "na";
        public const OK = "ok";
        public const INFO = "info";
        public const UNKNOWN = "unknown";
        public const WARNING = "warning";
        public const ERROR = "error";
        public const CRITICAL = "critical";
    }');
}

// --- User functions (UserSupport) ---
// core\user::get_user() is a central bootstrap stub; the get_user_by_email /
// get_user_by_username surfaces are NOT provided there (locked), so those two
// wrappers stay blocked. fullname() is purely data-driven (a cross-cutting
// helper) to avoid conflicting with any parallel batch that also stubs it.

if (!function_exists('fullname')) {
    function fullname($user, $override = false): string
    {
        return $GLOBALS['__middag_test_fullname'] ?? 'Stub Fullname';
    }
}

if (!function_exists('user_create_user')) {
    function user_create_user($user, $updatepassword = true, $triggerevent = true): int
    {
        $GLOBALS['__middag_test_created_user'] = [$user, $updatepassword, $triggerevent];

        return $GLOBALS['__middag_test_new_user_id'] ?? 1;
    }
}

if (!function_exists('user_update_user')) {
    function user_update_user($user, $updatepassword = true, $triggerevent = true): void
    {
        $GLOBALS['__middag_test_updated_user'] = [$user, $updatepassword, $triggerevent];
    }
}

if (!function_exists('delete_user')) {
    function delete_user($user): bool
    {
        return $GLOBALS['__middag_test_delete_result'] ?? true;
    }
}

if (!function_exists('profile_get_user_fields_with_data')) {
    function profile_get_user_fields_with_data($userid): array
    {
        return $GLOBALS['__middag_test_profile_fields'] ?? [];
    }
}

// --- Role functions/classes (RoleSupport) ---
// Role name display constants and the role helper functions the wrapper calls.
// get_all_roles / role_fix_names / get_assignable_roles / get_users_from_role_on_context
// are all data-driven. The global context_course stand-in returns a context with
// ->id, or a caller-set value (e.g. false) so the !$ctx guard is reachable.

if (!defined('ROLENAME_ALIAS')) {
    define('ROLENAME_ALIAS', 1);
}
if (!defined('ROLENAME_BOTH')) {
    define('ROLENAME_BOTH', 3);
}

if (!function_exists('get_all_roles')) {
    function get_all_roles($context = null): array
    {
        return $GLOBALS['__middag_test_all_roles'] ?? [];
    }
}

if (!function_exists('role_fix_names')) {
    function role_fix_names($roles, $context = null, $rolenamedisplay = 0, $returnmenu = null): array
    {
        if (isset($GLOBALS['__middag_test_role_names'])) {
            return $GLOBALS['__middag_test_role_names'];
        }

        $out = [];
        foreach ($roles as $key => $role) {
            $out[$key] = is_object($role) ? ($role->shortname ?? ('role' . $key)) : (string) $role;
        }

        return $out;
    }
}

if (!function_exists('get_assignable_roles')) {
    function get_assignable_roles($context, $rolenamedisplay = 0, $withusercounts = false, $user = null): array
    {
        return $GLOBALS['__middag_test_assignable_roles'] ?? [];
    }
}

if (!function_exists('get_users_from_role_on_context')) {
    function get_users_from_role_on_context($role, $context): array
    {
        return $GLOBALS['__middag_test_role_users'] ?? [];
    }
}

if (!class_exists('context_course', false)) {
    eval('class context_course {
        public int $id = 0;
        public function __construct(int $id = 0) { $this->id = $id; }
        public static function instance($id = 0, $strictness = 2) {
            if (array_key_exists("__middag_test_context_course_instance", $GLOBALS)) {
                return $GLOBALS["__middag_test_context_course_instance"];
            }

            return new self((int) $id);
        }
    }');
}
