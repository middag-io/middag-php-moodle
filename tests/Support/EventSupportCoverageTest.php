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

use core\component as core_component;
use core\event\base;
use Middag\Moodle\Domain\Event\EventDto;
use Middag\Moodle\Support\EventSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;

/**
 * @internal
 */
#[CoversClass(EventSupport::class)]
final class EventSupportCoverageTest extends TestCase
{
    /** Absolute path used as $CFG->libdir; its classes/event holds core fixtures. */
    private static string $coreRoot = '';

    private static string $coreEventDir = '';

    /** Absolute path registered as a plugin dir; its classes/event holds a fixture. */
    private static string $pluginRoot = '';

    private static string $pluginEventDir = '';

    private mixed $prevCfg;

    public static function setUpBeforeClass(): void
    {
        self::$coreRoot = sys_get_temp_dir() . '/middag_event_test_' . getmypid();
        self::$coreEventDir = self::$coreRoot . '/classes/event';
        self::$pluginRoot = self::$coreRoot . '/plugin_foo';
        self::$pluginEventDir = self::$pluginRoot . '/classes/event';

        @mkdir(self::$coreEventDir, 0o777, true);
        @mkdir(self::$pluginEventDir, 0o777, true);

        // scanEventFiles() only reads directory entries (never includes them), so
        // the file bodies are irrelevant; the matching event classes are defined
        // below. middag_test_ghost.php has no class → class_exists() gates it out.
        foreach ([
            'middag_test_valid.php',
            'middag_test_noedulevel.php',
            'middag_test_deprecated.php',
            'middag_test_abstract.php',
            'middag_test_notevent.php',
            'middag_test_throwing.php',
            'middag_test_throwname.php',
            'middag_test_ghost.php',
        ] as $file) {
            file_put_contents(self::$coreEventDir . '/' . $file, "<?php\n");
        }

        file_put_contents(self::$pluginEventDir . '/middag_test_plugin.php', "<?php\n");
        // A plugin event file with no backing class → loadPluginEvents() skips it
        // (the `continue` on the invalid-event guard).
        file_put_contents(self::$pluginEventDir . '/middag_test_plugin_ghost.php', "<?php\n");

        self::defineFixtures();
    }

    public static function tearDownAfterClass(): void
    {
        self::removeTree(self::$coreRoot);
    }

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        // DEBUG_NONE keeps Environment::isDevelopment() false (production) unless a
        // test flips $CFG->debug to DEBUG_DEVELOPER.
        $GLOBALS['CFG'] = (object) [
            'libdir' => self::$coreRoot,
            'debug' => DEBUG_NONE,
            'wwwroot' => 'https://moodle.test',
        ];

