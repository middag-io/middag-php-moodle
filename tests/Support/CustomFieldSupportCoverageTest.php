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

use Middag\Moodle\Support\CustomFieldSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CustomFieldSupport wraps core_customfield\handler. The handler stand-in
 * (tests/stubs/support/groups.php) returns test-provided field/controller
 * doubles via $GLOBALS and throws on $GLOBALS['__middag_test_cf_throw'] so every
 * catch arm is reachable.
 *
 * @internal
 */
#[CoversClass(CustomFieldSupport::class)]
final class CustomFieldSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetGlobals();
    }

    protected function tearDown(): void
    {
        $this->resetGlobals();
    }

    #[Test]
    public function testGetFieldValuesReturnsHandlerDataAsAnArray(): void
    {
        $GLOBALS['__middag_test_cf_values'] = ['city' => 'NYC', 'country' => 'US'];

        self::assertSame(
            ['city' => 'NYC', 'country' => 'US'],
            CustomFieldSupport::getFieldValues('core_course', 'course', 10)
        );
    }

    #[Test]
    public function testGetFieldValuesReturnsEmptyOnFailure(): void
    {
        $GLOBALS['__middag_test_cf_throw'] = true;

        self::assertSame([], CustomFieldSupport::getFieldValues('core_course', 'course', 10));
    }

    #[Test]
    public function testGetFieldValueReturnsTheNamedValue(): void
    {
        $GLOBALS['__middag_test_cf_values'] = ['city' => 'NYC'];

        self::assertSame('NYC', CustomFieldSupport::getFieldValue('core_course', 'course', 10, 'city'));
    }

    #[Test]
    public function testGetFieldValueReturnsNullWhenAbsent(): void
    {
        $GLOBALS['__middag_test_cf_values'] = ['city' => 'NYC'];

        self::assertNull(CustomFieldSupport::getFieldValue('core_course', 'course', 10, 'zip'));
    }

    #[Test]
    public function testGetFieldValuesBulkMapsControllersByInstance(): void
    {
        $GLOBALS['__middag_test_cf_bulk'] = [100 => [$this->makeController('city', 'NYC')]];

        $result = CustomFieldSupport::getFieldValuesBulk('core_course', 'course', [100]);

        self::assertSame(['city' => 'NYC'], $result[100]);
    }

    #[Test]
    public function testGetFieldValuesBulkReturnsEmptyOnFailure(): void
    {
        $GLOBALS['__middag_test_cf_throw'] = true;

        self::assertSame([], CustomFieldSupport::getFieldValuesBulk('core_course', 'course', [100]));
    }

    #[Test]
    public function testGetFieldDefinitionsListsFields(): void
    {
        $GLOBALS['__middag_test_cf_fields'] = [
            $this->makeField(['shortname' => 'city', 'name' => 'City', 'type' => 'text', 'configdata' => '{}']),
        ];

        $definitions = CustomFieldSupport::getFieldDefinitions('core_course', 'course');

        self::assertSame(
            [['shortname' => 'city', 'name' => 'City', 'type' => 'text', 'configdata' => '{}']],
            $definitions
        );
    }

    #[Test]
    public function testGetFieldDefinitionsReturnsEmptyOnFailure(): void
    {
        $GLOBALS['__middag_test_cf_throw'] = true;

        self::assertSame([], CustomFieldSupport::getFieldDefinitions('core_course', 'course'));
    }

    #[Test]
    public function testSaveFieldDataSetsAndSavesOnlyMatchingControllers(): void
    {
        $city = $this->makeController('city');
        $country = $this->makeController('country');
        $GLOBALS['__middag_test_cf_instance_data'] = [$city, $country];

        $result = CustomFieldSupport::saveFieldData('core_course', 'course', 10, ['city' => 'NYC']);

        self::assertTrue($result);
        self::assertSame([['charvalue', 'NYC'], ['value', 'NYC']], $city->setCalls);
        self::assertSame(1, $city->saveCount);
        self::assertSame([], $country->setCalls);
        self::assertSame(0, $country->saveCount);
    }

    #[Test]
    public function testSaveFieldDataReturnsFalseOnFailure(): void
    {
        $GLOBALS['__middag_test_cf_throw'] = true;

        self::assertFalse(CustomFieldSupport::saveFieldData('core_course', 'course', 10, ['city' => 'NYC']));
    }

    #[Test]
    public function testDeleteInstanceDataReturnsTrueAndDeletes(): void
    {
        self::assertTrue(CustomFieldSupport::deleteInstanceData('core_course', 'course', 10));
        self::assertSame([10], $GLOBALS['__middag_test_cf_deleted']);
    }

    #[Test]
    public function testDeleteInstanceDataReturnsFalseOnFailure(): void
    {
        $GLOBALS['__middag_test_cf_throw'] = true;

        self::assertFalse(CustomFieldSupport::deleteInstanceData('core_course', 'course', 10));
    }

    private function resetGlobals(): void
    {
        unset(
            $GLOBALS['__middag_test_cf_values'],
            $GLOBALS['__middag_test_cf_bulk'],
            $GLOBALS['__middag_test_cf_fields'],
            $GLOBALS['__middag_test_cf_instance_data'],
            $GLOBALS['__middag_test_cf_deleted'],
            $GLOBALS['__middag_test_cf_throw'],
            $GLOBALS['__middag_test_cf_throw_get_handler'],
        );
    }

    private function makeController(string $shortname, mixed $exportValue = null, string $datafield = 'charvalue'): object
    {
        return new class($shortname, $exportValue, $datafield) {
            /** @var array<int, array<int, mixed>> */
            public array $setCalls = [];

            public int $saveCount = 0;

            public function __construct(
                private readonly string $shortname,
                private readonly mixed $exportValue,
                private readonly string $datafieldName,
            ) {}

            public function get_field(): object
            {
                $shortname = $this->shortname;

                return new class($shortname) {
                    public function __construct(private readonly string $shortname) {}

                    public function get(string $key): mixed
                    {
                        return $key === 'shortname' ? $this->shortname : ('[' . $key . ']');
                    }
                };
            }

            public function export_value(): mixed
            {
                return $this->exportValue;
            }

            public function datafield(): string
            {
                return $this->datafieldName;
            }

            public function set(string $key, mixed $value): void
            {
                $this->setCalls[] = [$key, $value];
            }

            public function save(): void
            {
                ++$this->saveCount;
            }
        };
    }

    /**
     * @param array<string, string> $attrs
     */
    private function makeField(array $attrs): object
    {
        return new class($attrs) {
            /**
             * @param array<string, string> $attrs
             */
            public function __construct(private array $attrs) {}

            public function get(string $key): mixed
            {
                return $this->attrs[$key] ?? null;
            }
        };
    }
}
