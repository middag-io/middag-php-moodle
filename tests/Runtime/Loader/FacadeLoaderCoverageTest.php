<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Runtime\Loader;

use FilesystemIterator;
use Middag\Moodle\Runtime\Loader\FacadeLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;

/**
 * FacadeLoader scans a host plugin's facade/ and extensions/ trees and returns a
 * ShortName => FQCN map. It is exercised without a Moodle runtime by pointing the
 * loader at a temporary host root whose *.php filenames resolve to the fixture
 * facade classes declared in tests/stubs/areas/kernel-facadeloader.php (component
 * = local_example, configured by the bootstrap). Cache reads/writes go through
 * the core_cache\cache bootstrap double, backed by
 * $GLOBALS['__middag_test_cache_store'].
 *
 * @internal
 */
#[CoversClass(FacadeLoader::class)]
final class FacadeLoaderCoverageTest extends TestCase
{
    private const CACHE_KEY = 'facades';

    private string $root;

    private mixed $prevComponentDir;

    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/middag-facadeloader-' . uniqid('', true);
        mkdir($this->root, 0o777, true);

        $this->prevComponentDir = $GLOBALS['__middag_test_component_dir'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        // Start every test from a clean, empty cache backing store so the
        // production cache-read branch sees a miss unless the test seeds it.
        $GLOBALS['__middag_test_cache_store'] = [];
        unset($GLOBALS['__middag_test_component_dir']);
    }

    protected function tearDown(): void
    {
        // Restore the component-dir global.
        if ($this->prevComponentDir === null) {
            unset($GLOBALS['__middag_test_component_dir']);
        } else {
            $GLOBALS['__middag_test_component_dir'] = $this->prevComponentDir;
        }

        // Restore $CFG (the dev-mode test mutates it).
        if ($this->prevCfg === null) {
            unset($GLOBALS['CFG']);
        } else {
            $GLOBALS['CFG'] = $this->prevCfg;
        }

        unset($GLOBALS['__middag_test_cache_store']);
        $this->deleteDir($this->root);
    }

    // ------------------------------------------------------------------
    // Empty host root — every scan short-circuits, result is an empty map.
    // ------------------------------------------------------------------

    #[Test]
    public function testGetDefinitionsReturnsEmptyMapWhenNothingToScan(): void
    {
        // No facade/ dir (scanDirectory is_dir guard) and no extensions/ dir
        // (both extension blocks skipped). Cache is empty → miss → scan → store.
        $loader = new FacadeLoader($this->root);

        self::assertSame([], $loader->getDefinitions());
        // The empty result is written back to the cache (CacheSupport::set).
        self::assertSame([], $GLOBALS['__middag_test_cache_store'][self::CACHE_KEY] ?? null);
    }

    // ------------------------------------------------------------------
    // Full discovery — core + suffix facades, with the abstract /
    // missing-class / non-facade / non-file entries all filtered.
    // ------------------------------------------------------------------

    #[Test]
    public function testGetDefinitionsDiscoversAndFiltersAcrossBothShapes(): void
    {
        // Core facades: local_example\facade\*
        $this->writeFile('/facade/CovAlphaFacade.php');       // concrete → kept
        $this->writeFile('/facade/CovAbstractFacade.php');    // abstract → filtered
        $this->writeFile('/facade/CovGhost.php');             // no class → filtered

        // Suffix facades: *_facade.php anywhere under extensions/
        $this->writeFile('/extensions/covext/deep/cov_suffix_facade.php');          // concrete → kept
        $this->writeFile('/extensions/covext/deep/cov_abstract_suffix_facade.php'); // abstract → filtered
        $this->writeFile('/extensions/covext/deep/ghost_facade.php');               // no class → filtered
        $this->writeFile('/extensions/covext/deep/regular.php');                    // not *_facade.php → skipped

        // A broken symlink is a non-file leaf, exercising the isFile() guard in
        // the recursive suffix scan. Best-effort: platforms without symlink
        // support simply lose that one line, never a false assertion.
        @symlink($this->root . '/does-not-exist-target', $this->root . '/extensions/covext/broken_link');

        $loader = new FacadeLoader($this->root);
        $map = $loader->getDefinitions();

        // Kept (concrete) facades — one per shape.
        self::assertArrayHasKey('CovAlphaFacade', $map);
        self::assertSame('local_example\facade\CovAlphaFacade', $map['CovAlphaFacade']);

        self::assertArrayHasKey('cov_suffix_facade', $map);
        self::assertSame('local_example\extensions\covext\deep\cov_suffix_facade', $map['cov_suffix_facade']);

        // Filtered entries.
        self::assertArrayNotHasKey('CovAbstractFacade', $map);
        self::assertArrayNotHasKey('CovGhost', $map);
        self::assertArrayNotHasKey('cov_abstract_suffix_facade', $map);
        self::assertArrayNotHasKey('ghost_facade', $map);
        self::assertArrayNotHasKey('regular', $map);

        self::assertCount(2, $map);

        // The freshly discovered map is written to the cache.
        self::assertSame($map, $GLOBALS['__middag_test_cache_store'][self::CACHE_KEY] ?? null);
    }

