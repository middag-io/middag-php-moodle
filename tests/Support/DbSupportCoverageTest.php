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
use RuntimeException;
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
        unset($GLOBALS['__middag_test_db_record'], $GLOBALS['__middag_test_db_table_exists'], $GLOBALS['__middag_test_db_void']);
        $GLOBALS['DB'] = $this->makeDb();
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
        unset($GLOBALS['__middag_test_db_record'], $GLOBALS['__middag_test_db_table_exists'], $GLOBALS['__middag_test_db_void']);
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
    public function testDeleteRecordsForwardsNullForTheTruncateFastPath(): void
    {
        // Omitting $conditions must reach the host as null (not []) so
        // moodle_database::delete_records() can take its TRUNCATE fast path.
        self::assertTrue(DbSupport::deleteRecords('user'));
        self::assertSame(['user', null], $GLOBALS['__middag_test_db_delete_records_args']);
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

    #[Test]
    public function testGetRecordsSelectMenuReturnsTheMenu(): void
    {
        self::assertSame([2 => 'b'], DbSupport::getRecordsSelectMenu('user', 'id > 0'));
    }

    #[Test]
    public function testGetRecordSelectReturnsTheRecordWhenFound(): void
    {
        $record = (object) ['id' => 43];
        $GLOBALS['__middag_test_db_record'] = $record;

        self::assertSame($record, DbSupport::getRecordSelect('user', 'id = 43'));
    }

    #[Test]
    public function testGetRecordSelectReturnsNullWhenTheDatabaseReturnsFalse(): void
    {
        $GLOBALS['__middag_test_db_record'] = false;

        self::assertNull(DbSupport::getRecordSelect('user', 'id = 999'));
    }

    #[Test]
    public function testGetRecordSqlReturnsTheRecordWhenFound(): void
    {
        $record = (object) ['id' => 44];
        $GLOBALS['__middag_test_db_record'] = $record;

        self::assertSame($record, DbSupport::getRecordSql('SELECT * FROM x'));
    }

    #[Test]
    public function testGetRecordSqlReturnsNullWhenTheDatabaseReturnsFalse(): void
    {
        $GLOBALS['__middag_test_db_record'] = false;

        self::assertNull(DbSupport::getRecordSql('SELECT * FROM x'));
    }

    #[Test]
    public function testGetFieldSelectReturnsTheFieldValue(): void
    {
        self::assertSame('field-select-value', DbSupport::getFieldSelect('user', 'email', 'id = 1'));
    }

    #[Test]
    public function testGetFieldsetReturnsTheColumn(): void
    {
        self::assertSame([50, 60], DbSupport::getFieldset('user', 'id'));
    }

    #[Test]
    public function testGetRecordsListReturnsTheRecordList(): void
    {
        self::assertEquals([11 => (object) ['id' => 11]], DbSupport::getRecordsList('user', 'id', [11]));
    }

    #[Test]
    public function testGetRecordsetListReturnsARecordset(): void
    {
        self::assertInstanceOf(moodle_recordset::class, DbSupport::getRecordsetList('user', 'id', [11]));
    }

    #[Test]
    public function testGetCountedRecordsSqlReturnsTheRecordList(): void
    {
        self::assertEquals([(object) ['id' => 21]], DbSupport::getCountedRecordsSql('SELECT * FROM x', 'fullcount'));
    }

    #[Test]
    public function testGetCountedRecordsetSqlReturnsARecordset(): void
    {
        self::assertInstanceOf(moodle_recordset::class, DbSupport::getCountedRecordsetSql('SELECT * FROM x', 'fullcount'));
    }

    #[Test]
    public function testExportTableRecordsetReturnsARecordset(): void
    {
        self::assertInstanceOf(moodle_recordset::class, DbSupport::exportTableRecordset('user'));
    }

    #[Test]
    public function testRecordExistsSelectReturnsTrue(): void
    {
        self::assertTrue(DbSupport::recordExistsSelect('user', 'id > 0'));
    }

    #[Test]
    public function testInsertRecordsDelegatesTheBulkInsert(): void
    {
        DbSupport::insertRecords('user', [new stdClass()]);

        self::assertSame('insert_records', $GLOBALS['__middag_test_db_void']);
    }

    #[Test]
    public function testInsertRecordRawCastsTheReturnedIdToInt(): void
    {
        self::assertSame(6, DbSupport::insertRecordRaw('user', ['name' => 'x']));
    }

    #[Test]
    public function testUpdateRecordRawReturnsTrue(): void
    {
        self::assertTrue(DbSupport::updateRecordRaw('user', ['id' => 1]));
    }

    #[Test]
    public function testImportRecordReturnsTrue(): void
    {
        self::assertTrue(DbSupport::importRecord('user', ['id' => 1]));
    }

    #[Test]
    public function testSetFieldSelectReturnsTrue(): void
    {
        self::assertTrue(DbSupport::setFieldSelect('user', 'confirmed', 1, 'id > 0'));
    }

    #[Test]
    public function testDeleteRecordsListReturnsTrue(): void
    {
        self::assertTrue(DbSupport::deleteRecordsList('user', 'id', [1, 2]));
    }

    #[Test]
    public function testDeleteRecordsSubqueryDelegatesTheDelete(): void
    {
        DbSupport::deleteRecordsSubquery('user', 'id', 'subid', 'SELECT id AS subid FROM y');

        self::assertSame('delete_records_subquery', $GLOBALS['__middag_test_db_void']);
    }

    #[Test]
    public function testCommitDelegatedTransactionDelegatesTheCommit(): void
    {
        DbSupport::commitDelegatedTransaction(new moodle_transaction());

        self::assertSame('commit_delegated_transaction', $GLOBALS['__middag_test_db_void']);
    }

    #[Test]
    public function testRollbackDelegatedTransactionRethrowsTheException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        DbSupport::rollbackDelegatedTransaction(new moodle_transaction(), new RuntimeException('boom'));
    }

    #[Test]
    public function testIsTransactionStartedReturnsTrue(): void
    {
        self::assertTrue(DbSupport::isTransactionStarted());
    }

    #[Test]
    public function testTransactionsForbiddenDelegatesTheGuard(): void
    {
        DbSupport::transactionsForbidden();

        self::assertSame('transactions_forbidden', $GLOBALS['__middag_test_db_void']);
    }

    #[Test]
    public function testGetPrefixReturnsThePrefix(): void
    {
        self::assertSame('mdl_', DbSupport::getPrefix());
    }

    #[Test]
    public function testGetDbvendorReturnsTheVendor(): void
    {
        self::assertSame('mysql', DbSupport::getDbvendor());
    }

    #[Test]
    public function testGetIndexesReturnsTheIndexMap(): void
    {
        self::assertSame(['primary' => ['columns' => ['id']]], DbSupport::getIndexes('user'));
    }

    #[Test]
    public function testResetCachesDelegatesTheReset(): void
    {
        DbSupport::resetCaches(['user']);

        self::assertSame('reset_caches', $GLOBALS['__middag_test_db_void']);
    }

    #[Test]
    public function testIsFulltextSearchSupportedReturnsTrue(): void
    {
        self::assertTrue(DbSupport::isFulltextSearchSupported());
    }

    #[Test]
    public function testIsCountWindowFunctionSupportedReturnsTrue(): void
    {
        self::assertTrue(DbSupport::isCountWindowFunctionSupported());
    }

    #[Test]
    public function testSqlConcatForwardsAllExpressions(): void
    {
        self::assertSame('CONCAT(a,b)', DbSupport::sqlConcat('a', 'b'));
    }

    #[Test]
    public function testSqlConcatJoinForwardsTheSeparator(): void
    {
        self::assertSame("CJ(' ')", DbSupport::sqlConcatJoin());
    }

    #[Test]
    public function testSqlGroupConcatForwardsTheField(): void
    {
        self::assertSame('GC(name)', DbSupport::sqlGroupConcat('name'));
    }

    #[Test]
    public function testSqlEqualForwardsTheFieldName(): void
    {
        self::assertSame('EQ:name', DbSupport::sqlEqual('name', ':pat'));
    }

    #[Test]
    public function testSqlLengthForwardsTheFieldName(): void
    {
        self::assertSame('LEN(name)', DbSupport::sqlLength('name'));
    }

    #[Test]
    public function testSqlSubstrForwardsTheExpression(): void
    {
        self::assertSame('SUBSTR(name)', DbSupport::sqlSubstr('name', 1));
    }

    #[Test]
    public function testSqlPositionForwardsNeedleAndHaystack(): void
    {
        self::assertSame('POS(a,b)', DbSupport::sqlPosition('a', 'b'));
    }

    #[Test]
    public function testSqlOrderByTextForwardsTheFieldName(): void
    {
        self::assertSame('OBT(intro)', DbSupport::sqlOrderByText('intro'));
    }

    #[Test]
    public function testSqlOrderByNullForwardsTheFieldName(): void
    {
        self::assertSame('OBN(name)', DbSupport::sqlOrderByNull('name'));
    }

    #[Test]
    public function testSqlNullFromClauseReturnsTheClause(): void
    {
        self::assertSame('FROM dual', DbSupport::sqlNullFromClause());
    }

    #[Test]
    public function testSqlBitandForwardsBothOperands(): void
    {
        self::assertSame('BAND(f,2)', DbSupport::sqlBitand('f', 2));
    }

    #[Test]
    public function testSqlBitnotForwardsTheOperand(): void
    {
        self::assertSame('BNOT(f)', DbSupport::sqlBitnot('f'));
    }

    #[Test]
    public function testSqlBitorForwardsBothOperands(): void
    {
        self::assertSame('BOR(f,2)', DbSupport::sqlBitor('f', 2));
    }

    #[Test]
    public function testSqlBitxorForwardsBothOperands(): void
    {
        self::assertSame('BXOR(f,2)', DbSupport::sqlBitxor('f', 2));
    }

    #[Test]
    public function testSqlModuloForwardsBothOperands(): void
    {
        self::assertSame('MOD(f,7)', DbSupport::sqlModulo('f', 7));
    }

    #[Test]
    public function testSqlCeilForwardsTheExpression(): void
    {
        self::assertSame('CEIL(grade)', DbSupport::sqlCeil('grade'));
    }

    #[Test]
    public function testSqlCastToCharForwardsTheField(): void
    {
        self::assertSame('TOCHAR(id)', DbSupport::sqlCastToChar('id'));
    }

    #[Test]
    public function testSqlCastChar2intForwardsTheFieldName(): void
    {
        self::assertSame('C2I(code)', DbSupport::sqlCastChar2int('code'));
    }

    #[Test]
    public function testSqlCastChar2realForwardsTheFieldName(): void
    {
        self::assertSame('C2R(score)', DbSupport::sqlCastChar2real('score'));
    }

    #[Test]
    public function testSqlIsemptyForwardsTableAndField(): void
    {
        self::assertSame('ISEMPTY(user.name)', DbSupport::sqlIsempty('user', 'name', false, false));
    }

    #[Test]
    public function testSqlIsnotemptyForwardsTableAndField(): void
    {
        self::assertSame('ISNOTEMPTY(user.name)', DbSupport::sqlIsnotempty('user', 'name', false, false));
    }

    #[Test]
    public function testSqlRegexSupportedReturnsTrue(): void
    {
        self::assertTrue(DbSupport::sqlRegexSupported());
    }

    #[Test]
    public function testSqlRegexGetWordBeginningBoundaryMarkerReturnsTheMarker(): void
    {
        self::assertSame('[[:<:]]', DbSupport::sqlRegexGetWordBeginningBoundaryMarker());
    }

    #[Test]
    public function testSqlRegexGetWordEndBoundaryMarkerReturnsTheMarker(): void
    {
        self::assertSame('[[:>:]]', DbSupport::sqlRegexGetWordEndBoundaryMarker());
    }

    #[Test]
    public function testSqlIntersectForwardsTheFields(): void
    {
        self::assertSame('INTERSECT(id)', DbSupport::sqlIntersect(['SELECT id FROM a'], 'id'));
    }

    #[Test]
    public function testPerfGetReadsReturnsTheCounter(): void
    {
        self::assertSame(11, DbSupport::perfGetReads());
    }

    #[Test]
    public function testPerfGetWritesReturnsTheCounter(): void
    {
        self::assertSame(12, DbSupport::perfGetWrites());
    }

    #[Test]
    public function testPerfGetQueriesReturnsTheCounter(): void
    {
        self::assertSame(13, DbSupport::perfGetQueries());
    }

    #[Test]
    public function testPerfGetQueriesTimeReturnsTheDuration(): void
    {
        self::assertSame(1.5, DbSupport::perfGetQueriesTime());
    }

    #[Test]
    public function testWantReadReplicaUsesTheModernApiWhenAvailable(): void
    {
        self::assertTrue(DbSupport::wantReadReplica());
    }

    #[Test]
    public function testWantReadReplicaFallsBackToTheLegacySlaveApi(): void
    {
        // Moodle 4.5 driver: pre-MDL-71257, only want_read_slave() exists.
        $GLOBALS['DB'] = new class {
            public function want_read_slave(): bool
            {
                return true;
            }
        };

        self::assertTrue(DbSupport::wantReadReplica());
    }

    #[Test]
    public function testPerfGetReadsReplicaUsesTheModernApiWhenAvailable(): void
    {
        self::assertSame(14, DbSupport::perfGetReadsReplica());
    }

    #[Test]
    public function testPerfGetReadsReplicaFallsBackToTheLegacySlaveApi(): void
    {
        // Moodle 4.5 driver: pre-MDL-71257, only perf_get_reads_slave() exists.
        $GLOBALS['DB'] = new class {
            public function perf_get_reads_slave(): int
            {
                return 3;
            }
        };

        self::assertSame(3, DbSupport::perfGetReadsReplica());
    }

    #[Test]
    public function testMarkTablesForPrimaryDelegatesWhenSupported(): void
    {
        DbSupport::markTablesForPrimary('user', 'course');

        self::assertSame('mark_tables_for_primary', $GLOBALS['__middag_test_db_void']);
    }

    #[Test]
    public function testMarkTablesForPrimaryIsANoOpOnOlderDrivers(): void
    {
        // Pre-5.2 driver without mark_tables_for_primary(): must not fail.
        $GLOBALS['DB'] = new class {};

        DbSupport::markTablesForPrimary('user');

        self::assertArrayNotHasKey('__middag_test_db_void', $GLOBALS);
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
                $GLOBALS['__middag_test_db_delete_records_args'] = $args;

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

            public function get_records_select_menu(...$args): array
            {
                return [2 => 'b'];
            }

            public function get_record_select(...$args): mixed
            {
                return $GLOBALS['__middag_test_db_record'] ?? false;
            }

            public function get_record_sql(...$args): mixed
            {
                return $GLOBALS['__middag_test_db_record'] ?? false;
            }

            public function get_field_select(...$args): mixed
            {
                return 'field-select-value';
            }

            public function get_fieldset(...$args): array
            {
                return [50, 60];
            }

            public function get_records_list(...$args): array
            {
                return [11 => (object) ['id' => 11]];
            }

            public function get_recordset_list(...$args): moodle_recordset
            {
                return new moodle_recordset([(object) ['id' => 11]]);
            }

            public function get_counted_records_sql(...$args): array
            {
                return [(object) ['id' => 21]];
            }

            public function get_counted_recordset_sql(...$args): moodle_recordset
            {
                return new moodle_recordset([(object) ['id' => 21]]);
            }

            public function export_table_recordset(...$args): moodle_recordset
            {
                return new moodle_recordset([(object) ['id' => 31]]);
            }

            public function record_exists_select(...$args): bool
            {
                return true;
            }

            public function insert_records(...$args): void
            {
                $GLOBALS['__middag_test_db_void'] = 'insert_records';
            }

            public function insert_record_raw(...$args): mixed
            {
                return '6';
            }

            public function update_record_raw(...$args): bool
            {
                return true;
            }

            public function import_record(...$args): bool
            {
                return true;
            }

            public function set_field_select(...$args): bool
            {
                return true;
            }

            public function delete_records_list(...$args): bool
            {
                return true;
            }

            public function delete_records_subquery(...$args): void
            {
                $GLOBALS['__middag_test_db_void'] = 'delete_records_subquery';
            }

            public function commit_delegated_transaction(...$args): void
            {
                $GLOBALS['__middag_test_db_void'] = 'commit_delegated_transaction';
            }

            public function rollback_delegated_transaction(...$args): never
            {
                // Mirrors Moodle's real contract: always rethrows the given exception.
                throw $args[1];
            }

            public function is_transaction_started(): bool
            {
                return true;
            }

            public function transactions_forbidden(): void
            {
                $GLOBALS['__middag_test_db_void'] = 'transactions_forbidden';
            }

            public function get_prefix(): string
            {
                return 'mdl_';
            }

            public function get_dbvendor(): string
            {
                return 'mysql';
            }

            public function get_indexes(...$args): array
            {
                return ['primary' => ['columns' => ['id']]];
            }

            public function reset_caches(...$args): void
            {
                $GLOBALS['__middag_test_db_void'] = 'reset_caches';
            }

            public function is_fulltext_search_supported(): bool
            {
                return true;
            }

            public function is_count_window_function_supported(): bool
            {
                return true;
            }

            public function sql_concat(...$args): string
            {
                return 'CONCAT(' . implode(',', $args) . ')';
            }

            public function sql_concat_join(...$args): string
            {
                return 'CJ(' . $args[0] . ')';
            }

            public function sql_group_concat(...$args): string
            {
                return 'GC(' . $args[0] . ')';
            }

            public function sql_equal(...$args): string
            {
                return 'EQ:' . $args[0];
            }

            public function sql_length(...$args): string
            {
                return 'LEN(' . $args[0] . ')';
            }

            public function sql_substr(...$args): string
            {
                return 'SUBSTR(' . $args[0] . ')';
            }

            public function sql_position(...$args): string
            {
                return 'POS(' . $args[0] . ',' . $args[1] . ')';
            }

            public function sql_order_by_text(...$args): string
            {
                return 'OBT(' . $args[0] . ')';
            }

            public function sql_order_by_null(...$args): string
            {
                return 'OBN(' . $args[0] . ')';
            }

            public function sql_null_from_clause(): string
            {
                return 'FROM dual';
            }

            public function sql_bitand(...$args): string
            {
                return 'BAND(' . $args[0] . ',' . $args[1] . ')';
            }

            public function sql_bitnot(...$args): string
            {
                return 'BNOT(' . $args[0] . ')';
            }

            public function sql_bitor(...$args): string
            {
                return 'BOR(' . $args[0] . ',' . $args[1] . ')';
            }

            public function sql_bitxor(...$args): string
            {
                return 'BXOR(' . $args[0] . ',' . $args[1] . ')';
            }

            public function sql_modulo(...$args): string
            {
                return 'MOD(' . $args[0] . ',' . $args[1] . ')';
            }

            public function sql_ceil(...$args): string
            {
                return 'CEIL(' . $args[0] . ')';
            }

            public function sql_cast_to_char(...$args): string
            {
                return 'TOCHAR(' . $args[0] . ')';
            }

            public function sql_cast_char2int(...$args): string
            {
                return 'C2I(' . $args[0] . ')';
            }

            public function sql_cast_char2real(...$args): string
            {
                return 'C2R(' . $args[0] . ')';
            }

            public function sql_isempty(...$args): string
            {
                return 'ISEMPTY(' . $args[0] . '.' . $args[1] . ')';
            }

            public function sql_isnotempty(...$args): string
            {
                return 'ISNOTEMPTY(' . $args[0] . '.' . $args[1] . ')';
            }

            public function sql_regex_supported(): bool
            {
                return true;
            }

            public function sql_regex_get_word_beginning_boundary_marker(): string
            {
                return '[[:<:]]';
            }

            public function sql_regex_get_word_end_boundary_marker(): string
            {
                return '[[:>:]]';
            }

            public function sql_intersect(...$args): string
            {
                return 'INTERSECT(' . $args[1] . ')';
            }

            public function perf_get_reads(): int
            {
                return 11;
            }

            public function perf_get_writes(): int
            {
                return 12;
            }

            public function perf_get_queries(): int
            {
                return 13;
            }

            public function perf_get_queries_time(): float
            {
                return 1.5;
            }

            public function want_read_replica(): bool
            {
                return true;
            }

            public function perf_get_reads_replica(): int
            {
                return 14;
            }

            public function mark_tables_for_primary(...$args): void
            {
                $GLOBALS['__middag_test_db_void'] = 'mark_tables_for_primary';
            }
        };
    }
}
