<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel;

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\Framework\Kernel\HostContext;
use Middag\Moodle\Kernel\Config\ComponentContext;
use Middag\Moodle\Kernel\MoodleHostContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MoodleHostContext::class)]
final class MoodleHostContextTest extends TestCase
{
    protected function setUp(): void
    {
        // The test bootstrap configures the component seam; re-assert for isolation.
        ComponentContext::configure('local_example', 'local_example_autoload');
        unset($GLOBALS['__middag_test_config'], $GLOBALS['__middag_test_component_dir']);
        HostContext::reset();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_config'], $GLOBALS['__middag_test_component_dir']);
        HostContext::reset();
    }

    // ── DTO accessors (explicit values) ──────────────────────────────────────

    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertInstanceOf(
            HostComponentContextInterface::class,
            new MoodleHostContext('local_example', '2024010100'),
        );
    }

    #[Test]
    public function exposesTheConfiguredValues(): void
    {
        $context = new MoodleHostContext('local_example', '2024010100', '/var/www/moodle/local/example');

        self::assertSame('local_example', $context->componentName());
        self::assertSame('2024010100', $context->assetVersion());
        self::assertSame('/var/www/moodle/local/example', $context->basePath());
    }

    #[Test]
    public function basePathDefaultsToNull(): void
    {
        self::assertNull((new MoodleHostContext('local_example', '2024010100'))->basePath());
    }

    // ── resolve() factory (live Moodle environment) ──────────────────────────

    #[Test]
    public function resolveDelegatesComponentNameToComponentContext(): void
    {
        self::assertSame('local_example', MoodleHostContext::resolve()->componentName());
    }

    #[Test]
    public function resolveReadsAssetVersionFromTheInstalledPluginVersion(): void
    {
        $GLOBALS['__middag_test_config']['version'] = 2024062200;

        self::assertSame('2024062200', MoodleHostContext::resolve()->assetVersion());
    }

    #[Test]
    public function resolveFallsBackToAStableAssetVersionWhenUninstalled(): void
    {
        // No version config set → degrade to the stable fallback, never throw.
        self::assertSame('0', MoodleHostContext::resolve()->assetVersion());
    }

    #[Test]
    public function resolveReadsBasePathFromCoreComponent(): void
    {
        $GLOBALS['__middag_test_component_dir'] = '/var/www/moodle/local/example';

        self::assertSame('/var/www/moodle/local/example', MoodleHostContext::resolve()->basePath());
    }

    #[Test]
    public function resolveDegradesBasePathToNullWhenCoreComponentCannotResolveIt(): void
    {
        // core\component returns null for an unknown/uninstalled component.
        self::assertNull(MoodleHostContext::resolve()->basePath());
    }

    // ── boot wiring round-trip ────────────────────────────────────────────────

    #[Test]
    public function registersThroughHostContextLikeKernelBoot(): void
    {
        // Mirrors Kernel::boot(): HostContext::set(MoodleHostContext::resolve()).
        $GLOBALS['__middag_test_config']['version'] = 2024062200;
        $GLOBALS['__middag_test_component_dir'] = '/var/www/moodle/local/example';

        HostContext::set(MoodleHostContext::resolve());

        $context = HostContext::get();

        self::assertInstanceOf(HostComponentContextInterface::class, $context);
        self::assertSame('local_example', $context->componentName());
        self::assertSame('2024062200', $context->assetVersion());
        self::assertSame('/var/www/moodle/local/example', $context->basePath());
    }
}
