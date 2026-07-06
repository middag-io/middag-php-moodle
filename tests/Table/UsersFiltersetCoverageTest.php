<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Table;

use core_table\local\filter\filterset;
use Middag\Moodle\Table\UsersFilterset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test UsersFilterset.
 *
 * An otherwise-empty abstract subclass of Moodle's filterset; the test confirms
 * it extends the host base (runtime stand-in from tests/bootstrap.php) and is
 * extensible by concrete plugin filtersets.
 *
 * @internal
 */
#[CoversClass(UsersFilterset::class)]
final class UsersFiltersetCoverageTest extends TestCase
{
    #[Test]
    public function extendsMoodleFilterset(): void
    {
        $instance = new class extends UsersFilterset {};

        $this->assertInstanceOf(filterset::class, $instance);
        $this->assertInstanceOf(UsersFilterset::class, $instance);
    }

    #[Test]
    public function isAbstract(): void
    {
        $this->assertTrue((new ReflectionClass(UsersFilterset::class))->isAbstract());
    }
}
