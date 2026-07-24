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
use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Moodle\Config\ComponentContext;

/*
 * PHPUnit bootstrap for middag-io/moodle tests.
 *
 * Provides minimal Moodle function stubs that allow testing adapter classes
 * without a full Moodle runtime. Tests control return values via $GLOBALS.
 */

// Neutralize ambient environment hints so Environment resolution is driven
// solely by each test's explicit $CFG signals (Environment::detectHostEnvironment).
// getEnvironment() reads MIDDAG_ENV/APP_ENV (resolution step 2) ABOVE the
// $CFG-based host hook (step 3); a container/CI MIDDAG_ENV=development would
// otherwise override the tests' intended environment and break the
// production-mode cache-path assertions (FacadeLoader / EventSupport).
putenv('MIDDAG_ENV');
putenv('APP_ENV');
unset($_ENV['MIDDAG_ENV'], $_ENV['APP_ENV'], $_SERVER['MIDDAG_ENV'], $_SERVER['APP_ENV']);

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

// Stub: core_string_manager — the interface LangSupport::stringExists() checks
// via instanceof before calling string_exists(). Only string_exists() is used
// anywhere in src/, so the stub interface declares just that.
if (!interface_exists('core_string_manager', false)) {
    eval('interface core_string_manager { public function string_exists($identifier, $component): bool; }');
}

