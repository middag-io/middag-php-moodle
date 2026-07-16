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

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 adapter over {@see CacheSupport}, scoped to a single Moodle cache area.
 *
 * Allows platform-agnostic framework collaborators to consume Moodle's MUC via
 * {@see CacheInterface} without depending on Moodle-specific statics or the
 * `(key, area)` calling convention.
 *
 * TTL parameters are accepted for interface compatibility but ignored — Moodle's
 * cache API does not expose per-item TTL at this layer (see CacheSupport docs).
 *
 * @api
 */
final readonly class CacheSupportPsr16 implements CacheInterface
{
    public function __construct(private string $area = CacheSupport::DEFAULT_CACHE) {}

    public function get(string $key, mixed $default = null): mixed
    {
        // Only substitute the default on a genuine miss. CacheSupport::get()
        // returns bare false both for a miss and for a stored false, so has()
        // disambiguates — a value explicitly cached as false or null must be
        // returned as-is (PSR-16 CacheInterface::get contract).
        if (!CacheSupport::has($key, $this->area)) {
            return $default;
        }

        return CacheSupport::get($key, $this->area);
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return CacheSupport::set($key, $value, $this->area);
    }

    public function delete(string $key): bool
    {
        return CacheSupport::delete($key, $this->area);
    }

    public function clear(): bool
    {
        return CacheSupport::purge($this->area);
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $list = [];
        foreach ($keys as $key) {
            $list[] = $key;
        }

        $found = CacheSupport::getMany($list, $this->area);
        if ($found === false) {
            $found = [];
        }

        $result = [];
        foreach ($list as $key) {
            if (!array_key_exists($key, $found)) {
                $result[$key] = $default;

                continue;
            }

            $value = $found[$key];

            // get_many() returns false for a miss, but a genuinely stored false
            // is indistinguishable here — disambiguate with has() so get() and
            // getMultiple() agree on the same entry. A stored null is
            // unambiguous (misses come back as false, not null) and is a hit.
            if ($value === false && !CacheSupport::has($key, $this->area)) {
                $result[$key] = $default;

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     * @param null|DateInterval|int   $ttl
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $kv = [];
        foreach ($values as $k => $v) {
            $kv[(string) $k] = $v;
        }

        return CacheSupport::setMany($kv, $this->area);
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $list = [];
        foreach ($keys as $key) {
            $list[] = $key;
        }

        return CacheSupport::deleteMany($list, $this->area);
    }

    public function has(string $key): bool
    {
        return CacheSupport::has($key, $this->area);
    }
}
