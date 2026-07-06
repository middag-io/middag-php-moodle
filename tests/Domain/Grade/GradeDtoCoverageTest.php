<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Grade;

use Middag\Moodle\Domain\Grade\GradeDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(GradeDto::class)]
final class GradeDtoCoverageTest extends TestCase
{
    #[Test]
    public function defaultsAreApplied(): void
    {
        $dto = new GradeDto();

        $this->assertSame(0, $dto->userid);
        $this->assertSame(0, $dto->courseid);
        $this->assertSame(0, $dto->itemid);
        $this->assertNull($dto->finalgrade);
        $this->assertNull($dto->rawgrade);
        $this->assertSame('', $dto->displayValue);
        $this->assertNull($dto->passed);
        $this->assertNull($dto->feedback);
        $this->assertNull($dto->timemodified);
    }

    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $dto = new GradeDto(
            userid: 7,
            courseid: 3,
            itemid: 42,
            finalgrade: 85.5,
            rawgrade: 90.0,
            displayValue: '85.5 %',
            passed: true,
            feedback: 'Well done',
            timemodified: 1700000000,
        );

        $this->assertSame(7, $dto->userid);
        $this->assertSame(3, $dto->courseid);
        $this->assertSame(42, $dto->itemid);
        $this->assertSame(85.5, $dto->finalgrade);
        $this->assertSame(90.0, $dto->rawgrade);
        $this->assertSame('85.5 %', $dto->displayValue);
        $this->assertTrue($dto->passed);
        $this->assertSame('Well done', $dto->feedback);
        $this->assertSame(1700000000, $dto->timemodified);
    }

    #[Test]
    public function toArrayMapsDisplayValueToSnakeCaseKey(): void
    {
        $dto = new GradeDto(
            userid: 7,
            courseid: 3,
            itemid: 42,
            finalgrade: 85.5,
            rawgrade: 90.0,
            displayValue: '85.5 %',
            passed: false,
            feedback: 'Try again',
            timemodified: 1700000000,
        );

        $expected = [
            'userid' => 7,
            'courseid' => 3,
            'itemid' => 42,
            'finalgrade' => 85.5,
            'rawgrade' => 90.0,
            'display_value' => '85.5 %',
            'passed' => false,
            'feedback' => 'Try again',
            'timemodified' => 1700000000,
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    #[Test]
    public function toArrayPreservesNullableDefaults(): void
    {
        $dto = new GradeDto();

        $array = $dto->toArray();

        $this->assertArrayHasKey('display_value', $array);
        $this->assertSame('', $array['display_value']);
        $this->assertNull($array['finalgrade']);
        $this->assertNull($array['rawgrade']);
        $this->assertNull($array['passed']);
        $this->assertNull($array['feedback']);
        $this->assertNull($array['timemodified']);
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(GradeDto::class);
        $this->assertTrue($reflection->isFinal());
    }
}
