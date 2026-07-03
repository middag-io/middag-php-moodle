<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use core_cache\cache as moodle_cache;
use Exception;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Shared\Util\Debug as debug;

/**
 * Cache utility wrapper for Moodle's Cache API.
 *
 * This class centralizes access to Moodle cache to protect the codebase from
 * future architectural changes. All interactions with cache should go through
 * this class to provide a stable, safe API layer.
 *
 * @internal
 */
class CacheSupport
{
    /** @var string Default cache area name for this plugin. */
    public const DEFAULT_CACHE = 'default';

    /**
     * Component name used for cache definitions.
     *
     * Resolved from the composition-root {@see ComponentContext} seam instead of
     * a hard-coded plugin constant, keeping the adapter product-agnostic.
     */
    public static function pluginName(): string
    {
        return ComponentContext::name();
    }

    /**
     * Creates a Moodle cache loader instance for the given area.
     *
     * @param ?string $area      Cache area name defined in db/caches.php.
     * @param ?string $component Plugin component (frankenstyle). Defaults to local_example.
     *
     * @return null|moodle_cache Moodle cache instance or null on failure
     */
    public static function make(?string $area = null, ?string $component = null): ?moodle_cache
    {
        try {
            return moodle_cache::make($component ?? self::pluginName(), $area ?? self::DEFAULT_CACHE);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return null;
        }
    }

    /**
     * Resolves a value via callback and stores it in cache if missing.
     *
     * This method simplifies the common pattern of reading from cache and, if
     * missing, resolving the value via callback and storing it.
     *
     * Note: Moodle Cache API does not support per-item TTL at this level; if you
     * need expiration control, use definitions in db/caches.php or invalidation
     * mechanics via keys/versions.
     *
     * @param string   $key      cache key
     * @param callable $resolver callback that returns the value when not cached
     * @param string   $area     cache area
     *
     * @return mixed value from cache or resolved via callback; false on error
     */
    public static function getOrSet(string $key, callable $resolver, string $area = self::DEFAULT_CACHE): mixed
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }

            $value = $cache->get($key);
            if ($value !== false && $value !== null) {
                return $value;
            }

            // Resolve and store the value
            $value = $resolver();
            $cache->set($key, $value);

            return $value;
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Retrieves a value from the cache.
     *
     * @param string $key  cache key
     * @param string $area Cache area. Defaults to self::DEFAULT_CACHE.
     *
     * @return mixed returns the cached value, null if not found, or false on error
     */
    public static function get(string $key, string $area = self::DEFAULT_CACHE): mixed
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }

            return $cache->get($key);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Stores a value into the cache.
     *
     * @param string $key   cache key
     * @param mixed  $value serializable value to store
     * @param string $area  Cache area. Defaults to self::DEFAULT_CACHE.
     *
     * @return bool True on success, false otherwise
     */
    public static function set(string $key, mixed $value, string $area = self::DEFAULT_CACHE): bool
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }

            return $cache->set($key, $value);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Checks if a key exists in the cache.
     *
     * @param string $key  cache key
     * @param string $area Cache area. Defaults to self::DEFAULT_CACHE.
     *
     * @return bool True if exists, false otherwise (including on error)
     */
    public static function has(string $key, string $area = self::DEFAULT_CACHE): bool
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }

            return $cache->has($key);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Deletes a key from the cache.
     *
     * @param string $key  cache key
     * @param string $area Cache area. Defaults to self::DEFAULT_CACHE.
     *
     * @return bool True on success, false otherwise
     */
    public static function delete(string $key, string $area = self::DEFAULT_CACHE): bool
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }

            return $cache->delete($key);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Deletes multiple keys from the cache at once.
     *
     * @param array  $keys list of cache keys
     * @param string $area Cache area. Defaults to self::DEFAULT_CACHE.
     *
     * @return bool True on success, false otherwise
     */
    public static function deleteMany(array $keys, string $area = self::DEFAULT_CACHE): bool
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }
            $cache->delete_many($keys);

            return true;
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Fetches multiple keys from the cache in a single call.
     *
     * @param array  $keys list of keys to fetch
     * @param string $area Cache area. Defaults to self::DEFAULT_CACHE.
     *
     * @return array|false associative array of key => value (missing keys omitted) or false on error
     */
    public static function getMany(array $keys, string $area = self::DEFAULT_CACHE): array|false
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }

            return $cache->get_many($keys);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Stores multiple key/value pairs into the cache.
     *
     * @param array  $keyvalues associative array of key => value
     * @param string $area      Cache area. Defaults to self::DEFAULT_CACHE.
     *
     * @return bool True on success, false otherwise
     */
    public static function setMany(array $keyvalues, string $area = self::DEFAULT_CACHE): bool
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }
            $cache->set_many($keyvalues);

            return true;
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Purges all entries in the given cache area.
     *
     * @param string $area Cache area. Defaults to self::DEFAULT_CACHE.
     *
     * @return bool True on success, false otherwise
     */
    public static function purge(string $area = self::DEFAULT_CACHE): bool
    {
        try {
            $cache = self::make($area);
            if (!$cache instanceof moodle_cache) {
                return false;
            }
            $cache->purge();

            return true;
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }
}
