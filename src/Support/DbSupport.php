<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use database_manager;
use dml_exception;
use moodle_recordset;
use moodle_transaction;
use stdClass;

/**
 * Wrapper for Moodle's global database object ($DB).
 *
 * Provides a clean interface for database operations, allowing for better
 * testability and isolation of Moodle's global state.
 *
 * @api
 */
class DbSupport
{
    /**
     * Retrieves a single database record as an object.
     *
     * @param string $table      Table name
     * @param array  $conditions array of field => value conditions
     * @param string $fields     Comma separated list of fields (default: all)
     * @param int    $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|stdClass the record object or null if not found
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecord(string $table, array $conditions, string $fields = '*', int $strictness = IGNORE_MISSING): ?stdClass
    {
        global $DB;

        $record = $DB->get_record($table, $conditions, $fields, $strictness);

        return $record ?: null;
    }

    /**
     * Retrieves a single field value from a database record.
     *
     * @param string $table      Table name
     * @param string $return     Field name to return
     * @param array  $conditions array of field => value conditions
     * @param int    $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return mixed the field value or false if not found
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getField(string $table, string $return, array $conditions, int $strictness = IGNORE_MISSING): mixed
    {
        global $DB;

        return $DB->get_field($table, $return, $conditions, $strictness);
    }

    /**
     * Retrieves a single field value using a custom SQL query.
     *
     * @param string     $sql    the SQL query
     * @param null|array $params array of parameters for the SQL query
     *
     * @return mixed the field value or false if not found
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getFieldSql(string $sql, ?array $params = null): mixed
    {
        global $DB;

        return $DB->get_field_sql($sql, $params);
    }

    /**
     * Retrieves multiple database records as an array of objects.
     *
     * @param string $table      Table name
     * @param array  $conditions array of field => value conditions
     * @param string $sort       SQL sort order
     * @param string $fields     Comma separated list of fields
     * @param int    $limitfrom  return records starting from this index
     * @param int    $limitnum   return this many records
     *
     * @return array<int|string, stdClass> list of record objects
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecords(string $table, array $conditions = [], string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): array
    {
        global $DB;

        return $DB->get_records($table, $conditions, $sort, $fields, $limitfrom, $limitnum);
    }

    /**
     * Retrieves records using a custom SQL query.
     *
     * @param string     $sql       the SQL query
     * @param null|array $params    array of parameters for the SQL query
     * @param int        $limitfrom return records starting from this index
     * @param int        $limitnum  return this many records
     *
     * @return array<int|string, stdClass> list of record objects
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecordsSql(string $sql, ?array $params = null, int $limitfrom = 0, int $limitnum = 0): array
    {
        global $DB;

        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    }

    /**
     * Inserts a new record into a table.
     *
     * @param string   $table      Table name
     * @param stdClass $dataobject the data object to insert
     * @param bool     $returnid   whether to return the new record ID
     * @param bool     $bulk       whether this is a bulk insert
     *
     * @return int the new record ID (if $returnid is true)
     *
     * @throws dml_exception if a database error occurs
     */
    public static function insertRecord(string $table, stdClass $dataobject, bool $returnid = true, bool $bulk = false): int
    {
        global $DB;

        return (int) $DB->insert_record($table, $dataobject, $returnid, $bulk);
    }

    /**
     * Updates an existing record in a table.
     *
     * @param string   $table      Table name
     * @param stdClass $dataobject the data object with new values (must include 'id')
     * @param bool     $bulk       whether this is a bulk update
     *
     * @return bool True on success, false otherwise
     *
     * @throws dml_exception if a database error occurs
     */
    public static function updateRecord(string $table, stdClass $dataobject, bool $bulk = false): bool
    {
        global $DB;

        return $DB->update_record($table, $dataobject, $bulk);
    }

    /**
     * Deletes records from a table.
     *
     * @param string $table      Table name
     * @param array  $conditions array of field => value conditions
     *
     * @return bool True on success, false otherwise
     *
     * @throws dml_exception if a database error occurs
     */
    public static function deleteRecords(string $table, array $conditions = []): bool
    {
        global $DB;

        return $DB->delete_records($table, $conditions);
    }

