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
 * Enumerates the supported admin setting types; storesValue() distinguishes
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
        $this->assertFalse($type->storesValue());
    }

    /**
     * @return array<string, array{0: SettingType}>
     */
    public static function displayOnlyProvider(): array
    {
        return [
            'heading' => [SettingType::Heading],
            'description' => [SettingType::Description],
            'link' => [SettingType::Link],
        ];
    }

    #[Test]
    #[DataProvider('storageProvider')]
    public function inputTypesStoreValues(SettingType $type): void
    {
        $this->assertTrue($type->storesValue());
    }

    /**
     * @return array<string, array{0: SettingType}>
     */
    public static function storageProvider(): array
    {
        return [
            'text' => [SettingType::Text],
            'checkbox' => [SettingType::Checkbox],
            'select' => [SettingType::Select],
            'storedfile' => [SettingType::StoredFile],
            'duration' => [SettingType::Duration],
        ];
    }

    #[Test]
    public function backingValueMatchesCaseName(): void
    {
        $this->assertSame('text', SettingType::Text->value);
        $this->assertSame('storedfile', SettingType::StoredFile->value);
    }
}
