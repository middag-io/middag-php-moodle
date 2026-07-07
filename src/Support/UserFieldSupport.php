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

use dml_exception;
use Middag\Framework\Shared\Util\Typing;
use Middag\Moodle\Domain\User\UserProfileField;
use Middag\Moodle\Domain\User\UserProfileFieldDataDto;
use Middag\Moodle\Shared\Util\Debug;
use stdClass;

// File-scope host-library include: runs at autoload, before any test's coverage window.
// @codeCoverageIgnoreStart
global $CFG;

require_once $CFG->dirroot . '/user/profile/lib.php';
// @codeCoverageIgnoreEnd

/**
 * Encapsulates Moodle's User Profile Fields API.
 *
 * Wraps `mdl_user_info_field` (definitions) and `mdl_user_info_data` (values).
 * The rest of the framework MUST NOT access these tables directly.
 *
 * @api
 *
 * @see ADR-201 Moodle boundary consolidation
 * @see ADR-203 Support layer pattern
 */
class UserFieldSupport
{
    /**
     * Parameter prefix for SQL fragments to avoid collisions in caller queries.
     */
    private const PARAM_PREFIX = 'upf_';

    /**
     * Resolves a profile field definition by ID or shortname.
     *
     * @param int|string $fieldidorshortname numeric field ID or shortname string
     *
     * @return null|UserProfileField field entity or null if not found
     */
    public static function getField(int|string $fieldidorshortname): ?UserProfileField
    {
        global $DB;

        try {
            if (is_numeric($fieldidorshortname)) {
                $record = $DB->get_record('user_info_field', ['id' => (int) $fieldidorshortname]);
            } else {
                $record = $DB->get_record('user_info_field', ['shortname' => $fieldidorshortname]);
            }

            if ($record === false) {
                return null;
            }

            return UserProfileField::fromRecord($record);
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Returns all profile field definitions indexed by shortname, ordered by sortorder.
     *
     * @return array<string, UserProfileField>
     */
    public static function getAllFields(): array
    {
        global $DB;

        try {
            $records = $DB->get_records('user_info_field', [], 'sortorder ASC');
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return [];
        }

        $fields = [];
        foreach ($records as $record) {
            $entity = UserProfileField::fromRecord($record);
            $fields[$entity->get_shortname()] = $entity;
        }

        return $fields;
    }

    /**
     * Returns field options for admin UI selects.
     *
     * Includes standard user fields plus all custom profile fields.
     * Profile field keys use the legacy format `profilefield_{id}`.
     *
     * @param bool $include_email whether to include the core 'email' field
     *
     * @return array<int|string, string> map of field identifier to label
     */
    public static function getFieldOptions(bool $include_email = false): array
    {
        $fields = [
            0 => '-- ' . LangSupport::getString('none') . ' --',
            'city' => LangSupport::getString('city'),
            'country' => LangSupport::getString('country'),
            'firstnamephonetic' => LangSupport::getString('firstnamephonetic'),
            'lastnamephonetic' => LangSupport::getString('lastnamephonetic'),
            'middlename' => LangSupport::getString('middlename'),
            'alternatename' => LangSupport::getString('alternatename'),
            'idnumber' => LangSupport::getString('idnumber'),
            'institution' => LangSupport::getString('institution'),
            'department' => LangSupport::getString('department'),
            'phone1' => LangSupport::getString('phone1'),
            'phone2' => LangSupport::getString('phone2'),
            'address' => LangSupport::getString('address'),
        ];

        if ($include_email) {
            $fields['email'] = LangSupport::getString('email');
        }

        $userfields = profile_get_user_fields_with_data(0);
        foreach ($userfields as $field) {
            $fields['profilefield_' . $field->fieldid] = LangSupport::getString('profilefield', 'admin') . ': ' . $field->field->name;
        }

        return $fields;
    }

    /**
     * Retrieves a user's profile field value by numeric field ID.
     *
     * @param int $userid  the user ID
     * @param int $fieldid the profile field ID
     *
     * @return null|UserProfileFieldDataDto DTO with field data or null if not found
     */
    public static function getUserData(int $userid, int $fieldid): ?UserProfileFieldDataDto
    {
        global $DB;

        try {
            $sql = 'SELECT uid.id, uid.userid, uid.fieldid, uif.shortname, uid.data, uid.dataformat
                    FROM {user_info_data} uid
                    JOIN {user_info_field} uif ON uif.id = uid.fieldid
                    WHERE uid.userid = :userid AND uid.fieldid = :fieldid';

            $record = $DB->get_record_sql($sql, ['userid' => $userid, 'fieldid' => $fieldid]);

            if ($record === false) {
                return null;
            }

            return self::buildDtoFromRecord($record);
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Retrieves a user's profile field value by shortname.
     *
     * @param int    $userid    the user ID
     * @param string $shortname the profile field shortname
     *
     * @return null|UserProfileFieldDataDto DTO with field data or null if not found
     */
    public static function getUserDataByShortname(int $userid, string $shortname): ?UserProfileFieldDataDto
    {
        global $DB;

        try {
            $sql = 'SELECT uid.id, uid.userid, uid.fieldid, uif.shortname, uid.data, uid.dataformat
                    FROM {user_info_data} uid
                    JOIN {user_info_field} uif ON uif.id = uid.fieldid
                    WHERE uid.userid = :userid AND uif.shortname = :shortname';

            $record = $DB->get_record_sql($sql, ['userid' => $userid, 'shortname' => $shortname]);

            if ($record === false) {
                return null;
            }

            return self::buildDtoFromRecord($record);
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Retrieves all profile field values for a user as a simple shortname-to-value map.
     *
     * @param int $userid the user ID
     *
     * @return array<string, string> map of shortname to data value
     */
    public static function getAllUserData(int $userid): array
    {
        $result = [];

        $userfields = profile_get_user_fields_with_data($userid);
        foreach ($userfields as $field) {
            $result[$field->get_shortname()] = (string) ($field->data ?? '');
        }

        return $result;
    }

    /**
     * Saves (upserts) a profile field value for a user.
     *
     * Resolves shortname from numeric field ID when necessary and delegates
     * to Moodle's `profile_save_custom_fields()`.
     *
     * @param int        $userid             the user ID
     * @param int|string $fieldidorshortname numeric field ID or shortname
     * @param string     $value              the value to save
     *
     * @return bool true on success, false on failure
     */
    public static function saveUserData(int $userid, int|string $fieldidorshortname, string $value): bool
    {
        global $DB;

        try {
            if (is_numeric($fieldidorshortname)) {
                $shortname = $DB->get_field('user_info_field', 'shortname', ['id' => (int) $fieldidorshortname]);

                if ($shortname === false) {
                    return false;
                }
            } else {
                $shortname = $fieldidorshortname;
            }

            profile_save_custom_fields($userid, [$shortname => $value]);

            return true;
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return false;
        }
    }

    /**
     * Builds a SQL subquery fragment that selects user IDs matching a profile field condition.
     *
     * The returned SQL fragment can be embedded in a caller's WHERE clause, e.g.:
     *   `WHERE u.id IN ({$fragment['sql']})`
     *
     * Uses the fixed prefix `upf_` for parameters to avoid collisions.
     * The caller is responsible for overall parameter uniqueness when combining
     * multiple fragments.
     *
     * @param int   $comparison_sql SQL condition applied to `uid.data` (e.g. `uid.data = :upf_value`)
     * @param array $params         parameters for the comparison SQL (caller must use `upf_` prefix)
     *
     * @return array{sql: string, params: array} SQL fragment and bound parameters
     */
    public static function buildUserSubquery(int $fieldid, string $comparison_sql, array $params): array
    {
        $param_prefix = self::PARAM_PREFIX;

        return [
            'sql' => sprintf('SELECT uid.userid FROM {user_info_data} uid WHERE uid.fieldid = :%sfieldid AND %s', $param_prefix, $comparison_sql),
            'params' => array_merge([$param_prefix . 'fieldid' => $fieldid], $params),
        ];
    }

    /**
     * Builds a DTO from a user_info_data + user_info_field joined record.
     *
     * @param stdClass $record database record with id, userid, fieldid, shortname, data, dataformat
     *
     * @return UserProfileFieldDataDto
     */
    private static function buildDtoFromRecord(stdClass $record): UserProfileFieldDataDto
    {
        return new UserProfileFieldDataDto(
            id: Typing::toInt($record->id),
            userid: (int) $record->userid,
            fieldid: (int) $record->fieldid,
            shortname: (string) $record->shortname,
            data: (string) ($record->data ?? ''),
            dataformat: (int) ($record->dataformat ?? 0),
        );
    }
}
