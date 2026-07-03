<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel;

use core_renderer;
use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Framework\Kernel\Contract\BootstrapInterface;
use Middag\Moodle\Bus\MoodleUserContext;
use Middag\Moodle\Logging\MoodleLogger;
use Middag\Moodle\Output\Contract\ViewAdapterInterface;
use Middag\Moodle\Output\MoodleView;
use Middag\Moodle\Translation\MoodleTranslator;
use Middag\Moodle\Translation\TranslatorInterface;
use moodle_database;
use moodle_page;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Moodle platform bootstrap.
 *
 * Configures the DI container with Moodle-specific synthetic services
 * and platform adapter bindings. Called by ContainerFactory during init.
 */
final class MoodleBootstrap implements BootstrapInterface
{
    public function configure(ContainerBuilder $builder): void
    {
        // Synthetic services — injected at runtime from Moodle globals
        $builder->register('moodle.db', moodle_database::class)->setSynthetic(true);
        $builder->register('moodle.cfg', stdClass::class)->setSynthetic(true);
        $builder->register('moodle.page', moodle_page::class)->setSynthetic(true);
        $builder->register('moodle.output', core_renderer::class)->setSynthetic(true);
        $builder->register('moodle.user', stdClass::class)->setSynthetic(true);

        // Platform adapters → framework contracts
        $builder->register(LoggerInterface::class, MoodleLogger::class)->setPublic(true);
        $builder->register(TranslatorInterface::class, MoodleTranslator::class)->setPublic(true);
        $builder->register(ViewAdapterInterface::class, MoodleView::class)->setPublic(true);

        // Bus adapters → framework contracts
        $builder->register(UserContextResolverInterface::class, MoodleUserContext::class)->setPublic(true);
    }

    public function platform(): string
    {
        return 'moodle';
    }

    public function getProjectRoot(): string
    {
        return '';
    }

    public function getOptions(): array
    {
        return [];
    }
}
