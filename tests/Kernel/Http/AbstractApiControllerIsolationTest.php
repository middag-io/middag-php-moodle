<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel\Http;

use PHPUnit\Framework\TestCase;

/**
 * Guards against plugin coupling regressions in the adapter API controller.
 *
 * The adapter must not import classes from the local_middag plugin namespace —
 * doing so inverts the dependency direction (adapter → consumer plugin).
 *
 * Uses source-level inspection rather than class loading to avoid triggering
 * downstream Moodle dependencies during the test run.
 *
 * @internal
 *
 * @coversNothing
 */
final class AbstractApiControllerIsolationTest extends TestCase
{
    private const SOURCE_PATH = __DIR__ . '/../../../src/Kernel/Http/AbstractApiController.php';

    public function testSourceFileExists(): void
    {
        self::assertFileExists(self::SOURCE_PATH);
    }

    public function testSourceHasNoPluginNamespaceImports(): void
    {
        $source = file_get_contents(self::SOURCE_PATH);
        self::assertIsString($source);

        self::assertStringNotContainsString('use local_middag\\', $source, 'Adapter must not import plugin namespace.');
        self::assertStringNotContainsString('local_middag\middag_metadata', $source, 'Plugin metadata constant must not appear.');
        self::assertStringNotContainsString('local_middag\base\api_controller', $source, 'Plugin api_controller base must not appear.');
    }

    public function testSourceDeclaresAdapterNamespace(): void
    {
        $source = file_get_contents(self::SOURCE_PATH);
        self::assertIsString($source);
        self::assertStringContainsString('namespace Middag\Moodle\Kernel\Http;', $source);
        self::assertStringContainsString('abstract class AbstractApiController', $source);
    }
}
