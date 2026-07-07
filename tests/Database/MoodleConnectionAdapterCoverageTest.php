<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Database;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Database\Enum\Capability;
use Middag\Moodle\Database\MoodleConnectionAdapter;
use Middag\Moodle\Database\MoodleSqlDialect;
use moodle_database;
use moodle_recordset;
use moodle_transaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Test MoodleConnectionAdapter.
 *
 * Wraps the global $DB behind the framework ConnectionAdapterInterface. $DB is a
 * mocked moodle_database (stubbed class from tests/bootstrap.php); the adapter
 * converts stdClass rows to arrays and strips {braces} from logical table names.
 *
 * @internal
 */
#[CoversClass(MoodleConnectionAdapter::class)]
final class MoodleConnectionAdapterCoverageTest extends TestCase
{
    private MockObject&moodle_database $db;

    private MoodleConnectionAdapter $adapter;

    protected function setUp(): void
    {
        $this->db = $this->createMock(moodle_database::class);
        $this->adapter = new MoodleConnectionAdapter($this->db);
    }

    #[Test]
    public function reportsSupportedCapabilities(): void
    {
        $this->assertTrue($this->adapter->supports(Capability::TRANSACTIONS));
        $this->assertTrue($this->adapter->supports(Capability::STREAMING));
        $this->assertTrue($this->adapter->supports(Capability::JSON_WHERE));
        $this->assertTrue($this->adapter->supports(Capability::UPSERT));
        $this->assertTrue($this->adapter->supports(Capability::ROW_LOCK));
        $this->assertFalse($this->adapter->supports(Capability::RETURNING));
        $this->assertFalse($this->adapter->supports(Capability::SCHEMA_DIFF));
    }

    #[Test]
    public function defaultsToAMoodleSqlDialect(): void
    {
        $this->assertInstanceOf(MoodleSqlDialect::class, $this->adapter->dialect());
    }

    #[Test]
    public function usesAnInjectedDialect(): void
    {
        $dialect = $this->createStub(SqlDialectInterface::class);
        $adapter = new MoodleConnectionAdapter($this->db, $dialect);

        $this->assertSame($dialect, $adapter->dialect());
    }

    #[Test]
    public function executeReturnsZeroAndPassesNullForEmptyParams(): void
    {
        $this->db->expects($this->once())->method('execute')->with('DELETE FROM x', null);

        $this->assertSame(0, $this->adapter->execute('DELETE FROM x'));
    }

    #[Test]
    public function executePassesParamsWhenPresent(): void
    {
        $this->db->expects($this->once())->method('execute')->with('UPDATE x SET a=:a', ['a' => 1]);

        $this->adapter->execute('UPDATE x SET a=:a', ['a' => 1]);
    }

    #[Test]
    public function fetchConvertsRecordToArray(): void
    {
        $this->db->method('get_record_sql')->willReturn((object) ['id' => 7, 'name' => 'x']);

        $this->assertSame(['id' => 7, 'name' => 'x'], $this->adapter->fetch('SELECT ...'));
    }

    #[Test]
    public function fetchReturnsNullWhenNoRecord(): void
    {
        $this->db->method('get_record_sql')->willReturn(false);

        $this->assertNull($this->adapter->fetch('SELECT ...'));
    }

    #[Test]
    public function fetchAllConvertsAndReindexesRows(): void
    {
        $this->db->method('get_records_sql')->willReturn([5 => (object) ['id' => 5], 9 => (object) ['id' => 9]]);

        $this->assertSame([['id' => 5], ['id' => 9]], $this->adapter->fetchAll('SELECT ...'));
    }

    #[Test]
    public function transactionCommitsAndReturnsWorkResult(): void
    {
        $txn = new moodle_transaction();
        $this->db->method('start_delegated_transaction')->willReturn($txn);

        $result = $this->adapter->transaction(static fn (): string => 'done');

        $this->assertSame('done', $result);
        $this->assertTrue($txn->committed);
    }

    #[Test]
    public function transactionRollsBackAndRethrowsOnFailure(): void
    {
        $txn = new moodle_transaction();
        $this->db->method('start_delegated_transaction')->willReturn($txn);
        $this->db->expects($this->once())->method('rollback_delegated_transaction');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->adapter->transaction(static function (): never {
            throw new RuntimeException('boom');
        });
    }

    #[Test]
    public function insertStripsBracesAndReturnsNewId(): void
    {
        $this->db->expects($this->once())->method('insert_record')
            ->with('items', $this->isInstanceOf(stdClass::class))
            ->willReturn(42);

        $this->assertSame(42, $this->adapter->insert('{items}', ['name' => 'x']));
    }

    #[Test]
    public function updateStripsBracesAndForwardsRecord(): void
    {
        $this->db->expects($this->once())->method('update_record')->with('items', $this->isInstanceOf(stdClass::class));

        $this->adapter->update('{items}', ['id' => 1, 'name' => 'x']);
    }

    #[Test]
    public function deleteStripsBracesAndForwardsConditions(): void
    {
        $this->db->expects($this->once())->method('delete_records')->with('items', ['id' => 1]);

        $this->adapter->delete('{items}', ['id' => 1]);
    }

    #[Test]
    public function findConvertsRecordToArray(): void
    {
        $this->db->method('get_record')->willReturn((object) ['id' => 3]);

        $this->assertSame(['id' => 3], $this->adapter->find('{items}', ['id' => 3]));
    }

    #[Test]
    public function findReturnsNullWhenNoRecord(): void
    {
        $this->db->method('get_record')->willReturn(false);

        $this->assertNull($this->adapter->find('{items}', ['id' => 99]));
    }

    #[Test]
    public function findAllConvertsAndReindexesRows(): void
    {
        $this->db->method('get_records')->willReturn([2 => (object) ['id' => 2]]);

        $this->assertSame([['id' => 2]], $this->adapter->findAll('{items}'));
    }

    #[Test]
    public function cursorYieldsRowsAsArraysAndClosesTheRecordset(): void
    {
        $recordset = new moodle_recordset([(object) ['id' => 1], (object) ['id' => 2]]);
        $this->db->method('get_recordset_sql')->willReturn($recordset);

        $rows = iterator_to_array($this->adapter->cursor('SELECT ...'), false);

        $this->assertSame([['id' => 1], ['id' => 2]], $rows);
        $this->assertTrue($recordset->closed);
    }

    #[Test]
    public function implementsConnectionAdapterInterface(): void
    {
        $this->assertInstanceOf(ConnectionAdapterInterface::class, $this->adapter);
    }
}
