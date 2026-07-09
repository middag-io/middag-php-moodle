<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Runtime;

use core_renderer;
use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Moodle\Bus\MoodleUserContext;
use Middag\Moodle\Logging\MoodleLogger;
use Middag\Moodle\Output\Contract\ViewAdapterInterface;
use Middag\Moodle\Output\MoodleView;
use Middag\Moodle\Runtime\MoodleBootstrap;
use Middag\Moodle\Translation\MoodleTranslator;
use moodle_database;
use moodle_page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * MoodleBootstrap is the platform BootstrapInterface implementation: it wires
 * Moodle synthetic services and platform adapter bindings into a fresh
 * ContainerBuilder. It is stateless (no singleton, no define(), no global
 * mutation), so each test builds its own bootstrap + container in isolation.
 *
 * ContainerBuilder::register() stores the class as an inert string (::class
 * never autoloads), so configure() runs without a Moodle runtime and the
 * resulting Definition objects can be asserted directly.
 *
 * @internal
 */
#[CoversClass(MoodleBootstrap::class)]
final class MoodleBootstrapCoverageTest extends TestCase
{
    #[Test]
    public function configureRegistersEachSyntheticMoodleServiceWithItsClass(): void
    {
        $bootstrap = new MoodleBootstrap();
        $builder = new ContainerBuilder();

        $bootstrap->configure($builder);

        $synthetics = [
            'moodle.db' => moodle_database::class,
            'moodle.cfg' => stdClass::class,
            'moodle.page' => moodle_page::class,
            'moodle.output' => core_renderer::class,
            'moodle.user' => stdClass::class,
        ];

        foreach ($synthetics as $id => $class) {
            self::assertTrue($builder->hasDefinition($id), 'missing synthetic service ' . $id);
            $definition = $builder->getDefinition($id);
            self::assertTrue($definition->isSynthetic(), $id . ' must be synthetic');
            self::assertSame($class, $definition->getClass());
        }
    }

    #[Test]
    public function configureBindsEachFrameworkContractToItsPublicMoodleAdapter(): void
    {
        $bootstrap = new MoodleBootstrap();
        $builder = new ContainerBuilder();

        $bootstrap->configure($builder);

        $adapters = [
            LoggerInterface::class => MoodleLogger::class,
            TranslatorInterface::class => MoodleTranslator::class,
            ViewAdapterInterface::class => MoodleView::class,
            UserContextResolverInterface::class => MoodleUserContext::class,
        ];

        foreach ($adapters as $contract => $implementation) {
            self::assertTrue($builder->hasDefinition($contract), 'missing binding for ' . $contract);
            $definition = $builder->getDefinition($contract);
            self::assertTrue($definition->isPublic(), $contract . ' binding must be public');
            self::assertSame($implementation, $definition->getClass());
        }
    }

    #[Test]
    public function configureRegistersOnlyTheExpectedServices(): void
    {
        // Five synthetics + four adapter bindings and nothing else. The
        // built-in `service_container` is the only definition a fresh
        // ContainerBuilder already carries, so it is the sole extra id.
        $bootstrap = new MoodleBootstrap();
        $builder = new ContainerBuilder();

        $bootstrap->configure($builder);

        $ids = array_keys($builder->getDefinitions());
        sort($ids);
        $expected = [
            'service_container',
            'moodle.db',
            'moodle.cfg',
            'moodle.page',
            'moodle.output',
            'moodle.user',
            LoggerInterface::class,
            TranslatorInterface::class,
            ViewAdapterInterface::class,
            UserContextResolverInterface::class,
        ];
        sort($expected);

        self::assertSame($expected, $ids);
    }

    #[Test]
    public function platformReturnsTheMoodleIdentifier(): void
    {
        self::assertSame('moodle', (new MoodleBootstrap())->platform());
    }

    #[Test]
    public function getProjectRootReturnsAnEmptyStringBecauseNoServiceDiscoveryIsUsed(): void
    {
        self::assertSame('', (new MoodleBootstrap())->getProjectRoot());
    }

    #[Test]
    public function getOptionsReturnsAnEmptyArray(): void
    {
        self::assertSame([], (new MoodleBootstrap())->getOptions());
    }
}
