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

use Middag\Framework\Kernel\HostContext;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Kernel\MoodleHostContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Coverage complement for {@see MoodleHostContext}: the sibling
 * MoodleHostContextTest exercises the int/uninstalled asset-version paths and
 * the base-path resolution, leaving the non-empty *string* version branch of
 * resolveAssetVersion() uncovered. These tests close that branch (and its
 * neighbours) through the observable resolve() output, without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(MoodleHostContext::class)]
final class MoodleHostContextCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        // Mirror the sibling test's isolation: re-assert the component seam and
        // clear the config/registry globals + framework singleton per test.
        ComponentContext::configure('local_example', 'local_example_autoload');
        unset($GLOBALS['__middag_test_config'], $GLOBALS['__middag_test_component_dir']);
        HostContext::reset();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_config'], $GLOBALS['__middag_test_component_dir']);
        HostContext::reset();
    }

    #[Test]
    public function resolveUsesANonEmptyStringPluginVersionVerbatim(): void
    {
        // A string version (as get_config can yield) is returned as-is — the
        // is_string() && !== '' branch, distinct from the int/float coercion.
        $GLOBALS['__middag_test_config']['version'] = '2024062200';

        self::assertSame('2024062200', MoodleHostContext::resolve()->assetVersion());
    }

    #[Test]
    public function resolveIgnoresAnEmptyStringVersionAndFallsBackToTheStableToken(): void
    {
        // is_string('') is true but the '' !== '' guard fails, so it must not be
        // returned verbatim; it degrades to the stable fallback token.
        $GLOBALS['__middag_test_config']['version'] = '';

        self::assertSame('0', MoodleHostContext::resolve()->assetVersion());
    }

    #[Test]
    public function resolveStringifiesAFloatPluginVersion(): void
    {
        // The is_float() side of the int/float coercion branch.
        $GLOBALS['__middag_test_config']['version'] = 1.5;

        self::assertSame('1.5', MoodleHostContext::resolve()->assetVersion());
    }
}
