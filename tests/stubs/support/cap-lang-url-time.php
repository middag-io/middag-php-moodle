<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

use core\exception\moodle_exception;

/*
 * Moodle function/class stubs for the capability / preference / lang / url / time
 * Support wrappers. Guarded with !function_exists / !class_exists so the file is
 * order-independent and purely additive (mirrors tests/stubs/support/request.php
 * and config-env.php). Dynamic returns are driven via $GLOBALS['__middag_test_*']
 * so tests stay in control; stubs throw when $GLOBALS['__middag_test_throw_<fn>']
 * is set, letting tests cover the wrappers' catch branches.
 */

// --- Capability functions (CapabilitySupport) ---
// has_capability() is provided by tests/bootstrap.php (central). The rest are
// added here. get_user_roles() throws a core\exception\moodle_exception (the
// namespaced type CapabilitySupport catches) when its throw flag is set.

if (!function_exists('get_capability_string')) {
    function get_capability_string($capability): string
    {
        return $GLOBALS['__middag_test_capability_string'] ?? ('[cap:' . $capability . ']');
    }
}

if (!function_exists('require_capability')) {
    function require_capability($capability, $context, $user = null, $doanything = true, $errormessage = 'nopermissions', $stringfile = ''): void
    {
        $GLOBALS['__middag_test_require_capability'] = [$capability, $errormessage, $stringfile];
    }
}

if (!function_exists('get_user_roles')) {
    function get_user_roles($context, $userid = 0, $checkparent = true): array
    {
        if (!empty($GLOBALS['__middag_test_throw_get_user_roles'])) {
            throw new moodle_exception('rolesfailed');
        }

        return $GLOBALS['__middag_test_user_roles'] ?? [];
    }
}

// --- User preference functions (PreferenceSupport) ---
// Backing store lives in $GLOBALS['__middag_test_preferences'] (per-key) and
// $GLOBALS['__middag_test_preferences_all'] (the "all preferences" object).

if (!function_exists('get_user_preferences')) {
    function get_user_preferences($name = null, $default = null, $user = null): mixed
    {
        if (!empty($GLOBALS['__middag_test_throw_get_user_preferences'])) {
            throw new RuntimeException('pref get failed');
        }

        if ($name === null) {
            return $GLOBALS['__middag_test_preferences_all'] ?? $default;
        }

        return $GLOBALS['__middag_test_preferences'][$name] ?? $default;
    }
}

if (!function_exists('set_user_preference')) {
    function set_user_preference($name, $value, $user = null): bool
    {
        if (!empty($GLOBALS['__middag_test_throw_set_user_preference'])) {
            throw new RuntimeException('pref set failed');
        }

        // Mirror moodlelib.php: a null $value DELETES the preference
        // (delegates to unset_user_preference) instead of storing anything.
        if ($value === null) {
            unset($GLOBALS['__middag_test_preferences'][$name]);

            return true;
        }

        $GLOBALS['__middag_test_preferences'][$name] = $value;

        return true;
    }
}

if (!function_exists('set_user_preferences')) {
    function set_user_preferences(array $prefs, $user = null): bool
    {
        if (!empty($GLOBALS['__middag_test_throw_set_user_preferences'])) {
            throw new RuntimeException('prefs set failed');
        }

        foreach ($prefs as $key => $value) {
            $GLOBALS['__middag_test_preferences'][$key] = $value;
        }

        return true;
    }
}

if (!function_exists('unset_user_preference')) {
    function unset_user_preference($name, $user = null): bool
    {
        if (!empty($GLOBALS['__middag_test_throw_unset_user_preference'])) {
            throw new RuntimeException('pref unset failed');
        }

        unset($GLOBALS['__middag_test_preferences'][$name]);

        return true;
    }
}

// --- Lang functions/classes (LangSupport) ---
// get_string() and get_string_manager() are provided by tests/bootstrap.php
// (their handlers may throw via $GLOBALS closures). core\lang_string and
// current_language() are added here.

if (!class_exists('core\lang_string', false)) {
    eval('namespace core; class lang_string implements \Stringable {
        public function __construct(public string $identifier = "", public ?string $component = null, public mixed $a = null, public bool $lazyload = false) {}
        public function __toString(): string { return "[" . (string) $this->component . "/" . $this->identifier . "]"; }
    }');
}

if (!function_exists('current_language')) {
    function current_language(): string
    {
        if (!empty($GLOBALS['__middag_test_throw_current_language'])) {
            throw new Exception('current_language failed');
        }

        return $GLOBALS['__middag_test_current_language'] ?? 'en';
    }
}

// --- URL functions (UrlSupport) ---
// moodle_url (core\url) is provided by tests/bootstrap.php. redirect() records
// its arguments so the void wrapper can be asserted.

if (!function_exists('redirect')) {
    function redirect($url, $message = '', $delay = null, $messagetype = ''): void
    {
        $GLOBALS['__middag_test_redirect'] = [$url, $message, $delay, $messagetype];
    }
}

// --- Time functions/classes (TimeSupport) ---
// core\user::get_user() is NOT provided (central stub gap — see coverage report),
// so the userid-driven branches of userTimezone()/userTimezoneObject() are
// skipped until it exists.

if (!function_exists('userdate')) {
    function userdate($date, $format = '', $timezone = 99, $fixday = true, $fixhour = true): string
    {
        return $GLOBALS['__middag_test_userdate'] ?? ('date:' . $date . '|tz:' . $timezone);
    }
}

if (!function_exists('usertime')) {
    function usertime($date, $timezone = 99): int
    {
        return $GLOBALS['__middag_test_usertime'] ?? ((int) $date + 3600);
    }
}

if (!function_exists('make_timestamp')) {
    function make_timestamp($year, $month = 1, $day = 1, $hour = 0, $minute = 0, $second = 0, $timezone = 99, $applydst = true): int
    {
        return $GLOBALS['__middag_test_make_timestamp'] ?? (int) mktime((int) $hour, (int) $minute, (int) $second, (int) $month, (int) $day, (int) $year);
    }
}

if (!class_exists('core_date', false)) {
    eval('class core_date {
        public static function get_server_timezone() { return $GLOBALS["__middag_test_server_tz"] ?? "UTC"; }
        public static function get_user_timezone($user = null) { return $GLOBALS["__middag_test_user_tz"] ?? "UTC"; }
        public static function get_server_timezone_object() { return new \DateTimeZone($GLOBALS["__middag_test_server_tz"] ?? "UTC"); }
        public static function get_user_timezone_object($user = null) { return new \DateTimeZone($GLOBALS["__middag_test_user_tz"] ?? "UTC"); }
    }');
}
