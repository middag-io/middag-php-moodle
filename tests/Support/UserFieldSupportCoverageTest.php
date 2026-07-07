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

use dml_exception;
use Middag\Moodle\Domain\User\UserProfileField;
use Middag\Moodle\Domain\User\UserProfileFieldDataDto;
use Middag\Moodle\Support\UserFieldSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * UserFieldSupport wraps user/profile/lib.php and the global $DB. The DB is
 * replaced with a recording double; profile_* functions are driven from
 * tests/stubs/support/groups.php. The get_string() handler is neutralised so
 * LangSupport::getString() yields the deterministic marker.
 *
 * @internal
 */
#[CoversClass(UserFieldSupport::class)]
final class UserFieldSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    private mixed $prevDb;

    private mixed $prevGetString;

    private object $db;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevDb = $GLOBALS['DB'] ?? null;
        $this->prevGetString = $GLOBALS['__middag_test_get_string'] ?? null;

        // UserFieldSupport require_once's $CFG->dirroot . '/user/profile/lib.php'
        // at file scope.
        $base = sys_get_temp_dir() . '/middag_support_groups_stubs';
        if (!is_dir($base . '/user/profile')) {
            mkdir($base . '/user/profile', 0o777, true);
        }
        file_put_contents($base . '/user/profile/lib.php', "<?php\n");
        $GLOBALS['CFG'] = (object) ['dirroot' => $base, 'libdir' => $base . '/lib'];

        $this->db = $this->makeDb();
        $GLOBALS['DB'] = $this->db;

        unset(
            $GLOBALS['__middag_test_get_string'],
            $GLOBALS['__middag_test_profile_fields'],
            $GLOBALS['__middag_test_saved_profile_fields'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['DB'] = $this->prevDb;
        $GLOBALS['__middag_test_get_string'] = $this->prevGetString;

        unset(
            $GLOBALS['__middag_test_profile_fields'],
            $GLOBALS['__middag_test_saved_profile_fields'],
        );
    }

    #[Test]
    public function testGetFieldResolvesByNumericId(): void
    {
        $this->db->record = (object) ['id' => 5, 'shortname' => 'dob', 'name' => 'Date of birth', 'datatype' => 'datetime'];

        $field = UserFieldSupport::getField(5);

        self::assertInstanceOf(UserProfileField::class, $field);
        self::assertSame('dob', $field->get_shortname());
    }

    #[Test]
    public function testGetFieldResolvesByShortname(): void
    {
        $this->db->record = (object) ['id' => 5, 'shortname' => 'dob', 'name' => 'Date of birth', 'datatype' => 'datetime'];

        $field = UserFieldSupport::getField('dob');

        self::assertInstanceOf(UserProfileField::class, $field);
        self::assertSame('dob', $field->get_shortname());
    }

    #[Test]
    public function testGetFieldReturnsNullWhenNotFound(): void
    {
        $this->db->record = false;

        self::assertNull(UserFieldSupport::getField(5));
    }

    #[Test]
    public function testGetFieldReturnsNullWhenTheQueryThrows(): void
    {
        $this->db->recordThrow = true;

        self::assertNull(UserFieldSupport::getField(5));
    }

    #[Test]
    public function testGetAllFieldsIndexesByShortname(): void
    {
        $this->db->records = [
            (object) ['id' => 1, 'shortname' => 'dob', 'sortorder' => 1],
            (object) ['id' => 2, 'shortname' => 'gender', 'sortorder' => 2],
        ];

        $fields = UserFieldSupport::getAllFields();

        self::assertArrayHasKey('dob', $fields);
        self::assertArrayHasKey('gender', $fields);
        self::assertInstanceOf(UserProfileField::class, $fields['dob']);
    }

    #[Test]
    public function testGetAllFieldsReturnsEmptyWhenTheQueryThrows(): void
    {
        $this->db->recordsThrow = true;

        self::assertSame([], UserFieldSupport::getAllFields());
    }

    #[Test]
    public function testGetFieldOptionsIncludesStandardAndCustomFields(): void
    {
        $GLOBALS['__middag_test_profile_fields'] = [$this->makeProfileField(9, 'Custom Field')];

        $options = UserFieldSupport::getFieldOptions();

        self::assertArrayHasKey(0, $options);
        self::assertArrayHasKey('city', $options);
        self::assertArrayNotHasKey('email', $options);
        self::assertArrayHasKey('profilefield_9', $options);
        self::assertStringContainsString('Custom Field', $options['profilefield_9']);
    }

    #[Test]
    public function testGetFieldOptionsAddsEmailWhenRequested(): void
    {
        $GLOBALS['__middag_test_profile_fields'] = [];

        $options = UserFieldSupport::getFieldOptions(true);

        self::assertArrayHasKey('email', $options);
    }

    #[Test]
    public function testGetUserDataReturnsADtoWhenFound(): void
    {
        $this->db->recordSql = (object) [
            'id' => 3, 'userid' => 5, 'fieldid' => 9, 'shortname' => 'dob', 'data' => '2000-01-01', 'dataformat' => 0,
        ];

        $dto = UserFieldSupport::getUserData(5, 9);

        self::assertInstanceOf(UserProfileFieldDataDto::class, $dto);
        self::assertSame(5, $dto->userid);
        self::assertSame('dob', $dto->shortname);
        self::assertSame('2000-01-01', $dto->data);
    }

    #[Test]
    public function testGetUserDataReturnsNullWhenNotFound(): void
    {
        $this->db->recordSql = false;

        self::assertNull(UserFieldSupport::getUserData(5, 9));
    }

    #[Test]
    public function testGetUserDataReturnsNullWhenTheQueryThrows(): void
    {
        $this->db->recordSqlThrow = true;

        self::assertNull(UserFieldSupport::getUserData(5, 9));
    }

    #[Test]
    public function testGetUserDataByShortnameReturnsADtoWhenFound(): void
    {
        $this->db->recordSql = (object) [
            'id' => 3, 'userid' => 5, 'fieldid' => 9, 'shortname' => 'dob', 'data' => '2000-01-01', 'dataformat' => 0,
        ];

        $dto = UserFieldSupport::getUserDataByShortname(5, 'dob');

        self::assertInstanceOf(UserProfileFieldDataDto::class, $dto);
        self::assertSame(9, $dto->fieldid);
    }

    #[Test]
    public function testGetUserDataByShortnameReturnsNullWhenNotFound(): void
    {
        $this->db->recordSql = false;

        self::assertNull(UserFieldSupport::getUserDataByShortname(5, 'dob'));
    }

    #[Test]
    public function testGetUserDataByShortnameReturnsNullWhenTheQueryThrows(): void
    {
        $this->db->recordSqlThrow = true;

        self::assertNull(UserFieldSupport::getUserDataByShortname(5, 'dob'));
    }

    #[Test]
    public function testGetAllUserDataMapsShortnameToValue(): void
    {
        $GLOBALS['__middag_test_profile_fields'] = [
            $this->makeUserDataField('dob', '2000-01-01'),
            $this->makeUserDataField('bio', null),
        ];

        $data = UserFieldSupport::getAllUserData(5);

        self::assertSame('2000-01-01', $data['dob']);
        self::assertSame('', $data['bio']);
    }

    #[Test]
    public function testSaveUserDataResolvesNumericFieldId(): void
    {
        $this->db->field = 'dob';

        self::assertTrue(UserFieldSupport::saveUserData(5, 9, 'value'));
        self::assertSame([[5, ['dob' => 'value']]], $GLOBALS['__middag_test_saved_profile_fields']);
    }

    #[Test]
    public function testSaveUserDataReturnsFalseWhenNumericFieldIsUnknown(): void
    {
        $this->db->field = false;

        self::assertFalse(UserFieldSupport::saveUserData(5, 9, 'value'));
        self::assertArrayNotHasKey('__middag_test_saved_profile_fields', $GLOBALS);
    }

    #[Test]
    public function testSaveUserDataUsesShortnameDirectly(): void
    {
        self::assertTrue(UserFieldSupport::saveUserData(5, 'dob', 'value'));
        self::assertSame([[5, ['dob' => 'value']]], $GLOBALS['__middag_test_saved_profile_fields']);
    }

    #[Test]
    public function testSaveUserDataReturnsFalseWhenTheLookupThrows(): void
    {
        $this->db->fieldThrow = true;

        self::assertFalse(UserFieldSupport::saveUserData(5, 9, 'value'));
    }

    #[Test]
    public function testBuildUserSubqueryComposesSqlAndParams(): void
    {
        $fragment = UserFieldSupport::buildUserSubquery(9, 'uid.data = :upf_value', ['upf_value' => 'x']);

        self::assertSame(
            'SELECT uid.userid FROM {user_info_data} uid WHERE uid.fieldid = :upf_fieldid AND uid.data = :upf_value',
            $fragment['sql']
        );
        self::assertSame(['upf_fieldid' => 9, 'upf_value' => 'x'], $fragment['params']);
    }

    private function makeProfileField(int $fieldid, string $name): object
    {
        return new class($fieldid, $name) {
            public object $field;

            public function __construct(public int $fieldid, string $name)
            {
                $this->field = (object) ['name' => $name];
            }
        };
    }

    private function makeUserDataField(string $shortname, ?string $data): object
    {
        return new class($shortname, $data) {
            public function __construct(public string $shortnameValue, public ?string $data) {}

            public function get_shortname(): string
            {
                return $this->shortnameValue;
            }
        };
    }

    private function makeDb(): object
    {
        return new class {
            public mixed $record = false;

            public bool $recordThrow = false;

            /** @var array<int|string, mixed> */
            public array $records = [];

            public bool $recordsThrow = false;

            public mixed $recordSql = false;

            public bool $recordSqlThrow = false;

            public mixed $field = false;

            public bool $fieldThrow = false;

            public function get_record($table, $conditions = null, $fields = '*', $strictness = 0): mixed
            {
                if ($this->recordThrow) {
                    throw new dml_exception('db failure');
                }

                return $this->record;
            }

            /**
             * @param mixed      $table
             * @param null|mixed $conditions
             * @param mixed      $sort
             * @param mixed      $fields
             * @param mixed      $limitfrom
             * @param mixed      $limitnum
             *
             * @return array<int|string, mixed>
             */
            public function get_records($table, $conditions = null, $sort = '', $fields = '*', $limitfrom = 0, $limitnum = 0): array
            {
                if ($this->recordsThrow) {
                    throw new dml_exception('db failure');
                }

                return $this->records;
            }

            public function get_record_sql($sql, $params = null, $strictness = 0): mixed
            {
                if ($this->recordSqlThrow) {
                    throw new dml_exception('db failure');
                }

                return $this->recordSql;
            }

            public function get_field($table, $return, $conditions = null, $strictness = 0): mixed
            {
                if ($this->fieldThrow) {
                    throw new dml_exception('db failure');
                }

                return $this->field;
            }
        };
    }
}
