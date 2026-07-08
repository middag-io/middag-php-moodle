<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Settings\Enum;

use Middag\Moodle\Settings\Enum\SettingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test SettingType.
 *
 * Enumerates the supported admin setting types; stores_value() distinguishes
 * input types from display-only ones (heading/description/link).
 *
 * @internal
 */
#[CoversClass(SettingType::class)]
final class SettingTypeCoverageTest extends TestCase
{
    #[Test]
    #[DataProvider('displayOnlyProvider')]
    public function displayOnlyTypesDoNotStoreValues(SettingType $type): void
    {
        $this->assertFalse($type->stores_value());
    }

    /**
     * @return array<string, array{0: SettingType}>
     */
    public static function displayOnlyProvider(): array
    {
        return [
            'heading' => [SettingType::heading],
            'description' => [SettingType::description],
            'link' => [SettingType::link],
        ];
    }

    #[Test]
    #[DataProvider('storageProvider')]
    public function inputTypesStoreValues(SettingType $type): void
    {
        $this->assertTrue($type->stores_value());
    }

    /**
     * @return array<string, array{0: SettingType}>
     */
    public static function storageProvider(): array
    {
        return [
            'text' => [SettingType::text],
            'checkbox' => [SettingType::checkbox],
            'select' => [SettingType::select],
            'storedfile' => [SettingType::storedfile],
            'duration' => [SettingType::duration],
        ];
    }

    #[Test]
    public function backingValueMatchesCaseName(): void
    {
        $this->assertSame('text', SettingType::text->value);
        $this->assertSame('storedfile', SettingType::storedfile->value);
    }
}