        $GLOBALS['__middag_test_plugin_types'] = ['mod' => self::$coreRoot];
        $GLOBALS['__middag_test_plugin_list'] = ['mod' => ['foo' => self::$pluginRoot]];
        $GLOBALS['__middag_test_plugin_display'] = ['mod_foo' => 'Foo Module'];
        $GLOBALS['__middag_test_cache_store'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;

        foreach ([
            '__middag_test_plugin_types',
            '__middag_test_plugin_list',
            '__middag_test_plugin_display',
            '__middag_test_cache_store',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    #[Test]
    public function testGetAllEventsReturnsRequestLevelCacheWhenPopulated(): void
    {
        $support = new EventSupport();
        $cached = [new EventDto(fqcn: 'req-cached', displayname: 'Cached', edulevel: 5)];
        (new ReflectionProperty(EventSupport::class, 'cachedEvents'))->setValue($support, $cached);

        // Poison the persistent cache to prove the request-level cache wins.
        $GLOBALS['__middag_test_cache_store'] = ['events' => [new EventDto(fqcn: 'persist', displayname: 'P')]];

        $result = $support->getAllEvents();

        self::assertCount(1, $result);
        self::assertSame('req-cached', $result[0]->fqcn);
    }

    #[Test]
    public function testGetAllEventsReturnsPersistentCacheHitWhenNotDevelopment(): void
    {
        $support = new EventSupport();
        $cached = [new EventDto(fqcn: 'persist-hit', displayname: 'Hit', edulevel: 3)];
        $GLOBALS['__middag_test_cache_store'] = ['events' => $cached];

        $result = $support->getAllEvents();

        self::assertCount(1, $result);
        self::assertSame('persist-hit', $result[0]->fqcn);
    }

    #[Test]
    public function testGetAllEventsLoadsAndCachesOnMiss(): void
    {
        $this->requirePluginTypeStub();

        $support = new EventSupport();
        $GLOBALS['__middag_test_cache_store'] = [];

        $result = $support->getAllEvents();

        $fqcns = array_map(static fn (EventDto $e): string => $e->fqcn, $result);

        self::assertContains('\core\event\middag_test_valid', $fqcns);
        self::assertContains('\mod_foo\event\middag_test_plugin', $fqcns);
        self::assertNotContains('\core\event\middag_test_deprecated', $fqcns);
        self::assertArrayHasKey('events', $GLOBALS['__middag_test_cache_store']);
    }

    #[Test]
    public function testGetAllEventsBypassesPersistentCacheInDevelopment(): void
    {
        $this->requirePluginTypeStub();

        $support = new EventSupport();
        $GLOBALS['CFG']->debug = DEBUG_DEVELOPER;
        $GLOBALS['__middag_test_cache_store'] = ['events' => [new EventDto(fqcn: 'SENTINEL', displayname: 'S')]];

        $result = $support->getAllEvents();

        $fqcns = array_map(static fn (EventDto $e): string => $e->fqcn, $result);

        // Development skips the persistent cache read: the sentinel is ignored and
        // events are loaded from the filesystem instead.
        self::assertNotContains('SENTINEL', $fqcns);
        self::assertContains('\core\event\middag_test_valid', $fqcns);
    }

    #[Test]
    public function testGetEventsByLevelFiltersByEducationLevel(): void
    {
        $support = new EventSupport();
        $events = [
            new EventDto(fqcn: 'a', displayname: 'A', edulevel: base::LEVEL_TEACHING),
            new EventDto(fqcn: 'b', displayname: 'B', edulevel: base::LEVEL_PARTICIPATING),
            new EventDto(fqcn: 'c', displayname: 'C', edulevel: base::LEVEL_TEACHING),
        ];
        (new ReflectionProperty(EventSupport::class, 'cachedEvents'))->setValue($support, $events);

        $teaching = $support->getEventsByLevel(base::LEVEL_TEACHING);
        self::assertCount(2, $teaching);
        // Keys must be reindexed sequentially (dropping 'b' at index 1 must not
        // leave a gap), so the result json_encode()s to an array, not an object.
        self::assertSame([0, 1], array_keys($teaching));

        // Default argument is LEVEL_PARTICIPATING.
        $participating = $support->getEventsByLevel();
        self::assertCount(1, $participating);
        self::assertSame('b', $participating[0]->fqcn);
    }

    #[Test]
    public function testLoadCoreEventsReturnsValidEventsOnly(): void
    {
        $support = new EventSupport();

        /** @var EventDto[] $events */
        $events = $this->invokePrivate($support, 'loadCoreEvents');

        $byFqcn = [];
        foreach ($events as $event) {
            $byFqcn[$event->fqcn] = $event;
        }

        self::assertArrayHasKey('\core\event\middag_test_valid', $byFqcn);
        self::assertArrayHasKey('\core\event\middag_test_noedulevel', $byFqcn);

        // Deprecated/abstract/non-event are filtered by isValidEvent(); the
        // throwing-info event passes isValidEvent() but yields empty static info
        // (getStaticInfoSafe catch) so loadCoreEvents drops it.
        self::assertArrayNotHasKey('\core\event\middag_test_deprecated', $byFqcn);
        self::assertArrayNotHasKey('\core\event\middag_test_abstract', $byFqcn);
        self::assertArrayNotHasKey('\core\event\middag_test_notevent', $byFqcn);
        self::assertArrayNotHasKey('\core\event\middag_test_throwing', $byFqcn);

        self::assertSame(base::LEVEL_TEACHING, $byFqcn['\core\event\middag_test_valid']->edulevel);
        // Static info without an edulevel key falls back to LEVEL_OTHER.
        self::assertSame(base::LEVEL_OTHER, $byFqcn['\core\event\middag_test_noedulevel']->edulevel);
    }

    #[Test]
    public function testLoadCoreEventsSurvivesAThrowingGetName(): void
    {
        $support = new EventSupport();

        // middag_test_throwname has valid static info but a get_name() that
        // throws. It must not abort the whole catalog: the event is kept with
        // its class short name as a fallback, and healthy events still load.
        /** @var EventDto[] $events */
        $events = $this->invokePrivate($support, 'loadCoreEvents');

        $byFqcn = [];
        foreach ($events as $event) {
            $byFqcn[$event->fqcn] = $event;
        }

        self::assertArrayHasKey('\core\event\middag_test_throwname', $byFqcn);
        self::assertSame('middag_test_throwname', $byFqcn['\core\event\middag_test_throwname']->displayname);
        self::assertArrayHasKey('\core\event\middag_test_valid', $byFqcn);
    }

    #[Test]
    public function testLoadPluginEventsReturnsEventsFromRegisteredPlugins(): void
    {
        $this->requirePluginTypeStub();

        $support = new EventSupport();

        /** @var EventDto[] $events */
        $events = $this->invokePrivate($support, 'loadPluginEvents');

        self::assertCount(1, $events);
        $dto = $events[0];

        self::assertSame('\mod_foo\event\middag_test_plugin', $dto->fqcn);
        self::assertSame('mod_foo', $dto->pluginname);
        self::assertSame('mod', $dto->plugintype);
        self::assertSame(base::LEVEL_PARTICIPATING, $dto->edulevel);
        self::assertSame('Foo Module', $dto->plugindisplayname);
    }

    #[Test]
    public function testScanEventFilesReturnsEmptyForMissingDirectory(): void
    {
        $support = new EventSupport();

        self::assertSame([], $this->invokePrivate($support, 'scanEventFiles', ['/no/such/middag/dir']));
    }

    #[Test]
    public function testScanEventFilesStripsPhpExtensionFromEntries(): void
    {
        $support = new EventSupport();

        /** @var string[] $names */
        $names = $this->invokePrivate($support, 'scanEventFiles', [self::$coreEventDir]);

        self::assertContains('middag_test_valid', $names);
        self::assertContains('middag_test_ghost', $names);
        self::assertNotContains('middag_test_valid.php', $names);
        self::assertNotContains('.', $names);
    }

    #[Test]
    public function testIsValidEventCoversEveryGuard(): void
    {
        $support = new EventSupport();

        // class_exists() false — no class backs the ghost file.
        self::assertFalse($this->invokePrivate($support, 'isValidEvent', ['\core\event\middag_test_ghost']));
        // exists but not a subclass of base.
        self::assertFalse($this->invokePrivate($support, 'isValidEvent', ['\core\event\middag_test_notevent']));
        // subclass but deprecated.
        self::assertFalse($this->invokePrivate($support, 'isValidEvent', ['\core\event\middag_test_deprecated']));
        // subclass, not deprecated, but abstract.
        self::assertFalse($this->invokePrivate($support, 'isValidEvent', ['\core\event\middag_test_abstract']));
        // all guards pass.
        self::assertTrue($this->invokePrivate($support, 'isValidEvent', ['\core\event\middag_test_valid']));
    }

    #[Test]
    public function testGetStaticInfoSafeCoversEveryBranch(): void
    {
        $support = new EventSupport();

        // No get_static_info() method → [].
        self::assertSame([], $this->invokePrivate($support, 'getStaticInfoSafe', [stdClass::class]));

        // Method present → its array is returned.
        self::assertSame(
            ['edulevel' => base::LEVEL_TEACHING],
            $this->invokePrivate($support, 'getStaticInfoSafe', ['\core\event\middag_test_valid']),
        );

        // Method throws → caught → [].
        self::assertSame([], $this->invokePrivate($support, 'getStaticInfoSafe', ['\core\event\middag_test_throwing']));
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    private function requirePluginTypeStub(): void
    {
        if (!method_exists(core_component::class, 'get_plugin_types')) {
            self::markTestSkipped('core\component::get_plugin_types() stub is absent (centralStubNeed).');
        }
    }

    /**
     * Define the Moodle event fixture classes exercised by the loaders.
     *
     * They live in the core\event and mod_foo\event namespaces so the loaders'
     * FQCN construction (`\core\event\<name>` and `\{type}_{plugin}\event\<name>`)
     * resolves onto them; the matching directory entries are written above.
     */
    private static function defineFixtures(): void
    {
        if (!class_exists('core\event\middag_test_valid', false)) {
            eval(<<<'PHP'
                namespace core\event;

                class middag_test_valid extends base
                {
                    public static function get_name() { return 'Valid'; }
                    public static function get_static_info() { return ['edulevel' => base::LEVEL_TEACHING]; }
                }

                class middag_test_noedulevel extends base
                {
                    public static function get_name() { return 'No level'; }
                    public static function get_static_info() { return ['note' => 'x']; }
                }

                class middag_test_deprecated extends base
                {
                    public static function is_deprecated() { return true; }
                }

                abstract class middag_test_abstract extends base {}

                class middag_test_notevent {}

                class middag_test_throwing extends base
                {
                    public static function get_static_info() { throw new \RuntimeException('static info failed'); }
                }

                class middag_test_throwname extends base
                {
                    public static function get_name() { throw new \RuntimeException('get_name failed'); }
                    public static function get_static_info() { return ['edulevel' => base::LEVEL_TEACHING]; }
                }
                PHP);
        }

        if (!class_exists('mod_foo\event\middag_test_plugin', false)) {
            eval(<<<'PHP'
                namespace mod_foo\event;

                class middag_test_plugin extends \core\event\base
                {
                    public static function get_name() { return 'Plugin'; }
                    public static function get_static_info() { return ['edulevel' => \core\event\base::LEVEL_PARTICIPATING]; }
                }
                PHP);
        }
    }

    private static function removeTree(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }
            if ($item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                self::removeTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
