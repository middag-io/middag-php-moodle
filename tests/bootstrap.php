<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Moodle\Config\ComponentContext;

/*
 * PHPUnit bootstrap for middag-io/moodle tests.
 *
 * Provides minimal Moodle function stubs that allow testing adapter classes
 * without a full Moodle runtime. Tests control return values via $GLOBALS.
 */

// Moodle constants required by source code
if (!defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', true);
}
if (!defined('IGNORE_MISSING')) {
    define('IGNORE_MISSING', 0);
}
if (!defined('IGNORE_MULTIPLE')) {
    define('IGNORE_MULTIPLE', 1);
}
if (!defined('MUST_EXIST')) {
    define('MUST_EXIST', 2);
}
if (!defined('SITEID')) {
    define('SITEID', 1);
}

// Stub: get_config() — reads from $GLOBALS['__middag_test_config']
if (!function_exists('get_config')) {
    function get_config(string $plugin, ?string $name = null): mixed
    {
        if ($name === null) {
            return (object) ($GLOBALS['__middag_test_config'] ?? []);
        }

        return $GLOBALS['__middag_test_config'][$name] ?? false;
    }
}

if (!function_exists('set_config')) {
    function set_config(string $name, mixed $value, string $plugin = ''): bool
    {
        $GLOBALS['__middag_test_config'][$name] = $value;

        return true;
    }
}

if (!function_exists('unset_config')) {
    function unset_config(string $name, string $plugin = ''): bool
    {
        unset($GLOBALS['__middag_test_config'][$name]);

        return true;
    }
}

// Stub: get_plugins_with_function() — reads from $GLOBALS['__middag_test_plugin_functions']
if (!function_exists('get_plugins_with_function')) {
    function get_plugins_with_function(string $function, string $file = 'lib.php', bool $include = true): array
    {
        $registry = $GLOBALS['__middag_test_plugin_functions'] ?? [];

        return $registry[$function] ?? [];
    }
}

// Stub: get_string() — delegates to $GLOBALS['__middag_test_get_string'] when
// set (a Closure receiving ($identifier, $component, $a); may throw to emulate
// Moodle's missing-string coding_exception), else echoes a deterministic
// "[component/identifier]" marker.
if (!function_exists('get_string')) {
    function get_string(string $identifier, string $component = '', mixed $a = null, bool $lazyload = false): string
    {
        $handler = $GLOBALS['__middag_test_get_string'] ?? null;
        if ($handler instanceof Closure) {
            return $handler($identifier, $component, $a);
        }

        return sprintf('[%s/%s]', $component, $identifier);
    }
}

// Stub: get_string_manager()->string_exists() — delegates to
// $GLOBALS['__middag_test_string_exists'] when set (a Closure receiving
// ($identifier, $component); may throw), else reports false.
if (!function_exists('get_string_manager')) {
    function get_string_manager(bool $forcereload = false): object
    {
        return new class {
            public function string_exists(string $identifier, string $component): bool
            {
                $handler = $GLOBALS['__middag_test_string_exists'] ?? null;
                if ($handler instanceof Closure) {
                    return (bool) $handler($identifier, $component);
                }

                return false;
            }
        };
    }
}

// Stub: email_to_user() — records calls in $GLOBALS['__middag_test_emails'];
// return value controlled via $GLOBALS['__middag_test_email_result'] (default true)
if (!function_exists('email_to_user')) {
    function email_to_user(object $user, $from, string $subject, string $messagetext, string $messagehtml = '', $attachment = '', $attachname = '', bool $usetrueaddress = true, $replyto = '', $replytoname = '', int $wordwrapwidth = 79): bool
    {
        $GLOBALS['__middag_test_emails'][] = [
            'to' => $user,
            'from' => $from,
            'subject' => $subject,
            'text' => $messagetext,
            'html' => $messagehtml,
            'attachment' => $attachment,
            'attachname' => $attachname,
            'replyto' => $replyto,
            'replytoname' => $replytoname,
        ];

        return $GLOBALS['__middag_test_email_result'] ?? true;
    }
}

// Stub: core\user::get_noreply_user() — deliverable pseudo-user template
// (Moodle 4.5+ namespaced class; global core_user aliased for legacy callers)
if (!class_exists('core\user', false)) {
    eval('namespace core; class user { public static function get_noreply_user(): \stdClass { $u = new \stdClass(); $u->id = -99; $u->email = "noreply@example.test"; $u->firstname = "No reply"; $u->lastname = ""; $u->maildisplay = 1; $u->emailstop = 0; $u->deleted = 0; $u->auth = "manual"; $u->mailformat = 1; return $u; } }');
}
if (!class_exists('core_user', false)) {
    class_alias('core\user', 'core_user');
}

// Stub: core\url (Moodle's URL class — implements __toString as Stringable)
if (!class_exists('core\url', false)) {
    eval('namespace core; class url implements \Stringable { public function __construct(public string $url = "") {} public function __toString(): string { return $this->url; } public function out(bool $escaped = true): string { return $this->url; } }');
}
if (!class_exists('moodle_url', false)) {
    class_alias('core\url', 'moodle_url');
}

// Stub: core\task\adhoc_task (base class for Moodle adhoc tasks)
if (!class_exists('core\task\adhoc_task', false)) {
    eval('namespace core\task; abstract class adhoc_task { private $customdata = null; private $userid = null; public function set_custom_data($customdata): void { $this->customdata = json_encode($customdata); } public function get_custom_data() { return $this->customdata === null ? null : json_decode($this->customdata); } public function set_userid($userid): void { $this->userid = $userid; } public function get_userid() { return $this->userid; } }');
}

// Stub: core\component::get_component_directory() — returns the path from
// $GLOBALS['__middag_test_component_dir'] (null when unset, mirroring an
// unknown/uninstalled component). moodle-stubs provide this for PHPStan only;
// it is not autoloadable at runtime, so tests need a behavioral stand-in.
if (!class_exists('core\component', false)) {
    eval('namespace core; class component { public static function get_component_directory($component) { return $GLOBALS["__middag_test_component_dir"] ?? null; } }');
}

// Stub: core_external API (external_api + structure classes). moodle-stubs
// provide these for PHPStan only; classes extending external_api need a runtime
// stand-in. See tests/stubs/external-api-stubs.php for the rationale + limits.
require_once __DIR__ . '/stubs/external-api-stubs.php';

// Composer autoloader (loads moodle-stubs + framework deps)
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Configure the adapter component seam for the test runtime (mirrors the product
// composition root). Adapter helpers resolve the running component via
// ComponentContext::name(), which throws when the adapter is unconfigured.
ComponentContext::configure('local_example', 'local_example_autoload');

// Post-autoload: framework interface stubs if not resolved by autoloader
if (!interface_exists(UserContextResolverInterface::class, false)) {
    require_once __DIR__ . '/stubs/framework-stubs.php';
}
