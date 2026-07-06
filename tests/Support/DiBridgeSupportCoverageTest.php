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
    public function testRegisterExportExposesTheServiceId(): void
    {
        DiBridgeSupport::registerExport('svc.x', static fn (): stdClass => new stdClass());
        DiBridgeSupport::registerExport('svc.y', static fn (): stdClass => new stdClass());

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

    private function resetExports(): void
    {
        (new ReflectionProperty(DiBridgeSupport::class, 'exports'))->setValue(null, []);
    }

    private function setMoodleBranch(int $branch): void
    {
        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
        $GLOBALS['CFG'] = (object) ['branch' => $branch];
    }
}