    // ------------------------------------------------------------------
    // Production cache HIT — a cached array short-circuits discovery.
    // ------------------------------------------------------------------

    #[Test]
    public function testGetDefinitionsReturnsCachedMapWithoutScanning(): void
    {
        $cached = ['Cached' => 'some\cached\Facade'];
        $GLOBALS['__middag_test_cache_store'][self::CACHE_KEY] = $cached;

        // Even though the host root holds a real, discoverable facade, the cache
        // hit must win and no scan must run.
        $this->writeFile('/facade/CovAlphaFacade.php');

        $loader = new FacadeLoader($this->root);

        self::assertSame($cached, $loader->getDefinitions());
    }

    // ------------------------------------------------------------------
    // Production cache with a non-array payload — the is_array() guard rejects
    // it and discovery proceeds (then overwrites the corrupt entry).
    // ------------------------------------------------------------------

    #[Test]
    public function testGetDefinitionsIgnoresNonArrayCachePayload(): void
    {
        $GLOBALS['__middag_test_cache_store'][self::CACHE_KEY] = 'corrupt-not-an-array';

        $loader = new FacadeLoader($this->root);

        // Empty host root → scan yields []; the corrupt cache value is discarded.
        self::assertSame([], $loader->getDefinitions());
        self::assertSame([], $GLOBALS['__middag_test_cache_store'][self::CACHE_KEY] ?? null);
    }

    // ------------------------------------------------------------------
    // Development mode — the cache read is skipped entirely, so a stale cached
    // value is never returned; discovery always runs.
    // ------------------------------------------------------------------

    #[Test]
    public function testDevelopmentModeSkipsCacheAndAlwaysScans(): void
    {
        // Force Environment::isDevelopment() true via the Moodle-native signal.
        $GLOBALS['CFG'] = new stdClass();
        $GLOBALS['CFG']->middag_env = 'development';

        // Seed a stale cache value that MUST be ignored in development.
        $GLOBALS['__middag_test_cache_store'][self::CACHE_KEY] = ['Stale' => 'stale\Facade'];

        $this->writeFile('/facade/CovAlphaFacade.php');

        $loader = new FacadeLoader($this->root);
        $map = $loader->getDefinitions();

        // Result reflects the live scan, not the stale cache.
        self::assertArrayHasKey('CovAlphaFacade', $map);
        self::assertArrayNotHasKey('Stale', $map);
    }

    // ------------------------------------------------------------------
    // Null projectRoot — the host directory is resolved through
    // Kernel::hostDirectory() (the component registry stub).
    // ------------------------------------------------------------------

    #[Test]
    public function testNullProjectRootResolvesThroughKernelHostDirectory(): void
    {
        $GLOBALS['__middag_test_component_dir'] = $this->root;
        $this->writeFile('/facade/CovAlphaFacade.php');

        // No projectRoot injected → root() falls back to Kernel::hostDirectory().
        $loader = new FacadeLoader();
        $map = $loader->getDefinitions();

        self::assertArrayHasKey('CovAlphaFacade', $map);
        self::assertSame('local_example\facade\CovAlphaFacade', $map['CovAlphaFacade']);
    }

    // ------------------------------------------------------------------
    // load() — the void discovery trigger. Observable effect: the discovered
    // map is written to the cache backing store.
    // ------------------------------------------------------------------

    #[Test]
    public function testLoadTriggersDiscoveryAndPopulatesCache(): void
    {
        $this->writeFile('/facade/CovAlphaFacade.php');

        $loader = new FacadeLoader($this->root);
        $loader->load();

        $stored = $GLOBALS['__middag_test_cache_store'][self::CACHE_KEY] ?? null;
        self::assertIsArray($stored);
        self::assertArrayHasKey('CovAlphaFacade', $stored);
        self::assertSame('local_example\facade\CovAlphaFacade', $stored['CovAlphaFacade']);
    }

    /**
     * Write an empty PHP file (creating parent dirs) at a path relative to the
     * temp host root. The loader only reads the filename, never the contents.
     */
    private function writeFile(string $relative): void
    {
        $path = $this->root . $relative;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($path, "<?php\n");
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($dir);
    }
}
