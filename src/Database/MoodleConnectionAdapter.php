<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Database;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Database\Enum\Capability;
use moodle_database;
use Throwable;

/**
 * Moodle database adapter — wraps the global `$DB` (`moodle_database`) behind the
 * framework {@see ConnectionAdapterInterface} seam.
 *
 * This is the only object in the Moodle adapter that knows about `$DB`; everything
 * above it (query builders, repositories, domain) depends on the framework contract,
 * never on `$DB` directly, so the same code runs unchanged on every host.
 *
 * Records and conditions are plain assoc arrays at this boundary; Moodle DML returns
 * `stdClass` rows, so this adapter converts to/from assoc arrays. Mapping rows to
 * domain entities happens above, in repositories.
 *
 * Table names received by the record helpers are *logical* (unprefixed, unbraced):
 * Moodle's DML applies the table prefix internally, so the record helpers strip the
 * `{...}` braces the dialect emits before handing the name to `$DB`.
 */
final readonly class MoodleConnectionAdapter implements ConnectionAdapterInterface
{
    private SqlDialectInterface $dialect;

    public function __construct(
        private moodle_database $db,
        ?SqlDialectInterface $dialect = null,
    ) {
        $this->dialect = $dialect ?? new MoodleSqlDialect($db);
    }

    public function supports(Capability $feature): bool
    {
        return match ($feature) {
            // Moodle DML exposes start_delegated_transaction()/commit/rollback.
            Capability::TRANSACTIONS => true,
            // get_recordset_sql() backs an unbuffered cursor.
            Capability::STREAMING => true,
            // MySQL 5.7+/MariaDB/pgsql baselines support JSON predicates.
            Capability::JSON_WHERE => true,
            // No portable RETURNING across Moodle's supported engines.
            Capability::RETURNING => false,
            // INSERT ... ON DUPLICATE KEY UPDATE (mysqli/mariadb family).
            Capability::UPSERT => true,
            // Moodle owns schema lifecycle via XMLDB/database_manager; no diffing here.
            Capability::SCHEMA_DIFF => false,
            // InnoDB / pgsql support SELECT ... FOR UPDATE / FOR SHARE.
            Capability::ROW_LOCK => true,
        };
    }

    public function dialect(): SqlDialectInterface
    {
        return $this->dialect;
    }

    public function execute(string $sql, array $params = []): int
    {
        $this->db->execute($sql, $params === [] ? null : $params);

        // Moodle's execute() returns bool (throws on failure); the contract expects
        // an affected-row count, which Moodle does not expose here. Report 0 to mean
        // "succeeded, count unknown" rather than fabricate a number.
        return 0;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $record = $this->db->get_record_sql($sql, $params === [] ? null : $params);

        return $record === false ? null : (array) $record;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $records = $this->db->get_records_sql($sql, $params === [] ? null : $params);

        return array_values(array_map(static fn ($row): array => (array) $row, $records));
    }

    public function transaction(callable $work): mixed
    {
        $transaction = $this->db->start_delegated_transaction();

        try {
            $result = $work($this);
            $transaction->allow_commit();

            return $result;
        } catch (Throwable $throwable) {
            $this->db->rollback_delegated_transaction($transaction, $throwable);

            throw $throwable;
        }
    }

    public function insert(string $table, array $record): int
    {
        return (int) $this->db->insert_record($this->logical($table), (object) $record);
    }

    public function update(string $table, array $record): void
    {
        $this->db->update_record($this->logical($table), (object) $record);
    }

    public function delete(string $table, array $conditions): void
    {
        $this->db->delete_records($this->logical($table), $conditions);
    }

    public function find(string $table, array $conditions): ?array
    {
        $record = $this->db->get_record($this->logical($table), $conditions);

        return $record === false ? null : (array) $record;
    }

    public function findAll(string $table, array $conditions = []): array
    {
        $records = $this->db->get_records($this->logical($table), $conditions === [] ? null : $conditions);

        return array_values(array_map(static fn ($row): array => (array) $row, $records));
    }

    public function cursor(string $sql, array $params = []): iterable
    {
        $recordset = $this->db->get_recordset_sql($sql, $params === [] ? null : $params);

        try {
            foreach ($recordset as $row) {
                yield (array) $row;
            }
        } finally {
            $recordset->close();
        }
    }

    /**
     * Strip the dialect's `{tablename}` braces back to the logical name Moodle's
     * record helpers expect (they prefix internally and reject braces).
     */
    private function logical(string $table): string
    {
        return trim($table, '{}');
    }
}
