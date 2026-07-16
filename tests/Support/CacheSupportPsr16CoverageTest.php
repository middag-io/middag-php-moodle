<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\CacheSupportPsr16;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CacheSupportPsr16 is a PSR-16 facade over CacheSupport, which reaches the
 * recording cache loader (tests/stubs/support/output-db.php) through the
 * $GLOBALS-driven backing store. Behaviour is asserted end-to-end via the store.
 *
 * @internal
 */
#[CoversClass(CacheSupportPsr16::class)]
final class CacheSupportPsr16CoverageTest extends TestCase
{
    protected function setUp(): void
    {
        ComponentContext::configure('local_example', 'local_example_autoload');
        $this->resetCacheGlobals();
    }

    protected function tearDown(): void
    {
        $this->resetCacheGlobals();
        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    #[Test]
    public function testGetReturnsTheStoredValue(): void
    {
        $GLOBALS['__middag_test_cache_store']['k'] = 'v';

        self::assertSame('v', (new CacheSupportPsr16())->get('k'));
    }

    #[Test]
    public function testGetReturnsTheDefaultWhenTheValueIsMissing(): void
    {
        self::assertSame('fallback', (new CacheSupportPsr16())->get('absent', 'fallback'));
    }

    #[Test]
    public function testGetReturnsAStoredNullNotTheDefault(): void
    {
        // A key explicitly cached as null is a hit (has() is true), so get()
        // returns null — not the default — matching getMultiple() on the same
        // entry (PSR-16 contract).
        $GLOBALS['__middag_test_cache_store']['k'] = null;

        self::assertNull((new CacheSupportPsr16())->get('k', 'fallback'));
    }

    #[Test]
    public function testGetReturnsAStoredFalseNotTheDefault(): void
    {
        $GLOBALS['__middag_test_cache_store']['feature_x'] = false;

        self::assertFalse((new CacheSupportPsr16())->get('feature_x', true));
    }

    #[Test]
    public function testSetStoresTheValueAndReturnsTrue(): void
    {
        $psr = new CacheSupportPsr16('local_area');

        self::assertTrue($psr->set('k', 'v'));
        self::assertSame('v', $GLOBALS['__middag_test_cache_store']['k']);
    }

    #[Test]
    public function testDeleteReturnsTrue(): void
    {
        $GLOBALS['__middag_test_cache_store']['k'] = 'v';

        self::assertTrue((new CacheSupportPsr16())->delete('k'));
        self::assertArrayNotHasKey('k', $GLOBALS['__middag_test_cache_store']);
    }

    #[Test]
    public function testClearPurgesTheAreaAndReturnsTrue(): void
    {
        $GLOBALS['__middag_test_cache_store'] = ['a' => 1];

        self::assertTrue((new CacheSupportPsr16())->clear());
        self::assertSame([], $GLOBALS['__middag_test_cache_store']);
    }

    #[Test]
    public function testGetMultipleMapsFoundValuesAndFallsBackForMissingOrFalseEntries(): void
    {
        // 'a' present with a real value; 'b' present but false; 'c' absent.
        $GLOBALS['__middag_test_cache_get_many'] = ['a' => 1, 'b' => false];

        $result = (new CacheSupportPsr16())->getMultiple(['a', 'b', 'c'], 'def');

        self::assertSame(['a' => 1, 'b' => 'def', 'c' => 'def'], $result);
    }

    #[Test]
    public function testGetMultipleReturnsStoredFalseAndNullConsistentlyWithGet(): void
    {
        // getMultiple() must agree with get() on the same entries: a stored
        // false is a hit (disambiguated via has()), a stored null is a hit,
        // and only a genuine miss falls back to the default.
        $GLOBALS['__middag_test_cache_store'] = ['f' => false, 'n' => null];
        $GLOBALS['__middag_test_cache_get_many'] = ['f' => false, 'n' => null, 'miss' => false];

        $result = (new CacheSupportPsr16())->getMultiple(['f', 'n', 'miss'], 'DEFAULT');

        self::assertFalse($result['f']);
        self::assertNull($result['n']);
        self::assertSame('DEFAULT', $result['miss']);
    }

    #[Test]
    public function testGetMultipleReturnsDefaultsWhenTheLookupFails(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        $result = (new CacheSupportPsr16())->getMultiple(['a', 'b'], 'def');

        self::assertSame(['a' => 'def', 'b' => 'def'], $result);
    }

    #[Test]
    public function testSetMultipleStringifiesKeysAndStoresValues(): void
    {
        self::assertTrue((new CacheSupportPsr16())->setMultiple([0 => 'zero', 'x' => 'ex']));
        self::assertSame('zero', $GLOBALS['__middag_test_cache_store']['0']);
        self::assertSame('ex', $GLOBALS['__middag_test_cache_store']['x']);
    }

    #[Test]
    public function testDeleteMultipleRemovesTheKeysAndReturnsTrue(): void
    {
        $GLOBALS['__middag_test_cache_store'] = ['a' => 1, 'b' => 2];

        self::assertTrue((new CacheSupportPsr16())->deleteMultiple(['a', 'b']));
        self::assertSame([], $GLOBALS['__middag_test_cache_store']);
    }

    #[Test]
    public function testHasReturnsTrueWhenTheKeyIsPresent(): void
    {
        $GLOBALS['__middag_test_cache_store']['k'] = 'v';

        self::assertTrue((new CacheSupportPsr16())->has('k'));
    }

    #[Test]
    public function testHasReturnsFalseWhenTheKeyIsAbsent(): void
    {
        self::assertFalse((new CacheSupportPsr16())->has('absent'));
    }

    private function resetCacheGlobals(): void
    {
        foreach ([
            '__middag_test_cache_store',
            '__middag_test_cache_get_many',
            '__middag_test_cache_make_throws',
        ] as $key) {
            unset($GLOBALS[$key]);
        }

        $GLOBALS['__middag_test_cache_store'] = [];
    }
}
