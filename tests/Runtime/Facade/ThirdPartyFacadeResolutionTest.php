<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Runtime\Facade;

use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Kernel\HostContext;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Runtime\ContainerFactory;
use Middag\Moodle\Runtime\Facade\AbstractFacade;
use Middag\Moodle\Runtime\Kernel;
use Middag\Moodle\Tests\Runtime\Facade\Fixture\ThirdPartyGreeter;
use Middag\Moodle\Tests\Runtime\Facade\Fixture\ThirdPartyGreeterFacade;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * D-FACADE-SEAM proof (LB-MDL-FACADE-01): the facade mechanism is
 * OSS-generic. A THIRD-PARTY plugin built on adapter + framework only —
 * no middag-io/core anywhere — supplies its own container builder through
 * the empty seam (ContainerFactory::setBuilder), registers its own service,
 * and its facade resolves end-to-end via Kernel::get() out of THAT
 * container (not a runtime-instance override).
 *
 * @internal
 */
#[CoversClass(AbstractFacade::class)]
#[CoversClass(ContainerFactory::class)]
final class ThirdPartyFacadeResolutionTest extends TestCase
{
    private mixed $prevCfg = null;

    /** @var array<string, mixed> */
    private array $prevServer = [];

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevServer = $_SERVER;

        $this->resetKernelWorld();

        $GLOBALS['CFG'] = (object) [
            'dirroot' => sys_get_temp_dir(),
            'wwwroot' => 'https://moodle.test',
        ];

        $_SERVER['HTTP_HOST'] = 'moodle.test';
        $_SERVER['SERVER_NAME'] = 'moodle.test';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_URI'] = '/local/example/index.php/api/ping';
        $_SERVER['SCRIPT_NAME'] = '/local/example/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->resetKernelWorld();

        $GLOBALS['CFG'] = $this->prevCfg;
        $_SERVER = $this->prevServer;
    }

    #[Test]
    public function thirdPartyFacadeResolvesThroughItsOwnBuilderWithoutCore(): void
    {
        // The "plugin bootstrap": the third party supplies the composition
        // root through the empty seam — exactly what local_middag does, but
        // with zero core involvement.
        ContainerFactory::setBuilder(static function (): ContainerBuilder {
            $builder = new ContainerBuilder();
            $builder->register(ThirdPartyGreeter::class, ThirdPartyGreeter::class)->setPublic(true);

            return $builder;
        });

        Kernel::init();

        self::assertSame('hello, world', ThirdPartyGreeterFacade::greet('world'));
    }

    #[Test]
    public function facadeRootComesFromTheProductContainerAndIsCached(): void
    {
        ContainerFactory::setBuilder(static function (): ContainerBuilder {
            $builder = new ContainerBuilder();
            $builder->register(ThirdPartyGreeter::class, ThirdPartyGreeter::class)->setPublic(true);

            return $builder;
        });

        Kernel::init();

        $root = ThirdPartyGreeterFacade::getFacadeRoot();

        self::assertInstanceOf(ThirdPartyGreeter::class, $root);
        // Same container-owned instance on subsequent resolutions (cache on).
        self::assertSame($root, ThirdPartyGreeterFacade::getFacadeRoot());
        self::assertSame($root, Kernel::get(ThirdPartyGreeter::class));
    }

    /**
     * Mirrors the kernel test recipe: reset the singleton, the factory seam,
     * host context, Inertia registry, the facade instance cache and the
     * composition-root component so no global state leaks (failOnRisky).
     */
    private function resetKernelWorld(): void
    {
        Kernel::shutdown();

        $factory = new ReflectionClass(ContainerFactory::class);
        $factory->setStaticPropertyValue('container', null);
        $factory->setStaticPropertyValue('builder', null);
        $factory->setStaticPropertyValue('resetCallbacks', []);

        (new ReflectionClass(Kernel::class))->setStaticPropertyValue('failedExtensions', []);

        HostContext::reset();
        InertiaManager::flush();
        ThirdPartyGreeterFacade::reset();

        ComponentContext::configure('local_example', 'local_example_autoload');
    }
}
