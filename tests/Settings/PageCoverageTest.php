<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Settings;

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Settings\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test Page.
 *
 * Settings-page grouper. resolveId() derives a conventional admin page id from
 * the plugin short name + extension + page name (or uses an explicit id);
 * resolveLabel() resolves the lang key. Plugin defaults to ComponentContext.
 *
 * @internal
 */
#[CoversClass(Page::class)]
final class PageCoverageTest extends TestCase
{
    #[Test]
    public function resolveIdUsesExplicitIdWhenProvided(): void
    {
        $page = new Page('general', id: 'custom_page_id');

        $this->assertSame('custom_page_id', $page->resolveId('core', 'local_example'));
    }

    #[Test]
    public function resolveIdDerivesShortNameFromFrankenstylePlugin(): void
    {
        $page = new Page('general');

        $this->assertSame('example_ecommerce_general', $page->resolveId('ecommerce', 'local_example'));
    }

    #[Test]
    public function resolveIdUsesPluginNameVerbatimWhenNotFrankenstyle(): void
    {
        $page = new Page('general');

        $this->assertSame('singleword_core_general', $page->resolveId('core', 'singleword'));
    }

    #[Test]
    public function resolveIdFallsBackToComponentContextPlugin(): void
    {
        $page = new Page('general');

        // ComponentContext::name() is 'local_example' in the test bootstrap.
        $plugin = (string) ComponentContext::name();
        $expectedShort = substr($plugin, (int) strpos($plugin, '_') + 1);

        $this->assertSame($expectedShort . '_core_general', $page->resolveId('core'));
    }

    #[Test]
    public function resolveLabelUsesExplicitLabelWhenProvided(): void
    {
        $page = new Page('general', label: 'my_label_key');

        $this->assertSame('my_label_key', $page->resolveLabel('core'));
    }

    #[Test]
    public function resolveLabelForCoreExtension(): void
    {
        $page = new Page('general');

        $this->assertSame('settings_page_general', $page->resolveLabel('core'));
    }

    #[Test]
    public function resolveLabelForNonCoreExtension(): void
    {
        $page = new Page('general');

        $this->assertSame('settings_page_ecommerce_general', $page->resolveLabel('ecommerce'));
    }
}
