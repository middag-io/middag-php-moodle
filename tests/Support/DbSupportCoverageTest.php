<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use database_manager;
use Middag\Moodle\Support\DbSupport;
use moodle_recordset;
use moodle_transaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * DbSupport is a thin, stateless facade over the global $DB. It is replaced with
 * a recording double exposing every moodle_database method DbSupport delegates
 * to, so each wrapper is exercised without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(DbSupport::class)]
final class DbSupportCoverageTest extends TestCase
{
    private mixed $prevDb;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
        unset($GLOBALS['__middag_test_db_record'], $GLOBALS['__middag_test_db_table_exists']);
        $GLOBALS['DB'] = $this->makeDb();
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
        unset($GLOBALS['__middag_test_db_record'], $GLOBALS['__middag_test_db_table_exists']);
    }

    #[Test]
    public function testGetRecordReturnsTheRecordWhenFound(): void
    {
        $record = (object) ['id' => 42];
        $GLOBALS['__middag_test_db_record'] = $record;

        self::assertSame($record, DbSupport::getRecord('user', ['id' => 42]));
    }

    #[Test]
    public function testGetRecordReturnsNullWhenTheDatabaseReturnsFalse(): void
    {
        $GLOBALS['__middag_test_db_record'] = false;

        self::assertNull(DbSupport::getRecord('user', ['id' => 999]));
    }

    #[Test]
    public function testGetFieldReturnsTheFieldValue(): void
    {
        self::assertSame('field-value', DbSupport::getField('user', 'email', ['id' => 1]));
    }

    #[Test]
    public function testGetFieldSqlReturnsTheFieldValue(): void
    {
        self::assertSame('field-sql-value', DbSupport::getFieldSql('SELECT email FROM x', []));
    }

    #[Test]
    public function testGetRecordsReturnsTheRecordList(): void
    {
        self::assertEquals([1 => (object) ['id' => 1]], DbSupport::getRecords('user'));
    }

    #[Test]
    public function testGetRecordsSqlReturnsTheRecordList(): void
    {
        self::assertEquals([(object) ['id' => 2]], DbSupport::getRecordsSql('SELECT * FROM x'));
    }

    #[Test]
    public function testInsertRecordCastsTheReturnedIdToInt(): void
    {
        self::assertSame(5, DbSupport::insertRecord('user', new stdClass()));
    }

    #[Test]
    public function testUpdateRecordReturnsTrue(): void
    {
        self::assertTrue(DbSupport::updateRecord('user', (object) ['id' => 1]));
    }

    #[Test]
    public function testDeleteRecordsReturnsTrue(): void
    {
        self::assertTrue(DbSupport::deleteRecords('user', ['id' => 1]));
    }

    #[Test]
    public function testRecordExistsReturnsTrue(): void
    {
        self::assertTrue(DbSupport::recordExists('user', ['id' => 1]));
    }

    #[Test]
    public function testStartDelegatedTransactionReturnsATransaction(): void
    {
        self::assertInstanceOf(moodle_transaction::class, DbSupport::startDelegatedTransaction());
    }

    #[Test]
    public function testExecuteReturnsTrue(): void
    {
        self::assertTrue(DbSupport::execute('UPDATE x SET y = 1', []));
    }

    #[Test]
    public function testSqlFullnameForwardsBothFieldNames(): void
    {
        self::assertSame('FN(given,family)', DbSupport::sqlFullname('given', 'family'));
    }

    #[Test]
    public function testSqlLikeForwardsTheField(): void
    {
        self::assertSame('LIKE:name', DbSupport::sqlLike('name', ':pat'));
    }

    #[Test]
    public function testGetInOrEqualReturnsTheFragmentAndParams(): void
    {
        [$fragment, $params] = DbSupport::getInOrEqual([1, 2]);

        self::assertSame('IN (:a, :b)', $fragment);
        self::assertSame(['a' => 1, 'b' => 2], $params);
    }

    #[Test]
    public function testGetRecordsMenuReturnsTheMenu(): void
    {
        self::assertSame([1 => 'a'], DbSupport::getRecordsMenu('user'));
    }

    #[Test]
    public function testGetRecordsetSqlReturnsARecordset(): void
    {
        self::assertInstanceOf(moodle_recordset::class, DbSupport::getRecordsetSql('SELECT 1'));
    }

    #[Test]
    public function testSqlCompareTextForwardsTheFieldName(): void
    {
        self::assertSame('CT(content)', DbSupport::sqlCompareText('content'));
    }

    #[Test]
    public function testCountRecordsCastsToInt(): void
    {
        self::assertSame(7, DbSupport::countRecords('user'));
    }

    #[Test]
    public function testCountRecordsSelectCastsToInt(): void
    {
        self::assertSame(8, DbSupport::countRecordsSelect('user', 'id > 0'));
    }

    #[Test]
    public function testCountRecordsSqlCastsToInt(): void
    {
        self::assertSame(9, DbSupport::countRecordsSql('SELECT COUNT(1) FROM x'));
    }

    #[Test]
    public function testSqlLikeEscapeForwardsTheText(): void
    {
        self::assertSame('ESC:50%', DbSupport::sqlLikeEscape('50%'));
    }

    #[Test]
    public function testSetFieldReturnsTrue(): void
    {
        self::assertTrue(DbSupport::setField('user', 'confirmed', 1, ['id' => 1]));
    }

    #[Test]
    public function testGetDbfamilyReturnsTheFamily(): void
    {
        self::assertSame('mysql', DbSupport::getDbfamily());
    }

    #[Test]
    public function testGetServerInfoReturnsTheServerInfoArray(): void
    {
        self::assertSame(['description' => '8.4.0'], DbSupport::getServerInfo());
    }

    #[Test]
    public function testGetDbcollationReturnsTheCollation(): void
    {
        self::assertSame('utf8mb4_unicode_ci', DbSupport::getDbcollation());
    }

    #[Test]
    public function testTableExistsReturnsTrueWhenTheManagerReportsThePresence(): void
    {
        $GLOBALS['__middag_test_db_table_exists'] = true;

        self::assertTrue(DbSupport::tableExists('user'));
    }

    #[Test]
    public function testTableExistsReturnsFalseWhenTheManagerReportsAbsence(): void
    {
        $GLOBALS['__middag_test_db_table_exists'] = false;

        self::assertFalse(DbSupport::tableExists('ghost'));
    }

    #[Test]
    public function testDeleteRecordsSelectReturnsTrue(): void
    {
        self::assertTrue(DbSupport::deleteRecordsSelect('user', 'id < :max', ['max' => 3]));
    }

    #[Test]
    public function testGetRecordsSelectReturnsTheRecordList(): void
    {
        self::assertEquals([(object) ['id' => 3]], DbSupport::getRecordsSelect('user', 'id > 0'));
    }

    #[Test]
    public function testGetRecordsSqlMenuReturnsTheMenu(): void
    {
        self::assertSame([1 => 'x'], DbSupport::getRecordsSqlMenu('SELECT id, name FROM x'));
    }

    #[Test]
    public function testGetFieldsetSelectReturnsTheColumn(): void
    {
        self::assertSame([10, 20], DbSupport::getFieldsetSelect('user', 'id', 'id > 0'));
    }

    #[Test]
    public function testGetFieldsetSqlReturnsTheColumn(): void
    {
        self::assertSame([30, 40], DbSupport::getFieldsetSql('SELECT id FROM x'));
    }

    #[Test]
    public function testGetRecordsetSelectReturnsARecordset(): void
    {
        self::assertInstanceOf(moodle_recordset::class, DbSupport::getRecordsetSelect('user', 'id > 0'));
    }

    #[Test]
    public function testGetRecordsetReturnsARecordset(): void
    {
        self::assertInstanceOf(moodle_recordset::class, DbSupport::getRecordset('user'));
    }

    #[Test]
    public function testRecordExistsSqlReturnsTrue(): void
    {
        self::assertTrue(DbSupport::recordExistsSql('SELECT 1 FROM x'));
    }

    #[Test]
    public function testSqlRegexReturnsThePositiveOperatorByDefault(): void
    {
        // Default (not negated) must delegate to sql_regex(positivematch=true).
        self::assertSame('REGEXP', DbSupport::sqlRegex());
    }

    #[Test]
    public function testSqlRegexReturnsTheNegatedOperatorWhenNegated(): void
    {
        // $negated=true must invert to sql_regex(positivematch=false).
        self::assertSame('NOT REGEXP', DbSupport::sqlRegex(true));
    }

    #[Test]
    public function testGetManagerReturnsTheDatabaseManager(): void
    {
        self::assertInstanceOf(database_manager::class, DbSupport::getManager());
    }

    #[Test]
    public function testGetColumnsReturnsTheColumnMap(): void
    {
        self::assertEquals(['id' => (object) ['name' => 'id']], DbSupport::getColumns('user'));
    }

    #[Test]
    public function testGetTablesReturnsTheTableList(): void
    {
        self::assertSame(['mdl_user'], DbSupport::getTables());
    }

    private function makeDb(): object
    {
        return new class {
            public function get_record(...$args): mixed
            {
                return $GLOBALS['__middag_test_db_record'] ?? false;
            }

            public function get_field(...$args): mixed
            {
                return 'field-value';
            }

            public function get_field_sql(...$args): mixed
            {
                return 'field-sql-value';
            }

            public function get_records(...$args): array
            {
                return [1 => (object) ['id' => 1]];
            }

            public function get_records_sql(...$args): array
            {
                return [(object) ['id' => 2]];
            }

            public function insert_record(...$args): mixed
            {
                return '5';
            }

            public function update_record(...$args): bool
            {
                return true;
            }

            public function delete_records(...$args): bool
            {
                return true;
            }

            public function record_exists(...$args): bool
            {
                return true;
            }

            public function start_delegated_transaction(): moodle_transaction
            {
                return new moodle_transaction();
            }

            public function execute(...$args): bool
            {
                return true;
            }

            public function sql_fullname(...$args): string
            {
                return 'FN(' . $args[0] . ',' . $args[1] . ')';
            }

            public function sql_like(...$args): string
            {
                return 'LIKE:' . $args[0];
            }

            public function get_in_or_equal(...$args): array
            {
                return ['IN (:a, :b)', ['a' => 1, 'b' => 2]];
            }

            public function get_records_menu(...$args): array
            {
                return [1 => 'a'];
            }

            public function get_recordset_sql(...$args): moodle_recordset
            {
                return new moodle_recordset([(object) ['id' => 1]]);
            }

            public function sql_compare_text(...$args): string
            {
                return 'CT(' . $args[0] . ')';
            }

            public function count_records(...$args): mixed
            {
                return '7';
            }

            public function count_records_select(...$args): mixed
            {
                return '8';
            }

            public function count_records_sql(...$args): mixed
            {
                return '9';
            }

            public function sql_like_escape(...$args): string
            {
                return 'ESC:' . $args[0];
            }

            public function set_field(...$args): bool
            {
                return true;
            }

            public function get_dbfamily(): string
            {
                return 'mysql';
            }

            public function get_server_info(): array
            {
                return ['description' => '8.4.0'];
            }

            public function get_dbcollation(): string
            {
                return 'utf8mb4_unicode_ci';
            }

            public function get_manager(): database_manager
            {
                return new class extends database_manager {
                    public function table_exists($table)
                    {
                        return $GLOBALS['__middag_test_db_table_exists'] ?? true;
                    }
                };
            }

            public function delete_records_select(...$args): bool
            {
                return true;
            }

            public function get_records_select(...$args): array
            {
                return [(object) ['id' => 3]];
            }

            public function get_records_sql_menu(...$args): array
            {
                return [1 => 'x'];
            }

            public function get_fieldset_select(...$args): array
            {
                return [10, 20];
            }

            public function get_fieldset_sql(...$args): array
            {
                return [30, 40];
            }

            public function get_recordset_select(...$args): moodle_recordset
            {
                return new moodle_recordset([(object) ['id' => 3]]);
            }

            public function get_recordset(...$args): moodle_recordset
            {
                return new moodle_recordset([(object) ['id' => 4]]);
            }

            public function record_exists_sql(...$args): bool
            {
                return true;
            }

            public function sql_regex($positivematch = true, $casesensitive = false): string
            {
                // Mirrors Moodle's real contract: the FIRST arg is $positivematch.
                return $positivematch ? 'REGEXP' : 'NOT REGEXP';
            }

            public function get_columns(...$args): array
            {
                return ['id' => (object) ['name' => 'id']];
            }

            public function get_tables(...$args): array
            {
                return ['mdl_user'];
            }
        };
    }
}
