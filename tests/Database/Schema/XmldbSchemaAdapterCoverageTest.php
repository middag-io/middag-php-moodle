<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Database\Schema;

use database_manager;
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Exception\MiddagPersistenceException;
use Middag\Moodle\Database\Schema\XmldbSchemaAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test XmldbSchemaAdapter.
 *
 * Translates descriptor arrays into Moodle xmldb DDL via a database_manager
 * (mocked). xmldb_table/field/index are runtime stand-ins from
 * tests/bootstrap.php; DDL failures are wrapped in MiddagPersistenceException.
 *
 * @internal
 */
#[CoversClass(XmldbSchemaAdapter::class)]
final class XmldbSchemaAdapterCoverageTest extends TestCase
{
    private database_manager&MockObject $dbman;

    private XmldbSchemaAdapter $adapter;

    protected function setUp(): void
    {
        $this->dbman = $this->createMock(database_manager::class);
        $this->adapter = new XmldbSchemaAdapter($this->dbman);
    }

    #[Test]
    public function createTableBuildsFullDescriptorAndDelegates(): void
    {
        $this->dbman->expects($this->once())->method('create_table');

        $this->adapter->createTable([
            'name' => 'mdl_items',
            'comment' => 'MIDDAG items',
            'columns' => [
                ['name' => 'id', 'type' => 'int', 'notnull' => true, 'sequence' => true],
                ['name' => 'title', 'type' => 'char', 'length' => 255],
                ['name' => 'body', 'type' => 'text'],
                ['name' => 'weight', 'type' => 'float'],
                ['name' => 'amount', 'type' => 'number'],
                ['name' => 'blob', 'type' => 'binary'],
            ],
            'keys' => [
                ['name' => 'primary', 'type' => 'primary', 'fields' => ['id']],
                ['name' => 'uq', 'type' => 'unique', 'fields' => ['title']],
                ['name' => 'fk', 'type' => 'foreign', 'fields' => ['ref'], 'reftable' => 'other', 'reffields' => ['id']],
            ],
            'indexes' => [
                ['name' => 'ix_u', 'type' => 'x', 'unique' => true, 'fields' => ['title']],
                ['name' => 'ix_n', 'type' => 'x', 'fields' => ['body']],
            ],
        ]);
    }

    #[Test]
    public function createTableWorksWithMinimalDescriptor(): void
    {
        $this->dbman->expects($this->once())->method('create_table');

        $this->adapter->createTable(['name' => 'mdl_bare']);
    }

    #[Test]
    public function createTableWrapsFailuresInPersistenceException(): void
    {
        $this->dbman->method('create_table')->willThrowException(new RuntimeException('ddl'));

        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('mdl_items');

        $this->adapter->createTable(['name' => 'mdl_items']);
    }

    #[Test]
    public function createTableRejectsUnknownColumnType(): void
    {
        $this->expectException(MiddagPersistenceException::class);

        $this->adapter->createTable([
            'name' => 'mdl_items',
            'columns' => [['name' => 'x', 'type' => 'geometry']],
        ]);
    }

    #[Test]
    public function createTableRejectsUnknownKeyType(): void
    {
        $this->expectException(MiddagPersistenceException::class);

        $this->adapter->createTable([
            'name' => 'mdl_items',
            'keys' => [['name' => 'k', 'type' => 'spatial', 'fields' => ['x']]],
        ]);
    }

    #[Test]
    public function dropTableDelegates(): void
    {
        $this->dbman->expects($this->once())->method('drop_table');

        $this->adapter->dropTable('mdl_items');
    }

    #[Test]
    public function dropTableWrapsFailures(): void
    {
        $this->dbman->method('drop_table')->willThrowException(new RuntimeException('x'));

        $this->expectException(MiddagPersistenceException::class);

        $this->adapter->dropTable('mdl_items');
    }

    #[Test]
    public function addColumnDelegates(): void
    {
        $this->dbman->expects($this->once())->method('add_field');

        $this->adapter->addColumn('mdl_items', ['name' => 'new', 'type' => 'int']);
    }

    #[Test]
    public function addColumnWrapsFailures(): void
    {
        $this->dbman->method('add_field')->willThrowException(new RuntimeException('x'));

        $this->expectException(MiddagPersistenceException::class);

        $this->adapter->addColumn('mdl_items', ['name' => 'new', 'type' => 'int']);
    }

    #[Test]
    public function dropColumnDelegates(): void
    {
        $this->dbman->expects($this->once())->method('drop_field');

        $this->adapter->dropColumn('mdl_items', 'old');
    }

    #[Test]
    public function dropColumnWrapsFailures(): void
    {
        $this->dbman->method('drop_field')->willThrowException(new RuntimeException('x'));

        $this->expectException(MiddagPersistenceException::class);

        $this->adapter->dropColumn('mdl_items', 'old');
    }

    #[Test]
    public function addIndexDelegates(): void
    {
        $this->dbman->expects($this->once())->method('add_index');

        $this->adapter->addIndex('mdl_items', ['name' => 'ix', 'unique' => true, 'fields' => ['a']]);
    }

    #[Test]
    public function addIndexWrapsFailures(): void
    {
        $this->dbman->method('add_index')->willThrowException(new RuntimeException('x'));

        $this->expectException(MiddagPersistenceException::class);

        $this->adapter->addIndex('mdl_items', ['name' => 'ix', 'fields' => ['a']]);
    }

    #[Test]
    public function dropIndexDelegates(): void
    {
        $this->dbman->expects($this->once())->method('drop_index');

        $this->adapter->dropIndex('mdl_items', 'ix');
    }

    #[Test]
    public function dropIndexWrapsFailures(): void
    {
        $this->dbman->method('drop_index')->willThrowException(new RuntimeException('x'));

        $this->expectException(MiddagPersistenceException::class);

        $this->adapter->dropIndex('mdl_items', 'ix');
    }

    #[Test]
    public function tableExistsDelegatesToDbman(): void
    {
        $this->dbman->method('table_exists')->willReturn(true);

        $this->assertTrue($this->adapter->tableExists('mdl_items'));
    }

    #[Test]
    public function columnExistsDelegatesToDbman(): void
    {
        $this->dbman->method('field_exists')->willReturn(false);

        $this->assertFalse($this->adapter->columnExists('mdl_items', 'ghost'));
    }

    #[Test]
    public function implementsSchemaBuilderAdapterInterface(): void
    {
        $this->assertInstanceOf(SchemaBuilderAdapterInterface::class, $this->adapter);
    }
}
