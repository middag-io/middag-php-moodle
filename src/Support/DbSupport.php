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
use Throwable;
use Traversable;

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
     * @param string     $table      Table name
     * @param null|array $conditions array of field => value conditions; null
     *                               (not []) deletes ALL rows via the host's
     *                               TRUNCATE fast path
     *
     * @return bool True on success, false otherwise
     *
     * @throws dml_exception if a database error occurs
     */
    public static function deleteRecords(string $table, ?array $conditions = null): bool
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

    /**
     * Retrieves records as a menu (key => value) matching a SQL WHERE clause.
     *
     * @param string     $table     table name
     * @param string     $select    SQL WHERE clause
     * @param null|array $params    query parameters
     * @param string     $sort      sort order
     * @param string     $fields    comma separated fields (first becomes key, second value)
     * @param int        $limitfrom skip this many rows
     * @param int        $limitnum  return this many rows
     *
     * @return array<mixed, mixed> the menu array
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecordsSelectMenu(string $table, string $select = '', ?array $params = null, string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): array
    {
        global $DB;

        return $DB->get_records_select_menu($table, $select, $params, $sort, $fields, $limitfrom, $limitnum);
    }

    /**
     * Retrieves a single record matching a SQL WHERE clause.
     *
     * @param string     $table      table name
     * @param string     $select     SQL WHERE clause
     * @param null|array $params     query parameters
     * @param string     $fields     comma separated list of fields
     * @param int        $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|stdClass the record object or null if not found
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecordSelect(string $table, string $select = '', ?array $params = null, string $fields = '*', int $strictness = IGNORE_MISSING): ?stdClass
    {
        global $DB;

        $record = $DB->get_record_select($table, $select, $params, $fields, $strictness);

        return $record ?: null;
    }

    /**
     * Retrieves a single record using a custom SQL query.
     *
     * @param string     $sql        the SQL query
     * @param null|array $params     query parameters
     * @param int        $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|stdClass the record object or null if not found
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecordSql(string $sql, ?array $params = null, int $strictness = IGNORE_MISSING): ?stdClass
    {
        global $DB;

        $record = $DB->get_record_sql($sql, $params, $strictness);

        return $record ?: null;
    }

    /**
     * Retrieves a single field value matching a SQL WHERE clause.
     *
     * @param string     $table      table name
     * @param string     $return     field name to return
     * @param string     $select     SQL WHERE clause
     * @param null|array $params     query parameters
     * @param int        $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return mixed the field value or false if not found
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getFieldSelect(string $table, string $return, string $select, ?array $params = null, int $strictness = IGNORE_MISSING): mixed
    {
        global $DB;

        return $DB->get_field_select($table, $return, $select, $params, $strictness);
    }

    /**
     * Get a single column of values from a table.
     *
     * @param string     $table      table name
     * @param string     $return     field to return
     * @param null|array $conditions field => value conditions
     *
     * @return array of values
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getFieldset(string $table, string $return, ?array $conditions = null): array
    {
        global $DB;

        return $DB->get_fieldset($table, $return, $conditions);
    }

    /**
     * Retrieves records where a field matches a list of values.
     *
     * @param string $table     table name
     * @param string $field     field to match against
     * @param array  $values    list of values
     * @param string $sort      sort order
     * @param string $fields    comma separated list of fields
     * @param int    $limitfrom skip this many rows
     * @param int    $limitnum  return this many rows
     *
     * @return array<int|string, stdClass> list of record objects
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecordsList(string $table, string $field, array $values, string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): array
    {
        global $DB;

        return $DB->get_records_list($table, $field, $values, $sort, $fields, $limitfrom, $limitnum);
    }

    /**
     * Get a recordset iterator where a field matches a list of values.
     *
     * @param string $table     table name
     * @param string $field     field to match against
     * @param array  $values    list of values
     * @param string $sort      sort order
     * @param string $fields    comma separated list of fields
     * @param int    $limitfrom skip this many rows
     * @param int    $limitnum  return this many rows
     *
     * @return moodle_recordset
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getRecordsetList(string $table, string $field, array $values, string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): moodle_recordset
    {
        global $DB;

        return $DB->get_recordset_list($table, $field, $values, $sort, $fields, $limitfrom, $limitnum);
    }

    /**
     * Retrieves paginated records plus the un-limited total via a window function.
     *
     * @param string     $sql             the SQL query
     * @param string     $fullcountcolumn column alias that receives the full count
     * @param string     $sort            sort order appended to the query
     * @param null|array $params          query parameters
     * @param int        $limitfrom       skip this many rows
     * @param int        $limitnum        return this many rows
     *
     * @return array<int|string, stdClass> list of record objects
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getCountedRecordsSql(string $sql, string $fullcountcolumn, string $sort = '', ?array $params = null, int $limitfrom = 0, int $limitnum = 0): array
    {
        global $DB;

        return $DB->get_counted_records_sql($sql, $fullcountcolumn, $sort, $params, $limitfrom, $limitnum);
    }

    /**
     * Recordset variant of {@see getCountedRecordsSql}.
     *
     * @param string     $sql             the SQL query
     * @param string     $fullcountcolumn column alias that receives the full count
     * @param string     $sort            sort order appended to the query
     * @param null|array $params          query parameters
     * @param int        $limitfrom       skip this many rows
     * @param int        $limitnum        return this many rows
     *
     * @return moodle_recordset
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getCountedRecordsetSql(string $sql, string $fullcountcolumn, string $sort = '', ?array $params = null, int $limitfrom = 0, int $limitnum = 0): moodle_recordset
    {
        global $DB;

        return $DB->get_counted_recordset_sql($sql, $fullcountcolumn, $sort, $params, $limitfrom, $limitnum);
    }

    /**
     * Get a recordset over an entire table (backup/export bulk reads).
     *
     * @param string $table table name
     *
     * @return moodle_recordset
     *
     * @throws dml_exception if a database error occurs
     */
    public static function exportTableRecordset(string $table): moodle_recordset
    {
        global $DB;

        return $DB->export_table_recordset($table);
    }

    /**
     * Check if a record exists matching a SQL WHERE clause.
     *
     * @param string     $table  table name
     * @param string     $select SQL WHERE clause
     * @param null|array $params query parameters
     *
     * @return bool
     *
     * @throws dml_exception if a database error occurs
     */
    public static function recordExistsSelect(string $table, string $select = '', ?array $params = null): bool
    {
        global $DB;

        return $DB->record_exists_select($table, $select, $params);
    }

    /**
     * Bulk-inserts multiple records (no ids are returned).
     *
     * @param string            $table       table name
     * @param array|Traversable $dataobjects records to insert
     *
     * @throws dml_exception if a database error occurs
     */
    public static function insertRecords(string $table, array|Traversable $dataobjects): void
    {
        global $DB;

        $DB->insert_records($table, $dataobjects);
    }

    /**
     * Low-level insert without safety checks (restore/import flows only).
     *
     * Returns 1 when $returnid is false (Moodle returns bool true).
     *
     * @param string         $table          table name
     * @param array|stdClass $params         the data record
     * @param bool           $returnid       whether to return the new record ID
     * @param bool           $bulk           whether this is a bulk insert
     * @param bool           $customsequence whether the id is supplied by the caller
     *
     * @return int the new record ID (if $returnid is true)
     *
     * @throws dml_exception if a database error occurs
     */
    public static function insertRecordRaw(string $table, array|stdClass $params, bool $returnid = true, bool $bulk = false, bool $customsequence = false): int
    {
        global $DB;

        return (int) $DB->insert_record_raw($table, $params, $returnid, $bulk, $customsequence);
    }

    /**
     * Low-level update without safety checks (restore/import flows only).
     *
     * @param string         $table  table name
     * @param array|stdClass $params the data record with new values (must include 'id')
     * @param bool           $bulk   whether this is a bulk update
     *
     * @return bool True on success
     *
     * @throws dml_exception if a database error occurs
     */
    public static function updateRecordRaw(string $table, array|stdClass $params, bool $bulk = false): bool
    {
        global $DB;

        return $DB->update_record_raw($table, $params, $bulk);
    }

    /**
     * Imports a record preserving its id (restore flows; no safety checks).
     *
     * @param string         $table      table name
     * @param array|stdClass $dataobject the data record (must include 'id')
     *
     * @return bool True on success
     *
     * @throws dml_exception if a database error occurs
     */
    public static function importRecord(string $table, array|stdClass $dataobject): bool
    {
        global $DB;

        return $DB->import_record($table, $dataobject);
    }

    /**
     * Updates a single field for rows matching a SQL WHERE clause.
     *
     * @param string     $table    table name
     * @param string     $newfield the field to update
     * @param mixed      $newvalue the new value for the field
     * @param string     $select   SQL WHERE clause
     * @param null|array $params   query parameters
     *
     * @return bool True on success
     *
     * @throws dml_exception if a database error occurs
     */
    public static function setFieldSelect(string $table, string $newfield, mixed $newvalue, string $select, ?array $params = null): bool
    {
        global $DB;

        return $DB->set_field_select($table, $newfield, $newvalue, $select, $params);
    }

    /**
     * Deletes records where a field matches a list of values.
     *
     * @param string $table  table name
     * @param string $field  field to match against
     * @param array  $values list of values
     *
     * @return bool True on success
     *
     * @throws dml_exception if a database error occurs
     */
    public static function deleteRecordsList(string $table, string $field, array $values): bool
    {
        global $DB;

        return $DB->delete_records_list($table, $field, $values);
    }

    /**
     * Deletes records where a field matches the result of a subquery.
     *
     * @param string $table    table name
     * @param string $field    field to match against
     * @param string $alias    subquery column alias ($alias is required for MySQL)
     * @param string $subquery the subquery producing the values
     * @param array  $params   query parameters
     *
     * @throws dml_exception if a database error occurs
     */
    public static function deleteRecordsSubquery(string $table, string $field, string $alias, string $subquery, array $params = []): void
    {
        global $DB;

        $DB->delete_records_subquery($table, $field, $alias, $subquery, $params);
    }

    /**
     * Commits a delegated transaction.
     *
     * @param moodle_transaction $transaction the transaction object
     *
     * @throws dml_exception if a database error occurs
     */
    public static function commitDelegatedTransaction(moodle_transaction $transaction): void
    {
        global $DB;

        $DB->commit_delegated_transaction($transaction);
    }

    /**
     * Rolls back a delegated transaction and rethrows the given exception.
     *
     * @param moodle_transaction $transaction the transaction object
     * @param Throwable          $e           the exception that caused the rollback (always rethrown)
     *
     * @throws Throwable
     */
    public static function rollbackDelegatedTransaction(moodle_transaction $transaction, Throwable $e): never
    {
        global $DB;

        $DB->rollback_delegated_transaction($transaction, $e);

        // rollback_delegated_transaction() always rethrows; this is unreachable
        // but keeps the `never` contract explicit for static analysis.
        throw $e;
    }

    /**
     * Checks whether a transaction is in progress.
     *
     * @return bool
     */
    public static function isTransactionStarted(): bool
    {
        global $DB;

        return $DB->is_transaction_started();
    }

    /**
     * Throws if a transaction is in progress (guard for non-transactional code).
     *
     * @throws dml_exception if a transaction is active
     */
    public static function transactionsForbidden(): void
    {
        global $DB;

        $DB->transactions_forbidden();
    }

    /**
     * Retrieves the database table prefix (useful in raw SQL).
     *
     * @return string
     */
    public static function getPrefix(): string
    {
        global $DB;

        return $DB->get_prefix();
    }

    /**
     * Retrieves the database vendor name (e.g. 'mysql', 'mariadb', 'postgres').
     *
     * @return string
     */
    public static function getDbvendor(): string
    {
        global $DB;

        return $DB->get_dbvendor();
    }

    /**
     * Get index information for a table.
     *
     * @param string $table table name
     *
     * @return array of index details
     */
    public static function getIndexes(string $table): array
    {
        global $DB;

        return $DB->get_indexes($table);
    }

    /**
     * Resets the internal column/table caches (after DDL outside database_manager).
     *
     * @param null|array $tablenames tables to reset, or null for all
     */
    public static function resetCaches(?array $tablenames = null): void
    {
        global $DB;

        $DB->reset_caches($tablenames);
    }

    /**
     * Checks whether the driver supports fulltext search.
     *
     * @return bool
     */
    public static function isFulltextSearchSupported(): bool
    {
        global $DB;

        return $DB->is_fulltext_search_supported();
    }

    /**
     * Checks whether the driver supports the COUNT window function.
     *
     * @return bool
     */
    public static function isCountWindowFunctionSupported(): bool
    {
        global $DB;

        return $DB->is_count_window_function_supported();
    }

    /**
     * Retrieves the SQL fragment concatenating the given expressions.
     *
     * @param string ...$arr expressions to concatenate
     *
     * @return string the SQL fragment
     */
    public static function sqlConcat(string ...$arr): string
    {
        global $DB;

        return $DB->sql_concat(...$arr);
    }

    /**
     * Retrieves the SQL fragment concatenating expressions with a separator.
     *
     * @param string $separator SQL literal used between elements
     * @param array  $elements  expressions to concatenate
     *
     * @return string the SQL fragment
     */
    public static function sqlConcatJoin(string $separator = "' '", array $elements = []): string
    {
        global $DB;

        return $DB->sql_concat_join($separator, $elements);
    }

    /**
     * Retrieves the SQL fragment aggregating grouped values into one string.
     *
     * @param string $field     field or expression to aggregate
     * @param string $separator string used between values
     * @param string $sort      sort order of the aggregated values
     *
     * @return string the SQL fragment
     */
    public static function sqlGroupConcat(string $field, string $separator = ', ', string $sort = ''): string
    {
        global $DB;

        return $DB->sql_group_concat($field, $separator, $sort);
    }

    /**
     * Retrieves the SQL fragment for an (in)equality comparison.
     *
     * @param string $fieldname       field name
     * @param string $param           the parameter placeholder (e.g. :param)
     * @param bool   $casesensitive   whether to be case sensitive
     * @param bool   $accentsensitive whether to be accent sensitive
     * @param bool   $notequal        whether to negate the comparison
     *
     * @return string the SQL fragment
     */
    public static function sqlEqual(string $fieldname, string $param, bool $casesensitive = true, bool $accentsensitive = true, bool $notequal = false): string
    {
        global $DB;

        return $DB->sql_equal($fieldname, $param, $casesensitive, $accentsensitive, $notequal);
    }

    /**
     * Retrieves the SQL fragment returning the length of a field.
     *
     * @param string $fieldname field name
     *
     * @return string the SQL fragment
     */
    public static function sqlLength(string $fieldname): string
    {
        global $DB;

        return $DB->sql_length($fieldname);
    }

    /**
     * Retrieves the SQL fragment extracting a substring.
     *
     * @param string $expr   expression to cut
     * @param mixed  $start  start position (1-based) or SQL expression
     * @param mixed  $length substring length or SQL expression; false = to the end
     *
     * @return string the SQL fragment
     */
    public static function sqlSubstr(string $expr, mixed $start, mixed $length = false): string
    {
        global $DB;

        return $DB->sql_substr($expr, $start, $length);
    }

    /**
     * Retrieves the SQL fragment locating a needle inside a haystack.
     *
     * @param string $needle   SQL expression searched for
     * @param string $haystack SQL expression searched in
     *
     * @return string the SQL fragment
     */
    public static function sqlPosition(string $needle, string $haystack): string
    {
        global $DB;

        return $DB->sql_position($needle, $haystack);
    }

    /**
     * Retrieves the SQL fragment for ordering by a text field.
     *
     * @param string $fieldname field name
     * @param int    $numchars  number of chars to sort by
     *
     * @return string the SQL fragment
     */
    public static function sqlOrderByText(string $fieldname, int $numchars = 32): string
    {
        global $DB;

        return $DB->sql_order_by_text($fieldname, $numchars);
    }

    /**
     * Retrieves the SQL fragment sorting nulls first/last consistently.
     *
     * @param string $fieldname field name
     * @param int    $sort      SORT_ASC or SORT_DESC
     *
     * @return string the SQL fragment
     */
    public static function sqlOrderByNull(string $fieldname, int $sort = SORT_ASC): string
    {
        global $DB;

        return $DB->sql_order_by_null($fieldname, $sort);
    }

    /**
     * Retrieves the FROM clause required for SELECTs without a table.
     *
     * @return string the SQL fragment
     */
    public static function sqlNullFromClause(): string
    {
        global $DB;

        return $DB->sql_null_from_clause();
    }

    /**
     * Retrieves the SQL fragment for a bitwise AND.
     *
     * @param int|string $int1 first operand (integer or field/expression)
     * @param int|string $int2 second operand (integer or field/expression)
     *
     * @return string the SQL fragment
     */
    public static function sqlBitand(int|string $int1, int|string $int2): string
    {
        global $DB;

        return $DB->sql_bitand($int1, $int2);
    }

    /**
     * Retrieves the SQL fragment for a bitwise NOT.
     *
     * @param int|string $int1 operand (integer or field/expression)
     *
     * @return string the SQL fragment
     */
    public static function sqlBitnot(int|string $int1): string
    {
        global $DB;

        return $DB->sql_bitnot($int1);
    }

    /**
     * Retrieves the SQL fragment for a bitwise OR.
     *
     * @param int|string $int1 first operand (integer or field/expression)
     * @param int|string $int2 second operand (integer or field/expression)
     *
     * @return string the SQL fragment
     */
    public static function sqlBitor(int|string $int1, int|string $int2): string
    {
        global $DB;

        return $DB->sql_bitor($int1, $int2);
    }

    /**
     * Retrieves the SQL fragment for a bitwise XOR.
     *
     * @param int|string $int1 first operand (integer or field/expression)
     * @param int|string $int2 second operand (integer or field/expression)
     *
     * @return string the SQL fragment
     */
    public static function sqlBitxor(int|string $int1, int|string $int2): string
    {
        global $DB;

        return $DB->sql_bitxor($int1, $int2);
    }

    /**
     * Retrieves the SQL fragment for a modulo operation.
     *
     * @param int|string $int1 dividend (integer or field/expression)
     * @param int|string $int2 divisor (integer or field/expression)
     *
     * @return string the SQL fragment
     */
    public static function sqlModulo(int|string $int1, int|string $int2): string
    {
        global $DB;

        return $DB->sql_modulo($int1, $int2);
    }

    /**
     * Retrieves the SQL fragment rounding up to the next integer.
     *
     * @param string $fieldname field name or numeric expression
     *
     * @return string the SQL fragment
     */
    public static function sqlCeil(string $fieldname): string
    {
        global $DB;

        return $DB->sql_ceil($fieldname);
    }

    /**
     * Retrieves the SQL fragment casting a field to char.
     *
     * @param string $field field name or expression
     *
     * @return string the SQL fragment
     */
    public static function sqlCastToChar(string $field): string
    {
        global $DB;

        return $DB->sql_cast_to_char($field);
    }

    /**
     * Retrieves the SQL fragment casting a char field to integer.
     *
     * @param string $fieldname field name
     * @param bool   $text      whether the field is a TEXT column
     *
     * @return string the SQL fragment
     */
    public static function sqlCastChar2int(string $fieldname, bool $text = false): string
    {
        global $DB;

        return $DB->sql_cast_char2int($fieldname, $text);
    }

    /**
     * Retrieves the SQL fragment casting a char field to real.
     *
     * @param string $fieldname field name
     * @param bool   $text      whether the field is a TEXT column
     *
     * @return string the SQL fragment
     */
    public static function sqlCastChar2real(string $fieldname, bool $text = false): string
    {
        global $DB;

        return $DB->sql_cast_char2real($fieldname, $text);
    }

    /**
     * Retrieves the SQL fragment testing a field for emptiness.
     *
     * @param string $tablename     table name
     * @param string $fieldname     field name
     * @param bool   $nullablefield whether the field is nullable
     * @param bool   $textfield     whether the field is a TEXT column
     *
     * @return string the SQL fragment
     */
    public static function sqlIsempty(string $tablename, string $fieldname, bool $nullablefield, bool $textfield): string
    {
        global $DB;

        return $DB->sql_isempty($tablename, $fieldname, $nullablefield, $textfield);
    }

    /**
     * Retrieves the SQL fragment testing a field for non-emptiness.
     *
     * @param string $tablename     table name
     * @param string $fieldname     field name
     * @param bool   $nullablefield whether the field is nullable
     * @param bool   $textfield     whether the field is a TEXT column
     *
     * @return string the SQL fragment
     */
    public static function sqlIsnotempty(string $tablename, string $fieldname, bool $nullablefield, bool $textfield): string
    {
        global $DB;

        return $DB->sql_isnotempty($tablename, $fieldname, $nullablefield, $textfield);
    }

    /**
     * Checks whether the driver supports regex matching (pair of {@see sqlRegex}).
     *
     * @return bool
     */
    public static function sqlRegexSupported(): bool
    {
        global $DB;

        return $DB->sql_regex_supported();
    }

    /**
     * Retrieves the driver's word-beginning boundary marker for regexes.
     *
     * @return string the marker fragment
     */
    public static function sqlRegexGetWordBeginningBoundaryMarker(): string
    {
        global $DB;

        return $DB->sql_regex_get_word_beginning_boundary_marker();
    }

    /**
     * Retrieves the driver's word-end boundary marker for regexes.
     *
     * @return string the marker fragment
     */
    public static function sqlRegexGetWordEndBoundaryMarker(): string
    {
        global $DB;

        return $DB->sql_regex_get_word_end_boundary_marker();
    }

    /**
     * Retrieves the SQL fragment intersecting multiple SELECTs.
     *
     * @param array  $selects the SELECT queries to intersect
     * @param string $fields  comma separated list of fields
     *
     * @return string the SQL fragment
     */
    public static function sqlIntersect(array $selects, string $fields): string
    {
        global $DB;

        return $DB->sql_intersect($selects, $fields);
    }

    /**
     * Number of read queries executed so far.
     *
     * @return int
     */
    public static function perfGetReads(): int
    {
        global $DB;

        return $DB->perf_get_reads();
    }

    /**
     * Number of write queries executed so far.
     *
     * @return int
     */
    public static function perfGetWrites(): int
    {
        global $DB;

        return $DB->perf_get_writes();
    }

    /**
     * Total number of queries executed so far.
     *
     * @return int
     */
    public static function perfGetQueries(): int
    {
        global $DB;

        return $DB->perf_get_queries();
    }

    /**
     * Time spent in queries so far, in seconds.
     *
     * @return float
     */
    public static function perfGetQueriesTime(): float
    {
        global $DB;

        return $DB->perf_get_queries_time();
    }

    /**
     * Whether the connection prefers the read replica.
     *
     * Moodle 5.0 renamed want_read_slave() to want_read_replica() (MDL-71257);
     * the guard keeps 4.5 compatibility.
     *
     * @return bool
     */
    public static function wantReadReplica(): bool
    {
        global $DB;

        if (method_exists($DB, 'want_read_replica')) {
            return $DB->want_read_replica();
        }

        return $DB->want_read_slave();
    }

    /**
     * Number of reads served by the read replica.
     *
     * Moodle 5.0 renamed perf_get_reads_slave() to perf_get_reads_replica()
     * (MDL-71257); the guard keeps 4.5 compatibility.
     *
     * @return int
     */
    public static function perfGetReadsReplica(): int
    {
        global $DB;

        if (method_exists($DB, 'perf_get_reads_replica')) {
            return $DB->perf_get_reads_replica();
        }

        return $DB->perf_get_reads_slave();
    }

    /**
     * Pins subsequent reads of the given tables to the primary connection.
     *
     * Only exists since Moodle 5.2 — a no-op on older versions.
     *
     * @param string ...$tables table names
     */
    public static function markTablesForPrimary(string ...$tables): void
    {
        global $DB;

        if (method_exists($DB, 'mark_tables_for_primary')) {
            $DB->mark_tables_for_primary(...$tables);
        }
    }
}
