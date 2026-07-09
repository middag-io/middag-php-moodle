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

use Middag\Moodle\Runtime\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_component_dir']);
    }

    #[Test]
    public function hostDirectoryResolvesTheComponentDirectoryFromTheRegistry(): void
    {
        $GLOBALS['__middag_test_component_dir'] = __DIR__;

        self::assertSame(__DIR__, Kernel::hostDirectory());
    }

    #[Test]
    public function hostDirectoryThrowsWhenTheRegistryYieldsNoDirectory(): void
    {
        // The core\component stub returns null when unset — the real API's
        // shape for an unknown/uninstalled component.
        unset($GLOBALS['__middag_test_component_dir']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('local_example');

        Kernel::hostDirectory();
    }

    #[Test]
    public function hostDirectoryThrowsWhenTheResolvedPathIsNotADirectory(): void
    {
        $GLOBALS['__middag_test_component_dir'] = __DIR__ . '/does-not-exist';

        $this->expectException(RuntimeException::class);

        Kernel::hostDirectory();
    }
}
