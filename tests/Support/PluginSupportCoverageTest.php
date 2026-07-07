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

use core\exception\moodle_exception;
use Middag\Moodle\Domain\Platform\Frankenstyle;
use Middag\Moodle\Domain\Platform\PluginDto;
use Middag\Moodle\Support\PluginSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A core_plugin_manager-shaped double. The shared stub (msg-file.php) exposes
 * only plugin_name(), so this double supplies get_plugin_info() and is injected
 * into PluginSupport in place of the real manager.
 *
 * @internal
 */
final readonly class PluginSupportManagerDouble
{
    /**
     * @param array<string, object> $infos component => plugininfo object
     * @param array<string, string> $names component => display name
     */
    public function __construct(
        private array $infos = [],
        private array $names = [],
    ) {}

    public function plugin_name(string $component): string
    {
        return $this->names[$component] ?? $component;
    }

    public function get_plugin_info(string $component): ?object
    {
        return $this->infos[$component] ?? null;
    }
}

/**
 * A core\component-shaped double supplying the plugin-type registry the central
 * bootstrap stub does not, injected as PluginSupport's component class.
 *
 * @internal
 */
final class PluginSupportComponentDouble
{
    /**
     * @return array<string, string>
     */
    public static function get_plugin_types(): array
    {
        return $GLOBALS['__middag_test_plugin_types'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function get_plugin_list(string $type): array
    {
        return $GLOBALS['__middag_test_plugin_list'][$type] ?? [];
    }
}

/**
 * PluginSupport delegates to core_plugin_manager and core\component. The shared
 * test stubs expose only a subset of those APIs, so most tests build the wrapper
 * via newInstanceWithoutConstructor() and inject purpose-built doubles for the
 * manager and component class — exercising the wrapper's own mapping/branch
 * logic. One test uses the real constructor to cover the wiring path.
 *
 * @internal
 */
#[CoversClass(PluginSupport::class)]
final class PluginSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        unset(
            $GLOBALS['__middag_test_plugin_display'],
            $GLOBALS['__middag_test_plugin_types'],
            $GLOBALS['__middag_test_plugin_list'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__middag_test_plugin_display'],
            $GLOBALS['__middag_test_plugin_types'],
            $GLOBALS['__middag_test_plugin_list'],
        );
    }

    #[Test]
    public function testConstructorWiresTheManagerAndDisplaynameDelegatesToIt(): void
    {
        $GLOBALS['__middag_test_plugin_display']['local_example'] = 'Cool Plugin';

        $support = new PluginSupport();

        self::assertSame('Cool Plugin', $support->pluginDisplayname('local', 'example'));
    }

    #[Test]
    public function testGetPluginInfoBuildsADtoFromFullPluginInfo(): void
    {
        $support = $this->makeSupport(new PluginSupportManagerDouble(['local_example' => $this->makeFullInfo()]));

        $dto = $support->getPluginInfo('local', 'example');

        self::assertInstanceOf(PluginDto::class, $dto);
        self::assertSame('local', $dto->type);
        self::assertSame('example', $dto->name);
        self::assertSame('local_example', $dto->component);
        self::assertSame('/var/www/plugins/local/example', $dto->rootdir);
        self::assertSame('Example Plugin', $dto->displayname);
        self::assertSame('std', $dto->source);
        self::assertSame(2024010100, $dto->versiondisk);
        self::assertTrue($dto->enabled);
        self::assertSame('1.0.0', $dto->release);
        self::assertSame('uptodate', $dto->status);
    }

    #[Test]
    public function testGetPluginInfoAppliesDefaultsForSparsePluginInfo(): void
    {
        $support = $this->makeSupport(new PluginSupportManagerDouble(['local_example' => $this->makeSparseInfo()]));

        $dto = $support->getPluginInfo('local', 'example');

        // rootdir falsy -> null; displayname absent -> component fallback;
        // get_status() absent -> null; is_enabled() false.
        self::assertNull($dto->rootdir);
        self::assertSame('local_example', $dto->displayname);
        self::assertNull($dto->source);
        self::assertNull($dto->status);
        self::assertFalse($dto->enabled);
    }

    #[Test]
    public function testGetPluginInfoThrowsWhenTheManagerHasNoInfo(): void
    {
        $support = $this->makeSupport(new PluginSupportManagerDouble());

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Unknown plugin: local_missing');

        $support->getPluginInfo('local', 'missing');
    }

    #[Test]
    public function testIsEnabledReturnsTrueForAnEnabledPlugin(): void
    {
        $support = $this->makeSupport(new PluginSupportManagerDouble(['local_example' => $this->makeFullInfo()]));

        self::assertTrue($support->isEnabled('local', 'example'));
    }

    #[Test]
    public function testIsEnabledReturnsFalseWhenInfoIsMissing(): void
    {
        $support = $this->makeSupport(new PluginSupportManagerDouble());

        // get_plugin_info() -> null short-circuits the `$info &&` guard.
        self::assertFalse($support->isEnabled('local', 'missing'));
    }

    #[Test]
    public function testIsEnabledReturnsFalseForADisabledPlugin(): void
    {
        $support = $this->makeSupport(new PluginSupportManagerDouble(['local_example' => $this->makeSparseInfo()]));

        self::assertFalse($support->isEnabled('local', 'example'));
    }

    #[Test]
    public function testGetPluginInfoByComponentDelegatesToGetPluginInfo(): void
    {
        $support = $this->makeSupport(new PluginSupportManagerDouble(['local_example' => $this->makeFullInfo()]));

        $dto = $support->getPluginInfoByComponent(Frankenstyle::local('example'));

        self::assertSame('local_example', $dto->component);
    }

    #[Test]
    public function testIsEnabledByComponentDelegatesToIsEnabled(): void
    {
        $support = $this->makeSupport(new PluginSupportManagerDouble(['local_example' => $this->makeFullInfo()]));

        self::assertTrue($support->isEnabledByComponent(Frankenstyle::local('example')));
    }

    #[Test]
    public function testGetPluginTypesReturnsTheComponentMap(): void
    {
        $GLOBALS['__middag_test_plugin_types'] = ['local' => '/plugins/local', 'mod' => '/plugins/mod'];
        $support = $this->makeSupport(new PluginSupportManagerDouble());

        self::assertSame(['local' => '/plugins/local', 'mod' => '/plugins/mod'], $support->getPluginTypes());
    }

    #[Test]
    public function testGetPluginsOfTypeReturnsTheList(): void
    {
        $GLOBALS['__middag_test_plugin_list']['local'] = ['example' => '/plugins/local/example'];
        $support = $this->makeSupport(new PluginSupportManagerDouble());

        self::assertSame(['example' => '/plugins/local/example'], $support->getPluginsOfType('local'));
    }

    #[Test]
    public function testPluginExistsReflectsThePresence(): void
    {
        $GLOBALS['__middag_test_plugin_list']['local'] = ['example' => '/plugins/local/example'];
        $support = $this->makeSupport(new PluginSupportManagerDouble());

        self::assertTrue($support->pluginExists('local', 'example'));
        self::assertFalse($support->pluginExists('local', 'absent'));
    }

    #[Test]
    public function testGetPluginDirectoryReturnsThePath(): void
    {
        $GLOBALS['__middag_test_plugin_list']['local'] = ['example' => '/plugins/local/example'];
        $support = $this->makeSupport(new PluginSupportManagerDouble());

        self::assertSame('/plugins/local/example', $support->getPluginDirectory('local', 'example'));
    }

    #[Test]
    public function testGetPluginDirectoryThrowsWhenMissing(): void
    {
        $GLOBALS['__middag_test_plugin_list']['local'] = [];
        $support = $this->makeSupport(new PluginSupportManagerDouble());

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Plugin local_absent not found.');

        $support->getPluginDirectory('local', 'absent');
    }

    #[Test]
    public function testGetAllPluginsGroupsByType(): void
    {
        $GLOBALS['__middag_test_plugin_types'] = ['local' => '/plugins/local'];
        $GLOBALS['__middag_test_plugin_list']['local'] = ['example' => '/plugins/local/example'];
        $support = $this->makeSupport(new PluginSupportManagerDouble(['local_example' => $this->makeFullInfo()]));

        $grouped = $support->getAllPlugins();

        self::assertArrayHasKey('local', $grouped);
        self::assertCount(1, $grouped['local']);
        self::assertSame('local_example', $grouped['local'][0]->component);
    }

    #[Test]
    public function testGetEnabledPluginsFiltersOutDisabled(): void
    {
        $GLOBALS['__middag_test_plugin_types'] = ['local' => '/plugins/local'];
        $GLOBALS['__middag_test_plugin_list']['local'] = ['example' => '/plugins/local/example', 'off' => '/plugins/local/off'];
        $support = $this->makeSupport(new PluginSupportManagerDouble([
            'local_example' => $this->makeFullInfo(),
            'local_off' => $this->makeSparseInfo(),
        ]));

        $enabled = $support->getEnabledPlugins();

        self::assertCount(1, $enabled);
        self::assertSame('local_example', $enabled[0]->component);
    }

    #[Test]
    public function testPluginExistsByComponentDelegates(): void
    {
        $GLOBALS['__middag_test_plugin_list']['local'] = ['example' => '/plugins/local/example'];
        $support = $this->makeSupport(new PluginSupportManagerDouble());

        self::assertTrue($support->pluginExistsByComponent(Frankenstyle::local('example')));
    }

    private function makeSupport(object $manager): PluginSupport
    {
        $reflection = new ReflectionClass(PluginSupport::class);
        $support = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('manager')->setValue($support, $manager);
        $reflection->getProperty('componentClass')->setValue($support, PluginSupportComponentDouble::class);

        return $support;
    }

    private function makeFullInfo(): object
    {
        return new class {
            public $rootdir = '/var/www/plugins/local/example';

            public $displayname = 'Example Plugin';

            public $source = 'std';

            public $versiondisk = 2024010100;

            public $versiondb = 2023010100;

            public $versionrequires = 2022010100;

            public $dependencies = [];

            public $release = '1.0.0';

            public $supported = [401, 405];

            public $incompatible;

            public function is_enabled(): bool
            {
                return true;
            }

            public function get_status(): string
            {
                return 'uptodate';
            }
        };
    }

    private function makeSparseInfo(): object
    {
        return new class {
            public $rootdir = false;

            public function is_enabled(): bool
            {
                return false;
            }
        };
    }
}