    /**
     * Checks if a record exists in a table.
     *
     * @param string $table      Table name
     * @param array  $conditions array of field => value conditions
     *
     * @return bool True if record exists, false otherwise
     *
     * @throws dml_exception if a database error occurs
     */
    public static function recordExists(string $table, array $conditions): bool
    {
        global $DB;

        return $DB->record_exists($table, $conditions);
    }

    /**
     * Starts a delegated database transaction.
     *
     * @return moodle_transaction the transaction object
     */
    public static function startDelegatedTransaction(): moodle_transaction
    {
        global $DB;

        return $DB->start_delegated_transaction();
    }

    /**
     * Executes a SQL query (for non-SELECT queries).
     *
     * @param string     $sql    the SQL query
     * @param null|array $params array of parameters for the SQL query
     *
     * @return bool True on success, false otherwise
     *
     * @throws dml_exception if a database error occurs
     */
    public static function execute(string $sql, ?array $params = null): bool
    {
        global $DB;

        return $DB->execute($sql, $params);
    }

    /**
     * Retrieves the SQL fragment for matching a full name.
     *
     * @param string $firstname the first name field name
     * @param string $lastname  the last name field name
     *
     * @return string the SQL fragment
     */
    public static function sqlFullname(string $firstname = 'firstname', string $lastname = 'lastname'): string
    {
        global $DB;

        return $DB->sql_fullname($firstname, $lastname);
    }

    /**
     * Retrieves the SQL fragment for a LIKE clause.
     *
     * @param string $field           Field name
     * @param string $param           The parameter name (e.g., :param).
     * @param bool   $casesensitive   whether to be case sensitive
     * @param bool   $accentsensitive whether to be accent sensitive
     * @param bool   $notlike         whether to use NOT LIKE
     * @param string $escapechar      escape character
     *
     * @return string the SQL fragment
     */
    public static function sqlLike(
        string $field,
        string $param,
        bool $casesensitive = true,
        bool $accentsensitive = true,
        bool $notlike = false,
        string $escapechar = '\\'
    ): string {
        global $DB;

        return $DB->sql_like($field, $param, $casesensitive, $accentsensitive, $notlike, $escapechar);
    }

    /**
     * Retrieves an IN or EQUAL SQL fragment.
     *
     * @param mixed  $items        the items to match
     * @param int    $type         the parameter type (SQL_PARAMS_NAMED or SQL_PARAMS_QM)
     * @param string $prefix       the parameter prefix
     * @param bool   $equal        whether to use = or IN
     * @param mixed  $onemptyitems behavior when items is empty
     *
     * @return array{0: string, 1: array} the SQL fragment and parameters
     */
    public static function getInOrEqual(
        mixed $items,
        int $type = SQL_PARAMS_NAMED,
        string $prefix = 'p',
        bool $equal = true,
        mixed $onemptyitems = false
    ): array {
        global $DB;

        return $DB->get_in_or_equal($items, $type, $prefix, $equal, $onemptyitems);
    }

    /**
     * Retrieves records as a menu (key => value array).
     *
     * @param string $table      Table name
     * @param array  $conditions array of field => value conditions
     * @param string $sort       SQL sort order
     * @param string $fields     Comma separated fields (first becomes key, second value)
     *
     * @return array<mixed, mixed> the menu array
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecordsMenu(string $table, array $conditions = [], string $sort = '', string $fields = '*'): array
    {
        global $DB;

        return $DB->get_records_menu($table, $conditions, $sort, $fields);
    }

    /**
     * Retrieves a recordset using a custom SQL query.
     *
     * @param string     $sql       the SQL query
     * @param null|array $params    array of parameters for the SQL query
     * @param int        $limitfrom return records starting from this index
     * @param int        $limitnum  return this many records
     *
     * @return moodle_recordset the recordset object
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecordsetSql(string $sql, ?array $params = null, int $limitfrom = 0, int $limitnum = 0): moodle_recordset
    {
        global $DB;

        return $DB->get_recordset_sql($sql, $params, $limitfrom, $limitnum);
    }

    /**
     * Retrieves the SQL fragment for comparing text fields.
     *
     * @param string $fieldname the field name
     *
     * @return string the SQL fragment
     */
    public static function sqlCompareText(string $fieldname): string
    {
        global $DB;

        return $DB->sql_compare_text($fieldname);
    }

