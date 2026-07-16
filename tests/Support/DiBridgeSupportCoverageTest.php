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

use Middag\Moodle\Support\DiBridgeSupport;
use Middag\Moodle\Support\VersionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
#[CoversClass(DiBridgeSupport::class)]
final class DiBridgeSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->resetExports();
    }

    protected function tearDown(): void
    {
        $this->resetExports();
        $GLOBALS['CFG'] = $this->prevCfg;

        // Force VersionSupport to re-read $CFG on its next use.
        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
    }

    #[Test]
    public function testGetExportedServiceIdsIsEmptyUntilConfigured(): void
    {
        // registerExport() only records locally; core\di::get() cannot resolve
        // the id until configure() pushes it into Moodle's DI builder, so the
        // getter must not report it as exposed yet.
        DiBridgeSupport::registerExport('svc.x', static fn (): stdClass => new stdClass());

        self::assertSame([], DiBridgeSupport::getExportedServiceIds());
    }

    #[Test]
    public function testGetExportedServiceIdsListsIdsAfterConfigure(): void
    {
        DiBridgeSupport::registerExport('svc.x', static fn (): stdClass => new stdClass());
        DiBridgeSupport::registerExport('svc.y', static fn (): stdClass => new stdClass());

        $hook = new class {
            public function add_definition(string $id, callable $factory): void {}
        };
        DiBridgeSupport::configure($hook);

        self::assertSame(['svc.x', 'svc.y'], DiBridgeSupport::getExportedServiceIds());
    }

    #[Test]
    public function testIsAvailableReturnsTrueOnSupportedMoodle(): void
    {
        $this->setMoodleBranch(404);

        self::assertTrue(DiBridgeSupport::isAvailable());
    }

    #[Test]
    public function testIsAvailableReturnsFalseOnUnsupportedMoodle(): void
    {
        $this->setMoodleBranch(403);

        self::assertFalse(DiBridgeSupport::isAvailable());
    }

    #[Test]
    public function testConfigureRegistersExportsIntoTheHook(): void
    {
        $factory = static fn (): stdClass => new stdClass();
        DiBridgeSupport::registerExport('svc.a', $factory);

        $hook = new class {
            /** @var array<string, callable> */
            public array $defs = [];

            public function add_definition(string $id, callable $factory): void
            {
                $this->defs[$id] = $factory;
            }
        };

        DiBridgeSupport::configure($hook);

        self::assertArrayHasKey('svc.a', $hook->defs);
    }

    #[Test]
    public function testConfigureSwallowsHookErrors(): void
    {
        DiBridgeSupport::registerExport('svc.b', static fn (): stdClass => new stdClass());

        $hook = new class {
            public bool $called = false;

            public function add_definition(string $id, callable $factory): never
            {
                $this->called = true;

                throw new RuntimeException('hook rejected definition');
            }
        };

        // Must not propagate: the loop body ran (called === true) and the
        // Throwable was caught + traced rather than surfaced to the caller.
        DiBridgeSupport::configure($hook);

        self::assertTrue($hook->called);
    }

    #[Test]
    public function testConfigureIsolatesAFailingExportPerId(): void
    {
        // One export the DI builder rejects must not abort the registration
        // of every export after it in iteration order — and the rejected id
        // must not be reported as exposed.
        DiBridgeSupport::registerExport('svc.bad', static fn (): stdClass => new stdClass());
        DiBridgeSupport::registerExport('svc.good', static fn (): stdClass => new stdClass());

        $hook = new class {
            /** @var array<string, callable> */
            public array $defs = [];

            public function add_definition(string $id, callable $factory): void
            {
                if ($id === 'svc.bad') {
                    throw new RuntimeException('hook rejected definition');
                }
                $this->defs[$id] = $factory;
            }
        };

        DiBridgeSupport::configure($hook);

        self::assertArrayHasKey('svc.good', $hook->defs);
        self::assertSame(['svc.good'], DiBridgeSupport::getExportedServiceIds());
    }

    private function resetExports(): void
    {
        (new ReflectionProperty(DiBridgeSupport::class, 'exports'))->setValue(null, []);
        (new ReflectionProperty(DiBridgeSupport::class, 'exportedIds'))->setValue(null, []);
        (new ReflectionProperty(DiBridgeSupport::class, 'configured'))->setValue(null, false);
    }

    private function setMoodleBranch(int $branch): void
    {
        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
        $GLOBALS['CFG'] = (object) ['branch' => $branch];
    }
}
