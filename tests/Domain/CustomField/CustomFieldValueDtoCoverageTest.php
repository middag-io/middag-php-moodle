<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\CustomField;

use Middag\Moodle\Domain\CustomField\CustomFieldValueDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CustomFieldValueDto::class)]
final class CustomFieldValueDtoCoverageTest extends TestCase
{
    #[Test]
    public function testConstructsWithAllFieldsAndDefaults(): void
    {
        $dto = new CustomFieldValueDto(
            shortname: 'birthdate',
            value: '2001-01-01',
            type: 'date',
            name: 'Birth date',
            required: true,
            category: 'Personal',
        );

        self::assertSame('birthdate', $dto->shortname);
        self::assertSame('2001-01-01', $dto->value);
        self::assertSame('date', $dto->type);
        self::assertSame('Birth date', $dto->name);
        self::assertTrue($dto->required);
        self::assertSame('Personal', $dto->category);
    }

    #[Test]
    public function testDefaultsForRequiredAndCategory(): void
    {
        $dto = new CustomFieldValueDto(
            shortname: 'nick',
            value: 'x',
            type: 'text',
            name: 'Nickname',
        );

        self::assertFalse($dto->required);
        self::assertNull($dto->category);
    }

    #[Test]
    public function testHasValueIsTrueWhenValuePresent(): void
    {
        $dto = new CustomFieldValueDto('f', 'something', 'text', 'F');

        self::assertTrue($dto->has_value());
    }

    #[Test]
    public function testHasValueIsFalseWhenValueIsNull(): void
    {
        $dto = new CustomFieldValueDto('f', null, 'text', 'F');

        self::assertFalse($dto->has_value());
    }

    #[Test]
    public function testHasValueIsFalseWhenValueIsEmptyString(): void
    {
        $dto = new CustomFieldValueDto('f', '', 'text', 'F');

        self::assertFalse($dto->has_value());
    }

    #[Test]
    public function testIntValueCastsNumericString(): void
    {
        $dto = new CustomFieldValueDto('f', '42', 'text', 'F');

        self::assertSame(42, $dto->int_value());
    }

    #[Test]
    public function testIntValueFallsBackToZeroWhenNull(): void
    {
        $dto = new CustomFieldValueDto('f', null, 'text', 'F');

        self::assertSame(0, $dto->int_value());
    }

    #[Test]
    public function testBoolValueIsTrueForTruthyString(): void
    {
        $dto = new CustomFieldValueDto('f', '1', 'checkbox', 'F');

        self::assertTrue($dto->bool_value());
    }

    #[Test]
    public function testBoolValueIsFalseWhenNull(): void
    {
        $dto = new CustomFieldValueDto('f', null, 'checkbox', 'F');

        self::assertFalse($dto->bool_value());
    }

    #[Test]
    public function testToArrayReturnsAllFields(): void
    {
        $dto = new CustomFieldValueDto(
            shortname: 'grade',
            value: '9.5',
            type: 'select',
            name: 'Grade',
            required: true,
            category: 'Academic',
        );

        self::assertSame([
            'shortname' => 'grade',
            'value' => '9.5',
            'type' => 'select',
            'name' => 'Grade',
            'required' => true,
            'category' => 'Academic',
        ], $dto->toArray());
    }
}