    /**
     * Counts records in a table.
     *
     * @param string $table      Table name
     * @param array  $conditions array of field => value conditions
     *
     * @return int the number of records
     *
     * @throws dml_exception if a database error occurs
     */
    public static function countRecords(string $table, array $conditions = []): int
    {
        global $DB;

        return (int) $DB->count_records($table, $conditions);
    }

    /**
     * Counts records in a table matching a SQL WHERE clause.
     *
     * @param string     $table  table name
     * @param string     $select SQL WHERE clause (without WHERE keyword)
     * @param null|array $params array of parameters for the SQL query
     *
     * @return int the number of matching records
     *
     * @throws dml_exception if a database error occurs
     */
    public static function countRecordsSelect(string $table, string $select = '', ?array $params = null): int
    {
        global $DB;

        return (int) $DB->count_records_select($table, $select, $params);
    }

    /**
     * Counts records using a custom SQL query.
     *
     * @param string     $sql    the SQL query (must return a single COUNT value)
     * @param null|array $params array of parameters for the SQL query
     *
     * @return int the number of records
     *
     * @throws dml_exception if a database error occurs
     */
    public static function countRecordsSql(string $sql, ?array $params = null): int
    {
        global $DB;

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Escapes a value for use in a SQL LIKE clause.
     *
     * @param string $text       the text to escape
     * @param string $escapechar the escape character to use
     *
     * @return string the escaped text
     */
    public static function sqlLikeEscape(string $text, string $escapechar = '\\'): string
    {
        global $DB;

        return $DB->sql_like_escape($text, $escapechar);
    }

    /**
     * Updates a single field value in a table.
     *
     * @param string $table      Table name
     * @param string $newfield   the field to update
     * @param mixed  $newvalue   the new value for the field
     * @param array  $conditions array of field => value conditions
     *
     * @return bool True on success
     *
     * @throws dml_exception if a database error occurs
     */
    public static function setField(string $table, string $newfield, mixed $newvalue, array $conditions): bool
    {
        global $DB;

        return $DB->set_field($table, $newfield, $newvalue, $conditions);
    }

    /**
     * Retrieves the database family name (e.g. 'mysql', 'postgres').
     *
     * @return string the database family
     */
    public static function getDbfamily(): string
    {
        global $DB;

        return $DB->get_dbfamily();
    }

    /**
     * Retrieves database server version information.
     *
     * @return array the server info string
     */
    public static function getServerInfo(): array
    {
        global $DB;

        return $DB->get_server_info();
    }

    /**
     * Retrieves the database collation setting.
     *
     * @return ?string the collation string or null
     */
    public static function getDbcollation(): ?string
    {
        global $DB;

        return $DB->get_dbcollation();
    }

    /**
     * Checks whether a database table exists.
     *
     * @param string $table the table name (without prefix)
     *
     * @return bool True if the table exists
     */
    public static function tableExists(string $table): bool
    {
        global $DB;

        return $DB->get_manager()->table_exists($table);
    }

    /**
     * Delete one or more records from a table which match a particular WHERE clause.
     *
     * @param string     $table  the database table to be checked against
     * @param string     $select a fragment of SQL to be used in a where clause in the SQL call (used to define the selection criteria)
     * @param null|array $params array of sql parameters
     *
     * @return bool true
     *
     * @throws dml_exception a DML specific exception is thrown for any errors
     */
    public static function deleteRecordsSelect($table, $select, ?array $params = null)
    {
        global $DB;

        return $DB->delete_records_select($table, $select, $params);
    }

    /**
     * Get records matching a SQL WHERE clause.
     *
     * @param string     $table     table name
     * @param string     $select    SQL WHERE clause
     * @param null|array $params    query parameters
     * @param string     $sort      sort order
     * @param string     $fields    fields to return
     * @param int        $limitfrom skip this many rows
     * @param int        $limitnum  return this many rows
     *
     * @return array of objects
     *
     * @throws dml_exception
     */
    public static function getRecordsSelect(string $table, string $select = '', ?array $params = null, string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): array
    {
        global $DB;

        return $DB->get_records_select($table, $select, $params, $sort, $fields, $limitfrom, $limitnum);
    }

    /**
     * Get records as a menu (id => name) from a SQL query.
     *
     * @param string     $sql       SQL query
     * @param null|array $params    query parameters
     * @param int        $limitfrom skip this many rows
     * @param int        $limitnum  return this many rows
     *
     * @return array menu of id => name
     *
     * @throws dml_exception
     */
    public static function getRecordsSqlMenu(string $sql, ?array $params = null, int $limitfrom = 0, int $limitnum = 0): array
    {
        global $DB;

        return $DB->get_records_sql_menu($sql, $params, $limitfrom, $limitnum);
    }

    /**
     * Get a single column of values matching a SQL WHERE clause.
     *
     * @param string     $table  table name
     * @param string     $return field to return
     * @param string     $select SQL WHERE clause
     * @param null|array $params query parameters
     *
     * @return array of values
     *
     * @throws dml_exception
     */
    public static function getFieldsetSelect(string $table, string $return, string $select = '', ?array $params = null): array
    {
        global $DB;

        return $DB->get_fieldset_select($table, $return, $select, $params);
    }

    /**
     * Get a single column of values from a SQL query.
     *
     * @param string     $sql    SQL query
     * @param null|array $params query parameters
     *
     * @return array of values
     *
     * @throws dml_exception
     */
    public static function getFieldsetSql(string $sql, ?array $params = null): array
    {
        global $DB;

        return $DB->get_fieldset_sql($sql, $params);
    }

    /**
     * Get a recordset iterator matching a SQL WHERE clause.
     *
     * @param string     $table     table name
     * @param string     $select    SQL WHERE clause
     * @param null|array $params    query parameters
     * @param string     $sort      sort order
     * @param string     $fields    fields to return
     * @param int        $limitfrom skip this many rows
     * @param int        $limitnum  return this many rows
     *
     * @return moodle_recordset
     *
     * @throws dml_exception
     */
    public static function getRecordsetSelect(string $table, string $select = '', ?array $params = null, string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): moodle_recordset
    {
        global $DB;

        return $DB->get_recordset_select($table, $select, $params, $sort, $fields, $limitfrom, $limitnum);
    }

    /**
     * Get a recordset iterator from a table.
     *
     * @param string     $table      table name
     * @param null|array $conditions field => value conditions
     * @param string     $sort       sort order
     * @param string     $fields     fields to return
     * @param int        $limitfrom  skip this many rows
     * @param int        $limitnum   return this many rows
     *
     * @return moodle_recordset
     *
     * @throws dml_exception
     */
    public static function getRecordset(string $table, ?array $conditions = null, string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): moodle_recordset
    {
        global $DB;

        return $DB->get_recordset($table, $conditions, $sort, $fields, $limitfrom, $limitnum);
    }

    /**
     * Check if a record exists using a custom SQL query.
     *
     * @param string     $sql    SQL query
     * @param null|array $params query parameters
     *
     * @return bool
     *
     * @throws dml_exception
     */
    public static function recordExistsSql(string $sql, ?array $params = null): bool
    {
        global $DB;

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Return the SQL regex match operator for the active DML driver.
     *
     * Delegates to the driver's regex operator (e.g. "REGEXP"/"NOT REGEXP" on
     * MySQL, "~"/"!~" on PostgreSQL). Build the full clause as
     * `"$field " . DbSupport::sqlRegex() . " :param"`.
     *
     * @param bool $negated whether to negate the match (NOT REGEXP)
     *
     * @return string SQL regex operator fragment
     */
    public static function sqlRegex(bool $negated = false): string
    {
        global $DB;

        // Moodle's sql_regex($positivematch) expects the POSITIVE flag, so the
        // adapter's $negated must be inverted before delegating.
        return $DB->sql_regex(!$negated);
    }

    /**
     * Get the database manager (DDL operations).
     *
     * @return database_manager
     */
    public static function getManager(): database_manager
    {
        global $DB;

        return $DB->get_manager();
    }

    /**
     * Get column information for a table.
     *
     * @param string $table table name
     *
     * @return array of database_column_info objects
     */
    public static function getColumns(string $table): array
    {
        global $DB;

        return $DB->get_columns($table);
    }

    /**
     * Get all tables in the database.
     *
     * @return array of table names
     */
    public static function getTables(): array
    {
        global $DB;

        return $DB->get_tables();
    }
}
