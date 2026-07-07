<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Output\Table;

use core\context;
use core_table\sql_table;
use Middag\Moodle\Output\Table\UsersTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test UsersTable.
 *
 * Abstract base extending Moodle's sql_table; a concrete anonymous subclass
 * exercises the constructor, which forwards $uniqueid to the parent and stores
 * the context. Parent + context are runtime stand-ins from tests/bootstrap.php.
 *
 * @internal
 */
#[CoversClass(UsersTable::class)]
final class UsersTableCoverageTest extends TestCase
{
    #[Test]
    public function constructorForwardsUniqueIdToParent(): void
    {
        $table = $this->makeTable('users-42', new context(7));

        $this->assertInstanceOf(sql_table::class, $table);
        $this->assertSame('users-42', $table->uniqueid);
    }

    #[Test]
    public function constructorStoresContextAsReadonly(): void
    {
        $context = new context(99);

        $table = $this->makeTable('u', $context);

        $ref = new ReflectionClass(UsersTable::class);
        $prop = $ref->getProperty('context');
        $this->assertTrue($prop->isReadOnly());
        $this->assertSame($context, $prop->getValue($table));
    }

    #[Test]
    public function isAbstract(): void
    {
        $this->assertTrue((new ReflectionClass(UsersTable::class))->isAbstract());
    }

    private function makeTable(string $uniqueid, context $context): UsersTable
    {
        return new class($uniqueid, $context) extends UsersTable {};
    }
}
