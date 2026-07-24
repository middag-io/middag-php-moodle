<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Database\Schema;

use database_manager;
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Exception\MiddagPersistenceException;
use Throwable;
use xmldb_field;
use xmldb_index;
use xmldb_table;

/**
 * Moodle xmldb implementation of SchemaBuilderAdapterInterface.
 *
 * Translates platform-agnostic descriptor arrays into Moodle xmldb DDL operations
 * executed via the database_manager.
 *
 * Descriptor type strings → XMLDB_TYPE_* constants:
 *   int → INTEGER, char → CHAR, text → TEXT, float → FLOAT, number → NUMBER, binary → BINARY
 *
 * @internal
 */
class XmldbSchemaAdapter implements SchemaBuilderAdapterInterface
{
    public function __construct(
        private readonly database_manager $dbman,
    ) {}

    public function createTable(array $descriptor): void
    {
        try {
            $table = new xmldb_table($descriptor['name']);

            if (!empty($descriptor['comment'])) {
                $table->setComment($descriptor['comment']);
            }

            foreach ($descriptor['columns'] ?? [] as $column) {
                $table->add_field(
                    $column['name'],
                    $this->mapType($column['type']),
                    $column['length'] ?? null,
                    null,
                    ($column['notnull'] ?? false) ? XMLDB_NOTNULL : null,
                    ($column['sequence'] ?? false) ? XMLDB_SEQUENCE : null,
                    $column['default'] ?? null,
                );
            }

            foreach ($descriptor['keys'] ?? [] as $key) {
                $table->add_key(
                    $key['name'],
                    $this->mapKeyType($key['type']),
                    $key['fields'],
                    $key['reftable'] ?? null,
                    $key['reffields'] ?? null,
                );
            }

            foreach ($descriptor['indexes'] ?? [] as $index) {
                $table->add_index(
                    $index['name'],
                    $index['unique'] ?? false ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE,
                    $index['fields'],
                );
            }

            $this->dbman->create_table($table);
        } catch (Throwable $throwable) {
            throw new MiddagPersistenceException(
                'Failed to create table ' . $descriptor['name'] . ': ' . $throwable->getMessage(),
                0,
                $throwable,
            );
        }
    }

    public function dropTable(string $table_name): void
    {
        try {
            $table = new xmldb_table($table_name);
            $this->dbman->drop_table($table);
        } catch (Throwable $throwable) {
            throw new MiddagPersistenceException('Failed to drop table ' . $table_name . ': ' . $throwable->getMessage(), 0, $throwable);
        }
    }

    public function addColumn(string $table_name, array $column): void
    {
        try {
            $table = new xmldb_table($table_name);
            $field = new xmldb_field(
                $column['name'],
                $this->mapType($column['type']),
                $column['length'] ?? null,
                null,
                ($column['notnull'] ?? false) ? XMLDB_NOTNULL : null,
                ($column['sequence'] ?? false) ? XMLDB_SEQUENCE : null,
                $column['default'] ?? null,
            );
            $this->dbman->add_field($table, $field);
        } catch (Throwable $throwable) {
            throw new MiddagPersistenceException(
                'Failed to add column ' . $column['name'] . ' to ' . $table_name . ': ' . $throwable->getMessage(),
                0,
                $throwable,
            );
        }
    }

    public function dropColumn(string $table_name, string $column_name): void
    {
        try {
            $table = new xmldb_table($table_name);
            $field = new xmldb_field($column_name);
            $this->dbman->drop_field($table, $field);
        } catch (Throwable $throwable) {
            throw new MiddagPersistenceException(
                'Failed to drop column ' . $column_name . ' from ' . $table_name . ': ' . $throwable->getMessage(),
                0,
                $throwable,
            );
        }
    }

    public function addIndex(string $table_name, array $index): void
    {
        try {
            $table = new xmldb_table($table_name);
            $idx = new xmldb_index(
                $index['name'],
                $index['unique'] ?? false ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE,
                $index['fields'],
            );
            $this->dbman->add_index($table, $idx);
        } catch (Throwable $throwable) {
            throw new MiddagPersistenceException(
                'Failed to add index ' . $index['name'] . ' on ' . $table_name . ': ' . $throwable->getMessage(),
                0,
                $throwable,
            );
        }
    }

    public function dropIndex(string $table_name, string $index_name, array $fields = []): void
    {
        try {
            $table = new xmldb_table($table_name);
            // Moodle resolves the physical index via find_index_name(), which
            // matches on the field-set — the xmldb_index name is arbitrary and
            // NOT the auto-generated DB identifier. A name-only xmldb_index makes
            // find_index_name() fail, so drop_index() throws and the index
            // survives. Fall back to [$index_name] only when the caller could
            // not supply fields (single-column legacy usage).
            $idx = new xmldb_index($index_name, XMLDB_INDEX_NOTUNIQUE, $fields !== [] ? $fields : [$index_name]);
            $this->dbman->drop_index($table, $idx);
        } catch (Throwable $throwable) {
            throw new MiddagPersistenceException(
                'Failed to drop index ' . $index_name . ' from ' . $table_name . ': ' . $throwable->getMessage(),
                0,
                $throwable,
            );
        }
    }

    public function tableExists(string $table_name): bool
    {
        return $this->dbman->table_exists(new xmldb_table($table_name));
    }

    public function columnExists(string $table_name, string $column_name): bool
    {
        return $this->dbman->field_exists(new xmldb_table($table_name), new xmldb_field($column_name));
    }

    /**
     * Map a descriptor type string to an XMLDB_TYPE_* constant.
     *
     * @throws MiddagPersistenceException when the type is unknown
     */
    private function mapType(string $type): int
    {
        return match ($type) {
            'int' => XMLDB_TYPE_INTEGER,
            'char' => XMLDB_TYPE_CHAR,
            'text' => XMLDB_TYPE_TEXT,
            'float' => XMLDB_TYPE_FLOAT,
            'number' => XMLDB_TYPE_NUMBER,
            'binary' => XMLDB_TYPE_BINARY,
            default => throw new MiddagPersistenceException('Unknown column type: ' . $type),
        };
    }

    /**
     * Map a descriptor key type string to an XMLDB_KEY_* constant.
     *
     * @throws MiddagPersistenceException when the type is unknown
     */
    private function mapKeyType(string $type): int
    {
        return match ($type) {
            'primary' => XMLDB_KEY_PRIMARY,
            'unique' => XMLDB_KEY_UNIQUE,
            'foreign' => XMLDB_KEY_FOREIGN,
            default => throw new MiddagPersistenceException('Unknown key type: ' . $type),
        };
    }
}
