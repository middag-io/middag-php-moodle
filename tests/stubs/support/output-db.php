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
 * Moodle function/class stubs for the output / html / db / cache / lock Support
 * wrappers. Guarded with !function_exists / !class_exists so the file is
 * order-independent and purely additive (mirrors tests/stubs/support/request.php
 * and config-env.php). Dynamic returns are driven via $GLOBALS['__middag_test_*']
 * so tests stay in control; stubs throw when the matching throw flag is set,
 * letting tests reach the wrappers' catch branches.
 *
 * $OUTPUT and $DB are injected directly as recording doubles by the OutputSupport
 * and DbSupport tests, so no central function/class stub is needed for those two.
 */

// --- HtmlWriterSupport ---
// html_to_text() is the only free function HtmlWriterSupport wraps. The
// core\output\html_writer static helpers it delegates to live on a central
// bootstrap stub that currently exposes only random_id/attributes/link (see the
// batch report's centralStubNeeds). html_table is added here so the (skipped)
// table() test can construct a fixture once the central html_writer::table()
// method lands.

if (!function_exists('html_to_text')) {
    function html_to_text($html, $width = 75, $dolinks = true, $options = null): string
    {
        return $GLOBALS['__middag_test_html_to_text'] ?? ('text:' . $html);
    }
}

if (!class_exists('core_table\output\html_table', false)) {
    eval('namespace core_table\output; class html_table { public array $head = []; public array $data = []; public string $id = ""; }');
}

// --- CacheSupport / CacheSupportPsr16 ---
// core_cache\cache::make() returns a recording cache loader. make() throws when
// __middag_test_cache_make_throws is set (covers CacheSupport::make()'s catch and,
// downstream, every "make() returned null" guard). Each loader operation throws
// when its own flag is set (covers the per-method catch branches) and otherwise
// reads/writes the __middag_test_cache_store backing array.

if (!class_exists('core_cache\cache', false)) {
    eval(<<<'PHP'
        namespace core_cache;

        class cache
        {
            public static function make($component, $area)
            {
                if (!empty($GLOBALS['__middag_test_cache_make_throws'])) {
                    throw new \Exception('cache make failed');
                }

                return new self();
            }

            public function get($key)
            {
                if (!empty($GLOBALS['__middag_test_cache_get_throws'])) {
                    throw new \Exception('cache get failed');
                }
                $store = $GLOBALS['__middag_test_cache_store'] ?? [];

                return \array_key_exists($key, $store) ? $store[$key] : false;
            }

            public function set($key, $value)
            {
                if (!empty($GLOBALS['__middag_test_cache_set_throws'])) {
                    throw new \Exception('cache set failed');
                }
                $GLOBALS['__middag_test_cache_store'][$key] = $value;

                return $GLOBALS['__middag_test_cache_set_result'] ?? true;
            }

            public function has($key)
            {
                if (!empty($GLOBALS['__middag_test_cache_has_throws'])) {
                    throw new \Exception('cache has failed');
                }

                return \array_key_exists($key, $GLOBALS['__middag_test_cache_store'] ?? []);
            }

            public function delete($key)
            {
                if (!empty($GLOBALS['__middag_test_cache_delete_throws'])) {
                    throw new \Exception('cache delete failed');
                }
                unset($GLOBALS['__middag_test_cache_store'][$key]);

                return $GLOBALS['__middag_test_cache_delete_result'] ?? true;
            }

            public function delete_many(array $keys)
            {
                if (!empty($GLOBALS['__middag_test_cache_delete_many_throws'])) {
                    throw new \Exception('cache delete_many failed');
                }
                foreach ($keys as $key) {
                    unset($GLOBALS['__middag_test_cache_store'][$key]);
                }

                // Real contract: the count actually processed (may be < count($keys)).
                return $GLOBALS['__middag_test_cache_delete_many_result'] ?? \count($keys);
            }

            public function get_many(array $keys)
            {
                if (!empty($GLOBALS['__middag_test_cache_get_many_throws'])) {
                    throw new \Exception('cache get_many failed');
                }

                return $GLOBALS['__middag_test_cache_get_many'] ?? [];
            }

            public function set_many(array $keyvalues)
            {
                if (!empty($GLOBALS['__middag_test_cache_set_many_throws'])) {
                    throw new \Exception('cache set_many failed');
                }
                foreach ($keyvalues as $key => $value) {
                    $GLOBALS['__middag_test_cache_store'][$key] = $value;
                }

                // Real contract: the count actually stored (may be < count($keyvalues)).
                return $GLOBALS['__middag_test_cache_set_many_result'] ?? \count($keyvalues);
            }

            public function purge()
            {
                if (!empty($GLOBALS['__middag_test_cache_purge_throws'])) {
                    throw new \Exception('cache purge failed');
                }
                $GLOBALS['__middag_test_cache_store'] = [];

                return true;
            }
        }
        PHP);
}

// --- LockSupport ---
// core\lock\lock_config::get_lock_factory() returns a factory whose get_lock()
// yields a core\lock\lock, false (unavailable), or throws — driven by flags so
// every acquire()/execute()/release() branch is reachable. lock->release()
// records the release and can be made to throw for release()'s catch branch.

if (!class_exists('core\lock\lock', false)) {
    eval(<<<'PHP'
        namespace core\lock;

        class lock
        {
            public bool $released = false;

            public function __construct(public $key = 'resource') {}

            public function release()
            {
                if (!empty($GLOBALS['__middag_test_lock_release_throws'])) {
                    throw new \Exception('lock release failed');
                }
                $this->released = true;
                $GLOBALS['__middag_test_lock_released'] = true;

                return true;
            }
        }
        PHP);
}

if (!class_exists('core\lock\lock_config', false)) {
    eval(<<<'PHP'
        namespace core\lock;

        class lock_config
        {
            public static function get_lock_factory($type)
            {
                if (!empty($GLOBALS['__middag_test_lock_factory_throws'])) {
                    throw new \Exception('lock factory failed');
                }

                return new class {
                    public function get_lock($resource, $timeout, $maxlifetime = 86400)
                    {
                        if (!empty($GLOBALS['__middag_test_lock_get_throws'])) {
                            throw new \Exception('get_lock failed');
                        }

                        $GLOBALS['__middag_test_lock_maxlifetime'] = $maxlifetime;

                        if (!empty($GLOBALS['__middag_test_lock_unavailable'])) {
                            return false;
                        }

                        return new \core\lock\lock($resource);
                    }
                };
            }
        }
        PHP);
}
