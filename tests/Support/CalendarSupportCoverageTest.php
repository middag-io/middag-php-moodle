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

use Middag\Moodle\Domain\Calendar\CalendarEventDto;
use Middag\Moodle\Support\CalendarSupport;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(CalendarSupport::class)]
final class CalendarSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    private mixed $prevDb;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevDb = $GLOBALS['DB'] ?? null;

        // CalendarSupport require_once's $CFG->dirroot/calendar/lib.php at file scope.
        $GLOBALS['CFG'] = (object) [
            'dirroot' => $GLOBALS['__middag_test_moodleroot'],
            'wwwroot' => 'https://moodle.test',
        ];

        foreach ([
            '__middag_test_throw_calendar_create',
            '__middag_test_throw_calendar_load',
            '__middag_test_throw_calendar_update',
            '__middag_test_throw_calendar_delete',
            '__middag_test_calendar_new_id',
            '__middag_test_calendar_props',
            '__middag_test_calendar_deleted',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['DB'] = $this->prevDb;
    }

    #[Test]
    public function testCreateReturnsDtoWithGeneratedId(): void
    {
        $GLOBALS['__middag_test_calendar_new_id'] = 777;

        $dto = new CalendarEventDto(name: 'Kickoff', description: 'Desc', timestart: 100);

        $result = CalendarSupport::create($dto);

        self::assertInstanceOf(CalendarEventDto::class, $result);
        self::assertSame(777, $result->id);
        self::assertSame('Kickoff', $result->name);
        self::assertSame(100, $result->timestart);
    }

    #[Test]
    public function testCreateReturnsNullWhenCreationThrows(): void
    {
        $GLOBALS['__middag_test_throw_calendar_create'] = true;

        self::assertNull(CalendarSupport::create(new CalendarEventDto(name: 'X')));
    }

    #[Test]
    public function testUpdateReturnsFalseWhenIdIsNull(): void
    {
        self::assertFalse(CalendarSupport::update(new CalendarEventDto(name: 'X')));
    }

    #[Test]
    public function testUpdateReturnsTrueOnSuccess(): void
    {
        self::assertTrue(CalendarSupport::update(new CalendarEventDto(id: 12, name: 'Renamed')));
    }

    #[Test]
    public function testUpdateReturnsFalseWhenLoadThrows(): void
    {
        $GLOBALS['__middag_test_throw_calendar_load'] = true;

        self::assertFalse(CalendarSupport::update(new CalendarEventDto(id: 12, name: 'X')));
    }

    #[Test]
    public function testDeleteReturnsTrueAndForwardsRepeatsFlag(): void
    {
        self::assertTrue(CalendarSupport::delete(9, true));
        self::assertSame([9, true], $GLOBALS['__middag_test_calendar_deleted']);
    }

    #[Test]
    public function testDeleteReturnsFalseWhenLoadThrows(): void
    {
        $GLOBALS['__middag_test_throw_calendar_load'] = true;

        self::assertFalse(CalendarSupport::delete(9));
    }

    #[Test]
    public function testGetMapsLoadedRecordToDto(): void
    {
        $GLOBALS['__middag_test_calendar_props'] = [
            'id' => 3,
            'name' => 'Loaded',
            'description' => 'Body',
            'format' => 1,
            'eventtype' => 'course',
            'timestart' => 500,
            'timeduration' => 60,
            'courseid' => 10,
            'groupid' => 4,
            'userid' => 7,
            'visible' => 1,
            'categoryid' => 2,
            'repeats' => 0,
        ];

        $dto = CalendarSupport::get(3);

        self::assertInstanceOf(CalendarEventDto::class, $dto);
        self::assertSame(3, $dto->id);
        self::assertSame('course', $dto->eventtype);
        self::assertSame(10, $dto->courseid);
        self::assertSame(4, $dto->groupid);
        self::assertSame(7, $dto->userid);
        self::assertSame(2, $dto->categoryid);
    }

    #[Test]
    public function testGetReturnsNullWhenLoadThrows(): void
    {
        $GLOBALS['__middag_test_throw_calendar_load'] = true;

        self::assertNull(CalendarSupport::get(3));
    }

    #[Test]
    public function testGetByCourseMapsRecordsAndAppliesTimeFilters(): void
    {
        $GLOBALS['DB'] = new class extends moodle_database {
            /** @var array<int, mixed> */
            public array $captured = [];

            public function get_records_select($table, $select, ?array $params = null, $sort = '', $fields = '*', $limitfrom = 0, $limitnum = 0)
            {
                $this->captured = [$table, $select, $params];

                return [
                    'a' => (object) [
                        'id' => 1,
                        'name' => 'Full',
                        'courseid' => 10,
                        'groupid' => 5,
                        'userid' => 6,
                        'categoryid' => 8,
                        'visible' => 1,
                    ],
                    'b' => (object) [
                        'name' => 'Minimal',
                        'courseid' => 0,
                        'groupid' => 0,
                        'userid' => 0,
                        'categoryid' => 0,
                    ],
                ];
            }
        };

        $result = CalendarSupport::getByCourse(10, 100, 200);

        self::assertCount(2, $result);
        self::assertSame(1, $result[0]->id);
        self::assertSame(10, $result[0]->courseid);
        // Minimal record: id absent → null; zero ids → null.
        self::assertNull($result[1]->id);
        self::assertNull($result[1]->courseid);
        self::assertNull($result[1]->groupid);
        self::assertSame('event', $GLOBALS['DB']->captured[0]);
        self::assertStringContainsString('timestart >= :timestart', $GLOBALS['DB']->captured[1]);
        self::assertStringContainsString('timestart <= :timeend', $GLOBALS['DB']->captured[1]);
    }

    #[Test]
    public function testGetByCourseWithoutTimeFiltersUsesOnlyCourseCondition(): void
    {
        $GLOBALS['DB'] = new class extends moodle_database {
            public string $select = '';

            public function get_records_select($table, $select, ?array $params = null, $sort = '', $fields = '*', $limitfrom = 0, $limitnum = 0)
            {
                $this->select = $select;

                return [];
            }
        };

        self::assertSame([], CalendarSupport::getByCourse(10));
        self::assertSame('courseid = :courseid', $GLOBALS['DB']->select);
    }

    #[Test]
    public function testGetByCourseReturnsEmptyArrayWhenQueryThrows(): void
    {
        $GLOBALS['DB'] = new class extends moodle_database {
            public function get_records_select($table, $select, ?array $params = null, $sort = '', $fields = '*', $limitfrom = 0, $limitnum = 0): void
            {
                throw new RuntimeException('query failed');
            }
        };

        self::assertSame([], CalendarSupport::getByCourse(10));
    }
}