// Stub: get_string_manager()->string_exists() — delegates to
// $GLOBALS['__middag_test_string_exists'] when set (a Closure receiving
// ($identifier, $component); may throw), else reports false. Implements
// core_string_manager so the LangSupport instanceof gate passes.
if (!function_exists('get_string_manager')) {
    function get_string_manager(bool $forcereload = false): ?core_string_manager
    {
        if (!empty($GLOBALS['__middag_test_string_manager_invalid'])) {
            return null;
        }

        return new class implements core_string_manager {
            public function string_exists($identifier, $component): bool
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
    eval('namespace core; class user { public static function get_noreply_user(): \stdClass { $u = new \stdClass(); $u->id = -99; $u->email = "noreply@example.test"; $u->firstname = "No reply"; $u->lastname = ""; $u->maildisplay = 1; $u->emailstop = 0; $u->deleted = 0; $u->auth = "manual"; $u->mailformat = 1; return $u; } public static function get_user($userid, $fields = "*", $strictness = \IGNORE_MISSING) { return $GLOBALS["__middag_test_user_record"] ?? (object) ["id" => (int) $userid]; } public static function get_user_by_email($email, $fields = "*", $mnethostid = null, $strictness = \IGNORE_MISSING) { return $GLOBALS["__middag_test_user_by_email"] ?? false; } public static function get_user_by_username($username, $fields = "*", $mnethostid = null, $strictness = \IGNORE_MISSING) { return $GLOBALS["__middag_test_user_by_username"] ?? false; } }');
}
if (!class_exists('core_user', false)) {
    class_alias('core\user', 'core_user');
}

// Stub: core\url (Moodle's URL class — implements __toString as Stringable).
// Throws moodle_exception on construction when $GLOBALS['__middag_test_throw_moodle_url']
// is set, so UrlSupport's MUST_EXIST rethrow / IGNORE_MISSING fallback branches are reachable.
if (!class_exists('core\url', false)) {
    eval('namespace core; class url implements \Stringable { public array $params; public function __construct(public string $url = "", array $params = [], public ?string $anchor = null) { if (!empty($GLOBALS["__middag_test_throw_moodle_url"])) { $GLOBALS["__middag_test_throw_moodle_url"] = ((int) $GLOBALS["__middag_test_throw_moodle_url"]) - 1; throw new \core\exception\moodle_exception("invalidurl"); } $this->params = $params; } public function __toString(): string { return $this->url; } public function out(bool $escaped = true): string { return $this->url; } public function set_anchor(?string $anchor): void { $this->anchor = $anchor; } }');
}
if (!class_exists('moodle_url', false)) {
    class_alias('core\url', 'moodle_url');
}

// Stub: core\task\adhoc_task (base class for Moodle adhoc tasks)
if (!class_exists('core\task\adhoc_task', false)) {
    eval('namespace core\task; abstract class adhoc_task { private $customdata = null; private $userid = null; public function set_custom_data($customdata): void { $this->customdata = json_encode($customdata); } public function get_custom_data() { return $this->customdata === null ? null : json_decode($this->customdata); } public function set_userid($userid): void { $this->userid = $userid; } public function get_userid() { return $this->userid; } }');
}

// Stub: core\component — get_component_directory() returns the path from
// $GLOBALS['__middag_test_component_dir'] (null when unset, mirroring an
// unknown/uninstalled component). get_plugin_types()/get_plugin_list() drive
// EventSupport::loadPluginEvents(): they read $GLOBALS['__middag_test_plugin_types']
// (type => dir map) and $GLOBALS['__middag_test_plugin_list'][$type] (plugin => dir
// map), both defaulting to [] so loadPluginEvents() is a no-op unless a test
// registers plugin fixtures. moodle-stubs provide these for PHPStan only; they
// are not autoloadable at runtime, so tests need a behavioral stand-in.
if (!class_exists('core\component', false)) {
    eval('namespace core; class component { public static function get_component_directory($component) { return $GLOBALS["__middag_test_component_dir"] ?? null; } public static function get_plugin_types() { return $GLOBALS["__middag_test_plugin_types"] ?? []; } public static function get_plugin_list($type) { return $GLOBALS["__middag_test_plugin_list"][$type] ?? []; } }');
}

// Stub: core\hook\described_hook — interface implemented by Hook\AbstractExtendExtensions.
// moodle-stubs provide it for PHPStan only; not autoloadable at runtime. Signature
// mirrors the stub (static snake_case accessors).
if (!interface_exists('core\hook\described_hook', false)) {
    eval('namespace core\hook; interface described_hook { public static function get_hook_description(): string; public static function get_hook_tags(): array; }');
}

// Stub: core_table\sql_table — parent of Table\UsersTable. Real class extends
// flexible_table; the adapter only calls parent::__construct($uniqueid), so the
// stand-in exposes that single constructor.
if (!class_exists('core_table\sql_table', false)) {
    eval('namespace core_table; class sql_table { public function __construct(public $uniqueid = null) {} }');
}

// Stub: core_table\local\filter\filterset — parent of Table\UsersFilterset
// (an otherwise-empty abstract subclass). Minimal stand-in for extension.
if (!class_exists('core_table\local\filter\filterset', false)) {
    eval('namespace core_table\local\filter; abstract class filterset {}');
}

// Stub: core\context — type of the $context arg on Table\UsersTable and the
// canonical instance_by_id() entry used by ContextSupport::instanceById().
// Real class is abstract (extends stdClass, IteratorAggregate); tests only
// need an instantiable stand-in of the right type.
if (!class_exists('core\context', false)) {
    // Real Moodle: `abstract class core\context extends stdClass`. Extending
    // stdClass here keeps the stub faithful and lets it satisfy the
    // array|stdClass contract of AbstractMoodleEntity::fromRecord() (exercised
    // by Domain\Context\Context::fromContext()).
    eval('namespace core; class context extends \stdClass {
        public function __construct(public int $id = 0) {}
        public static function instance_by_id($id, $strictness = \MUST_EXIST) { return new self((int) $id); }
    }');
}

// Moodle debug + SQL param constants (from lib/setuplib.php / lib/dml). Real
// Moodle values; moodle-stubs declare them for PHPStan only. MoodleLogger's
// LEVEL_MAP resolves these lazily on first log() call, so they must exist.
if (!defined('DEBUG_NONE')) {
    define('DEBUG_NONE', 0);
}
if (!defined('DEBUG_MINIMAL')) {
    define('DEBUG_MINIMAL', 5);
}
if (!defined('DEBUG_NORMAL')) {
    define('DEBUG_NORMAL', 15);
}
if (!defined('DEBUG_DEVELOPER')) {
    define('DEBUG_DEVELOPER', 38911);
}
if (!defined('SQL_PARAMS_NAMED')) {
    define('SQL_PARAMS_NAMED', 2);
}
if (!defined('SQL_PARAMS_QM')) {
    define('SQL_PARAMS_QM', 1);
}

// Moodle PARAM_* cleaning constants (from lib/moodlelib). Real string values;
// MformFieldMapper tags each element's setType() with one of these.
foreach ([
    'PARAM_TEXT' => 'text', 'PARAM_RAW' => 'raw', 'PARAM_EMAIL' => 'email',
    'PARAM_URL' => 'url', 'PARAM_INT' => 'int', 'PARAM_FLOAT' => 'float',
    'PARAM_ALPHANUMEXT' => 'alphanumext', 'PARAM_BOOL' => 'bool',
] as $paramConst => $paramValue) {
    if (!defined($paramConst)) {
        define($paramConst, $paramValue);
    }
}

// Stub: debugging() — records calls in $GLOBALS['__middag_test_debugging'].
if (!function_exists('debugging')) {
    function debugging(string $message = '', ?int $level = null, ?array $backtrace = null): bool
    {
        $GLOBALS['__middag_test_debugging'][] = ['message' => $message, 'level' => $level];

        return true;
    }
}

// Stub: getremoteaddr() — returns $GLOBALS['__middag_test_remoteaddr'] (default '').
if (!function_exists('getremoteaddr')) {
    function getremoteaddr(string $default = '', bool $skipvalidation = false): string
    {
        return $GLOBALS['__middag_test_remoteaddr'] ?? $default;
    }
}

// Stub: sesskey() — returns $GLOBALS['__middag_test_sesskey'] (default 'sesskeytest'),
// so Sesskey::fromCurrent() can be exercised without a Moodle session.
if (!function_exists('sesskey')) {
    // Return type mirrors real Moodle: sesskey() yields false when
    // $_SESSION['USER'] is not set yet (early bootstrap / CLI / webservice).
    function sesskey(): false|string
    {
        return $GLOBALS['__middag_test_sesskey'] ?? 'sesskeytest';
    }
}

// Stub: moodle_exception + core\exception\coding_exception hierarchy. moodle-stubs
// declare these for PHPStan only. SqlGenerator throws coding_exception on bad input.
if (!class_exists('moodle_exception', false)) {
    // Mirrors Moodle's 6-arg constructor ($errorcode, $module, $link, $a,
    // $debuginfo, $previous) so callers that pass debuginfo/previous do not hit
    // Exception's 3-arg ArgumentCountError.
    eval('class moodle_exception extends \Exception { public function __construct($errorcode = "", $module = "", $link = "", $a = null, $debuginfo = null, $previous = null) { parent::__construct((string) $errorcode, 0, $previous instanceof \Throwable ? $previous : null); } }');
}
if (!class_exists('core\exception\coding_exception', false)) {
    eval('namespace core\exception; class coding_exception extends \moodle_exception {}');
}
if (!class_exists('coding_exception', false)) {
    class_alias('core\exception\coding_exception', 'coding_exception');
}

// Stub: moodle_transaction — the delegated-transaction handle returned by
// moodle_database::start_delegated_transaction(). Tests subclass it to record
// commit/rollback or to emulate Moodle's rollback-always-throws behaviour.
if (!class_exists('moodle_transaction', false)) {
    eval('class moodle_transaction { public bool $committed = false; public $rolledback = null; public function allow_commit() { $this->committed = true; } public function rollback($e) { $this->rolledback = $e; } }');
}

// Stub: moodle_recordset — the unbuffered cursor returned by get_recordset_sql().
// Iterates a fixed row set and tracks close() so the adapter\'s finally can be
// asserted.
if (!class_exists('moodle_recordset', false)) {
    eval('class moodle_recordset implements \Iterator { public bool $closed = false; private array $rows; private int $i = 0; public function __construct(array $rows = []) { $this->rows = array_values($rows); } public function current(): mixed { return $this->rows[$this->i]; } public function key(): mixed { return $this->i; } public function next(): void { ++$this->i; } public function rewind(): void { $this->i = 0; } public function valid(): bool { return isset($this->rows[$this->i]); } public function close(): void { $this->closed = true; } }');
}

// Stub: moodle_database — the DML gateway (global $DB). Concrete stand-in whose
// query helpers return deterministic strings and whose DML methods return benign
// defaults; tests subclass or mock (createMock) to control returns. Signatures
// mirror moodle-stubs.
if (!class_exists('moodle_database', false)) {
    eval('class moodle_database {
        public function count_records($table, ?array $conditions = null) { return 0; }
        public function count_records_sql($sql, ?array $params = null) { return 0; }
        public function record_exists($table, array $conditions) { return false; }
        public function get_field($table, $return, array $conditions, $strictness = 0) { return false; }
        public function get_records_select($table, $select, ?array $params = null, $sort = "", $fields = "*", $limitfrom = 0, $limitnum = 0) { return []; }
        public function set_field($table, $newfield, $newvalue, ?array $conditions = null) { return true; }
        public function get_in_or_equal($items, $type = SQL_PARAMS_NAMED, $prefix = "param", $equal = true, $onemptyitems = false) { $items = (array) $items; $params = []; $names = []; $i = 0; foreach ($items as $v) { $k = $prefix . (++$i); $params[$k] = $v; $names[] = ":" . $k; } $op = $equal ? "IN" : "NOT IN"; return [$op . " (" . implode(", ", $names) . ")", $params]; }
        public function sql_compare_text($fieldname, $numchars = 32) { return "CAST(" . $fieldname . " AS TEXT)"; }
        public function sql_like($fieldname, $param, $casesensitive = true, $accentsensitive = true, $notlike = false, $escapechar = "\\\") { return $fieldname . ($notlike ? " NOT LIKE " : " LIKE ") . $param; }
        public function sql_like_escape($text, $escapechar = "\\\") { return $text; }
        public function execute($sql, ?array $params = null) { return true; }
        public function get_record_sql($sql, ?array $params = null, $strictness = 0) { return false; }
        public function get_records_sql($sql, ?array $params = null, $limitfrom = 0, $limitnum = 0) { return []; }
        public function get_record($table, ?array $conditions = null, $fields = "*", $strictness = 0) { return false; }
        public function get_records($table, ?array $conditions = null, $sort = "", $fields = "*", $limitfrom = 0, $limitnum = 0) { return []; }
        public function get_recordset_sql($sql, ?array $params = null, $limitfrom = 0, $limitnum = 0) { return new \moodle_recordset(); }
        public function insert_record($table, $dataobject, $returnid = true, $bulk = false) { return 1; }
        public function update_record($table, $dataobject, $bulk = false) { return true; }
        public function delete_records($table, ?array $conditions = null) { return true; }
        public function start_delegated_transaction() { return new \moodle_transaction(); }
        public function rollback_delegated_transaction($transaction, $e) {}
        public function is_transaction_started() { return false; }
    }');
}

// Stub: core\context alias for the global \context deprecated name (Moodle's
// privacy contract types the arg as global \context; the adapter passes a
// core\context, class-aliased so the two are the same runtime type).
if (!class_exists('context', false)) {
    class_alias('core\context', 'context');
}

// Stub: core_privacy Privacy Subsystem surface. moodle-stubs declare these for
// PHPStan only. collection records add_database_table() calls; the contextlist
// classes and provider interfaces are minimal stand-ins for PrivacyProvider.
if (!class_exists('core_privacy\local\metadata\collection', false)) {
    eval('namespace core_privacy\local\metadata; class collection { public array $tables = []; public function __construct(public $component = null) {} public function add_database_table($name, array $privacyfields, $summary = "") { $this->tables[$name] = ["fields" => $privacyfields, "summary" => $summary]; return $this; } }');
}
if (!interface_exists('core_privacy\local\metadata\provider', false)) {
    eval('namespace core_privacy\local\metadata; interface provider {}');
}
if (!class_exists('core_privacy\local\request\contextlist', false)) {
    eval('namespace core_privacy\local\request; class contextlist {}');
}
if (!class_exists('core_privacy\local\request\approved_contextlist', false)) {
    eval('namespace core_privacy\local\request; class approved_contextlist {}');
}
if (!interface_exists('core_privacy\local\request\plugin\provider', false)) {
    eval('namespace core_privacy\local\request\plugin; interface provider {}');
}

// Stub: core\exception\moodle_exception (namespaced sibling of the global one).
if (!class_exists('core\exception\moodle_exception', false)) {
    eval('namespace core\exception; class moodle_exception extends \moodle_exception {}');
}

// Stub: has_capability() — controlled via $GLOBALS['__middag_test_has_capability']
// (default true). Throws a moodle_exception when $GLOBALS['__middag_test_throw_has_capability']
// is set, so CapabilitySupport's catch/trace branch is reachable.
// CapabilitySupport wraps it for the Output/NavbarService checks.
if (!function_exists('has_capability')) {
    function has_capability($capability, $context, $user = null, $doanything = true): bool
    {
        if (!empty($GLOBALS['__middag_test_throw_has_capability'])) {
            throw new moodle_exception('nopermissions');
        }

        return $GLOBALS['__middag_test_has_capability'] ?? true;
    }
}

// Stub: core\context\system::instance() — the site context. Returns a core\context
// stand-in (SITEID) for capability checks.
if (!class_exists('core\context\system', false)) {
    eval('namespace core\context; class system extends \core\context { public static function instance() { return new \core\context(\SITEID); } }');
}

// Stub: navigation_node. TYPE_CUSTOM is consumed by MoodleView; require_admin_tree()
// is a no-op admin-nav bootstrap hook driven by PageSupport::adminLoadNavigation().
if (!class_exists('navigation_node', false)) {
    eval('class navigation_node { const TYPE_CUSTOM = 50; public static function require_admin_tree() {} }');
}

// Stub: core\output rendering surface. moodle-stubs declare these for PHPStan
// only. plugin_renderer_base/renderer_base/renderable/templatable are minimal
// stand-ins; html_writer exposes the static helpers the Support layer wraps.
if (!class_exists('core\output\renderer_base', false)) {
    eval('namespace core\output; class renderer_base {}');
}
if (!class_exists('core\output\plugin_renderer_base', false)) {
    eval('namespace core\output; abstract class plugin_renderer_base extends renderer_base {}');
}
if (!interface_exists('core\output\renderable', false)) {
    eval('namespace core\output; interface renderable {}');
}
if (!interface_exists('core\output\templatable', false)) {
    eval('namespace core\output; interface templatable {}');
}
if (!class_exists('core\output\html_writer', false)) {
    eval('namespace core\output; class html_writer {
        private static int $idseq = 0;
        public static function random_id($prefix = "") { return $prefix . "auto" . (++self::$idseq); }
        public static function attributes(?array $attributes = null) { $out = ""; foreach ((array) $attributes as $k => $v) { $out .= " " . $k . "=\"" . $v . "\""; } return $out; }
        public static function link($url, $text = "", ?array $attributes = null) { return "<a href=\"" . $url . "\"" . self::attributes($attributes) . ">" . $text . "</a>"; }
        public static function tag($tagname, $contents, ?array $attributes = null) { return "<" . $tagname . self::attributes($attributes) . ">" . $contents . "</" . $tagname . ">"; }
        public static function start_tag($tagname, ?array $attributes = null) { return "<" . $tagname . self::attributes($attributes) . ">"; }
        public static function end_tag($tagname) { return "</" . $tagname . ">"; }
        public static function empty_tag($tagname, ?array $attributes = null) { return "<" . $tagname . self::attributes($attributes) . " />"; }
        public static function nonempty_tag($tagname, $contents, ?array $attributes = null) { if ((string) $contents === "") { return ""; } return self::tag($tagname, $contents, $attributes); }
        public static function attribute($name, $value) { return $name . "=\"" . $value . "\""; }
        public static function img($src, $alt, ?array $attributes = null) { $attributes = (array) $attributes; $attributes["src"] = $src; $attributes["alt"] = $alt; return self::empty_tag("img", $attributes); }
        public static function checkbox($name, $value, $checked = true, $label = "", ?array $attributes = null, ?array $labelattributes = null) { return self::empty_tag("input", ["type" => "checkbox", "name" => $name, "value" => $value]) . $label; }
        public static function select_yes_no($name, $selected = true, ?array $attributes = null) { return self::select(["1" => "Yes", "0" => "No"], $name, $selected ? "1" : "0"); }
        public static function select(array $options, $name, $selected = "", $nothing = ["" => "choosedots"], ?array $attributes = null, array $disabled = []) { $out = self::start_tag("select", ["name" => $name]); foreach ($options as $v => $label) { $out .= self::tag("option", $label, ["value" => $v]); } return $out . self::end_tag("select"); }
        public static function select_time($type, $name, $currenttime = 0, $step = 5, ?array $attributes = null, $timezone = 99) { return self::select([], $name); }
        public static function alist(array $items, ?array $attributes = null, $tag = "ul") { $out = self::start_tag($tag, $attributes); foreach ($items as $item) { $out .= self::tag("li", $item); } return $out . self::end_tag($tag); }
        public static function input_hidden_params($url, ?array $exclude = null) { return ""; }
        public static function script($jscode, $url = null) { return self::tag("script", $jscode); }
        public static function table($table) { return "<table></table>"; }
        public static function label($text, $for, $colonize = true, array $attributes = []) { $attributes["for"] = $for; return self::tag("label", $text, $attributes); }
        public static function div($content, $class = "", ?array $attributes = null) { $attributes = (array) $attributes; if ($class !== "") { $attributes["class"] = $class; } return self::tag("div", $content, $attributes); }
        public static function start_div($class = "", ?array $attributes = null) { $attributes = (array) $attributes; if ($class !== "") { $attributes["class"] = $class; } return self::start_tag("div", $attributes); }
        public static function end_div() { return self::end_tag("div"); }
        public static function span($content, $class = "", ?array $attributes = null) { $attributes = (array) $attributes; if ($class !== "") { $attributes["class"] = $class; } return self::tag("span", $content, $attributes); }
        public static function start_span($class = "", ?array $attributes = null) { $attributes = (array) $attributes; if ($class !== "") { $attributes["class"] = $class; } return self::start_tag("span", $attributes); }
        public static function end_span() { return self::end_tag("span"); }
    }');
}

// XMLDB DDL constants (from lib/xmldb). XmldbSchemaAdapter maps descriptor
// strings onto these; real Moodle values are not load-bearing for the adapter,
// so distinct sentinels suffice.
foreach ([
    'XMLDB_TYPE_INTEGER' => 1, 'XMLDB_TYPE_NUMBER' => 2, 'XMLDB_TYPE_FLOAT' => 3,
    'XMLDB_TYPE_CHAR' => 4, 'XMLDB_TYPE_TEXT' => 5, 'XMLDB_TYPE_BINARY' => 6,
    'XMLDB_KEY_PRIMARY' => 1, 'XMLDB_KEY_UNIQUE' => 2, 'XMLDB_KEY_FOREIGN' => 3,
    'XMLDB_INDEX_UNIQUE' => 1, 'XMLDB_INDEX_NOTUNIQUE' => 0,
    'XMLDB_NOTNULL' => 1, 'XMLDB_SEQUENCE' => 1,
] as $const => $value) {
    if (!defined($const)) {
        define($const, $value);
    }
}

// Stub: xmldb_table/xmldb_field/xmldb_index — XMLDB descriptor objects built by
// XmldbSchemaAdapter. Constructors accept the same positional args; builder
// methods are no-ops (the adapter only assembles them for database_manager).
if (!class_exists('xmldb_table', false)) {
    eval('class xmldb_table { public function __construct(public string $name = "") {} public function setComment($comment) {} public function add_field(...$args) {} public function add_key(...$args) {} public function add_index(...$args) {} }');
}
if (!class_exists('xmldb_field', false)) {
    eval('class xmldb_field { public function __construct(public string $name = "", ...$args) {} }');
}
if (!class_exists('xmldb_index', false)) {
    eval('class xmldb_index { public array $ctor_args; public function __construct(public string $name = "", ...$args) { $this->ctor_args = $args; } }');
}

// Stub: database_manager — the DDL executor. Concrete stand-in so tests can mock
// (createMock) the schema operations XmldbSchemaAdapter delegates to.
if (!class_exists('database_manager', false)) {
    eval('class database_manager { public function create_table($table) {} public function drop_table($table) {} public function add_field($table, $field) {} public function drop_field($table, $field) {} public function add_index($table, $index) {} public function drop_index($table, $index) {} public function table_exists($table) { return false; } public function field_exists($table, $field) { return false; } }');
}

// Stub: admin_setting family. moodle-stubs declare these for PHPStan only. The
// Settings DSL builds native admin_setting_config* instances; a variadic base
// (capturing ctor args + set_updatedcallback) plus thin subclasses cover every
// type the DSL emits without a Moodle runtime.
if (!class_exists('admin_setting', false)) {
    eval('class admin_setting { public array $ctor_args; public $updated_callback = null; public function __construct(...$args) { $this->ctor_args = $args; } public function set_updatedcallback($callback) { $this->updated_callback = $callback; return true; } }');
}
foreach ([
    'admin_setting_configtext', 'admin_setting_configcheckbox', 'admin_setting_configselect',
    'admin_setting_configselect_autocomplete', 'admin_setting_configmultiselect',
    'admin_setting_configmulticheckbox', 'admin_setting_configtextarea',
    'admin_setting_confightmleditor', 'admin_setting_configcolourpicker',
    'admin_setting_configduration', 'admin_setting_configtime', 'admin_setting_configpasswordunmask',
    'admin_setting_configstoredfile', 'admin_setting_configfile', 'admin_setting_configdirectory',
    'admin_setting_configexecutable', 'admin_setting_configiplist', 'admin_setting_configportlist',
    'admin_setting_encryptedpassword', 'admin_setting_description', 'admin_setting_heading',
] as $adminSettingClass) {
    if (!class_exists($adminSettingClass, false)) {
        eval('class ' . $adminSettingClass . ' extends admin_setting {}');
    }
}

// Stub: admin_settingpage — the settings-page grouper. Records the settings added
// via add() so SettingsResolver output can be asserted.
if (!class_exists('admin_settingpage', false)) {
    eval('class admin_settingpage { public array $ctor_args; public array $settings = []; public function __construct(...$args) { $this->ctor_args = $args; } public function add($setting) { $this->settings[] = $setting; return true; } }');
}

// Auto-load per-area Support stubs. Each file guards its definitions with
// !function_exists / !class_exists so the files are order-independent and
// purely additive; this keeps parallel coverage work from colliding on a
// single shared bootstrap edit.
foreach (glob(__DIR__ . '/stubs/support/*.php') ?: [] as $supportStub) {
    require_once $supportStub;
}

// Auto-load per-area stubs for the non-Support coverage areas (Domain, Http,
// Runtime, Security, Statics, Shared, Definition, …). Same doctrine as the
// support stubs above: every definition is guarded with !function_exists /
// !class_exists, so the files are order-independent, purely additive, and a
// symbol is defined exactly once (first glob wins). This gives each area a
// collision-free home for its Moodle stand-ins instead of a shared edit here.
foreach (glob(__DIR__ . '/stubs/areas/*.php') ?: [] as $areaStub) {
    require_once $areaStub;
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
