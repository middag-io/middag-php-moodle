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

use core_cache\cache as moodle_cache;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\CacheSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CacheSupport wraps Moodle's core_cache\cache. The cache loader is a recording
 * double (tests/stubs/support/output-db.php) driven by $GLOBALS flags: setting a
 * throw flag lets each try/catch branch be reached without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(CacheSupport::class)]
final class CacheSupportCoverageTest extends TestCase
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
    public function testPluginNameReturnsTheConfiguredComponent(): void
    {
        self::assertSame('local_example', CacheSupport::pluginName());
    }

    #[Test]
    public function testMakeReturnsACacheLoader(): void
    {
        self::assertInstanceOf(moodle_cache::class, CacheSupport::make());
    }

    #[Test]
    public function testMakeAcceptsAnExplicitComponentAndArea(): void
    {
        self::assertInstanceOf(moodle_cache::class, CacheSupport::make('mine', 'local_other'));
    }

    #[Test]
    public function testMakeReturnsNullWhenTheCacheApiThrows(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertNull(CacheSupport::make());
    }

    #[Test]
    public function testGetOrSetResolvesAndStoresOnCacheMiss(): void
    {
        $called = false;
        $resolver = function () use (&$called): string {
            $called = true;

            return 'resolved';
        };

        self::assertSame('resolved', CacheSupport::getOrSet('k', $resolver));
        self::assertTrue($called);
        self::assertSame('resolved', $GLOBALS['__middag_test_cache_store']['k']);
    }

    #[Test]
    public function testGetOrSetReturnsCachedValueWithoutInvokingResolver(): void
    {
        $GLOBALS['__middag_test_cache_store']['k'] = 'cached';
        $called = false;

        $value = CacheSupport::getOrSet('k', function () use (&$called): string {
            $called = true;

            return 'fresh';
        });

        self::assertSame('cached', $value);
        self::assertFalse($called);
    }

    #[Test]
    public function testGetOrSetReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::getOrSet('k', fn (): string => 'x'));
    }

    #[Test]
    public function testGetOrSetMemoisesAFalseResolverResult(): void
    {
        // core_cache\cache::get() returns false for both a miss and a stored
        // false, so a naive `!== false` hit test would re-resolve forever. The
        // has()-based check must serve the stored false on the second call.
        $calls = 0;
        $resolver = function () use (&$calls): bool {
            ++$calls;

            return false;
        };

        self::assertFalse(CacheSupport::getOrSet('flag', $resolver));
        self::assertFalse(CacheSupport::getOrSet('flag', $resolver));
        self::assertSame(1, $calls);
    }

    #[Test]
    public function testGetOrSetReturnsFalseWhenACacheOperationThrows(): void
    {
        // On a hit the value is read via get(); a throw there degrades to false.
        $GLOBALS['__middag_test_cache_store']['k'] = 'v';
        $GLOBALS['__middag_test_cache_get_throws'] = true;

        self::assertFalse(CacheSupport::getOrSet('k', fn (): string => 'x'));
    }

    #[Test]
    public function testGetReturnsTheStoredValue(): void
    {
        $GLOBALS['__middag_test_cache_store']['k'] = 'v';

        self::assertSame('v', CacheSupport::get('k'));
    }

    #[Test]
    public function testGetReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::get('k'));
    }

    #[Test]
    public function testGetReturnsFalseWhenTheCacheThrows(): void
    {
        $GLOBALS['__middag_test_cache_get_throws'] = true;

        self::assertFalse(CacheSupport::get('k'));
    }

    #[Test]
    public function testSetStoresTheValueAndReturnsTrue(): void
    {
        self::assertTrue(CacheSupport::set('k', 'v'));
        self::assertSame('v', $GLOBALS['__middag_test_cache_store']['k']);
    }

    #[Test]
    public function testSetReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::set('k', 'v'));
    }

    #[Test]
    public function testSetReturnsFalseWhenTheCacheThrows(): void
    {
        $GLOBALS['__middag_test_cache_set_throws'] = true;

        self::assertFalse(CacheSupport::set('k', 'v'));
    }

    #[Test]
    public function testHasReturnsTrueWhenTheKeyIsPresent(): void
    {
        $GLOBALS['__middag_test_cache_store']['k'] = 'v';

        self::assertTrue(CacheSupport::has('k'));
    }

    #[Test]
    public function testHasReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::has('k'));
    }

    #[Test]
    public function testHasReturnsFalseWhenTheCacheThrows(): void
    {
        $GLOBALS['__middag_test_cache_has_throws'] = true;

        self::assertFalse(CacheSupport::has('k'));
    }

    #[Test]
    public function testDeleteRemovesTheKeyAndReturnsTrue(): void
    {
        $GLOBALS['__middag_test_cache_store']['k'] = 'v';

        self::assertTrue(CacheSupport::delete('k'));
        self::assertArrayNotHasKey('k', $GLOBALS['__middag_test_cache_store']);
    }

    #[Test]
    public function testDeleteReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::delete('k'));
    }

    #[Test]
    public function testDeleteReturnsFalseWhenTheCacheThrows(): void
    {
        $GLOBALS['__middag_test_cache_delete_throws'] = true;

        self::assertFalse(CacheSupport::delete('k'));
    }

    #[Test]
    public function testDeleteManyRemovesTheKeysAndReturnsTrue(): void
    {
        $GLOBALS['__middag_test_cache_store'] = ['a' => 1, 'b' => 2, 'c' => 3];

        self::assertTrue(CacheSupport::deleteMany(['a', 'b']));
        self::assertSame(['c' => 3], $GLOBALS['__middag_test_cache_store']);
    }

    #[Test]
    public function testDeleteManyReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::deleteMany(['a']));
    }

    #[Test]
    public function testDeleteManyReturnsFalseWhenTheCacheThrows(): void
    {
        $GLOBALS['__middag_test_cache_delete_many_throws'] = true;

        self::assertFalse(CacheSupport::deleteMany(['a']));
    }

    #[Test]
    public function testGetManyReturnsTheFoundEntries(): void
    {
        $GLOBALS['__middag_test_cache_get_many'] = ['a' => 1, 'b' => 2];

        self::assertSame(['a' => 1, 'b' => 2], CacheSupport::getMany(['a', 'b']));
    }

    #[Test]
    public function testGetManyReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::getMany(['a']));
    }

    #[Test]
    public function testGetManyReturnsFalseWhenTheCacheThrows(): void
    {
        $GLOBALS['__middag_test_cache_get_many_throws'] = true;

        self::assertFalse(CacheSupport::getMany(['a']));
    }

    #[Test]
    public function testSetManyStoresTheEntriesAndReturnsTrue(): void
    {
        self::assertTrue(CacheSupport::setMany(['a' => 1, 'b' => 2]));
        self::assertSame(1, $GLOBALS['__middag_test_cache_store']['a']);
        self::assertSame(2, $GLOBALS['__middag_test_cache_store']['b']);
    }

    #[Test]
    public function testSetManyReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::setMany(['a' => 1]));
    }

    #[Test]
    public function testSetManyReturnsFalseWhenTheCacheThrows(): void
    {
        $GLOBALS['__middag_test_cache_set_many_throws'] = true;

        self::assertFalse(CacheSupport::setMany(['a' => 1]));
    }

    #[Test]
    public function testPurgeClearsTheAreaAndReturnsTrue(): void
    {
        $GLOBALS['__middag_test_cache_store'] = ['a' => 1];

        self::assertTrue(CacheSupport::purge());
        self::assertSame([], $GLOBALS['__middag_test_cache_store']);
    }

    #[Test]
    public function testPurgeReturnsFalseWhenTheCacheCannotBeMade(): void
    {
        $GLOBALS['__middag_test_cache_make_throws'] = true;

        self::assertFalse(CacheSupport::purge());
    }

    #[Test]
    public function testPurgeReturnsFalseWhenTheCacheThrows(): void
    {
        $GLOBALS['__middag_test_cache_purge_throws'] = true;

        self::assertFalse(CacheSupport::purge());
    }

    private function resetCacheGlobals(): void
    {
        foreach ([
            '__middag_test_cache_store',
            '__middag_test_cache_get_many',
            '__middag_test_cache_set_result',
            '__middag_test_cache_delete_result',
            '__middag_test_cache_make_throws',
            '__middag_test_cache_get_throws',
            '__middag_test_cache_set_throws',
            '__middag_test_cache_has_throws',
            '__middag_test_cache_delete_throws',
            '__middag_test_cache_delete_many_throws',
            '__middag_test_cache_get_many_throws',
            '__middag_test_cache_set_many_throws',
            '__middag_test_cache_purge_throws',
        ] as $key) {
            unset($GLOBALS[$key]);
        }

        $GLOBALS['__middag_test_cache_store'] = [];
    }
}
