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

use InvalidArgumentException;
use Middag\Moodle\Support\SettingsSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

enum core_config: string
{
    case DebugMode = 'debugmode';
}

enum ecommerce_config: string
{
    case SendFromWoo = 'sendfromwoo';
}

enum EcommerceConfig: string
{
    case SendFromWoo = 'sendfromwoo';
}

enum FrameworkConfig: string
{
    case DebugMode = 'debugmode';
}

enum sample_status: string
{
    case Active = 'active';
}

/**
 * Consumer-style subclass exercising the {@see SettingsSupport::extensionAliases()}
 * seam: remaps the legacy "framework" slug onto the canonical "core" extension.
 */
final class AliasedSettingsSupport extends SettingsSupport
{
    protected static function extensionAliases(): array
    {
        return ['framework' => 'core'];
    }
}

/**
 * @internal
 */
#[CoversClass(SettingsSupport::class)]
final class SettingsSupportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__middag_test_config'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_config']);
    }

    #[Test]
    public function testGetResolvesSnakeCaseEnumToCanonicalKey(): void
    {
        $GLOBALS['__middag_test_config']['mdg_core_debugmode'] = '2';

        self::assertSame('2', SettingsSupport::get(core_config::DebugMode));
    }

    #[Test]
    public function testGetResolvesCrossExtensionSlug(): void
    {
        $GLOBALS['__middag_test_config']['mdg_ecommerce_sendfromwoo'] = '1';

        self::assertSame('1', SettingsSupport::get(ecommerce_config::SendFromWoo));
    }

    #[Test]
    public function testGetNormalisesPascalCaseEnumToSnakeCaseSlug(): void
    {
        $GLOBALS['__middag_test_config']['mdg_ecommerce_sendfromwoo'] = '1';

        self::assertSame('1', SettingsSupport::get(EcommerceConfig::SendFromWoo));
    }

    #[Test]
    public function testPascalCaseFrameworkEnumResolvesItsOwnSlug(): void
    {
        // Regression for the P0-7 footgun: "FrameworkConfig" used to derive the
        // dead key mdg_FrameworkConfig_debugmode and silently read false. The
        // base resolver is value-free: "framework" maps onto its own slug —
        // remapping onto another extension is a subclass concern (see the
        // extensionAliases() tests below).
        $GLOBALS['__middag_test_config']['mdg_framework_debugmode'] = '2';

        self::assertSame('2', SettingsSupport::get(FrameworkConfig::DebugMode));
    }

    #[Test]
    public function testExtensionAliasesSeamRemapsTheDerivedSlug(): void
    {
        $GLOBALS['__middag_test_config']['mdg_core_debugmode'] = '2';

        self::assertSame('2', AliasedSettingsSupport::get(FrameworkConfig::DebugMode));
    }

    #[Test]
    public function testExtensionAliasesSeamLeavesUnaliasedSlugsUntouched(): void
    {
        $GLOBALS['__middag_test_config']['mdg_ecommerce_sendfromwoo'] = '1';

        self::assertSame('1', AliasedSettingsSupport::get(ecommerce_config::SendFromWoo));
    }

    #[Test]
    public function testExtensionAliasesSeamAppliesToWrites(): void
    {
        self::assertTrue(AliasedSettingsSupport::set(FrameworkConfig::DebugMode, '1'));
        self::assertSame('1', $GLOBALS['__middag_test_config']['mdg_core_debugmode']);
        self::assertArrayNotHasKey('mdg_framework_debugmode', $GLOBALS['__middag_test_config']);

        self::assertTrue(AliasedSettingsSupport::unset(FrameworkConfig::DebugMode));
        self::assertArrayNotHasKey('mdg_core_debugmode', $GLOBALS['__middag_test_config']);
    }

    #[Test]
    public function testSetWritesTheCanonicalKey(): void
    {
        self::assertTrue(SettingsSupport::set(core_config::DebugMode, '1'));
        self::assertSame('1', $GLOBALS['__middag_test_config']['mdg_core_debugmode']);
    }

    #[Test]
    public function testUnsetRemovesTheCanonicalKey(): void
    {
        $GLOBALS['__middag_test_config']['mdg_core_debugmode'] = '1';

        self::assertTrue(SettingsSupport::unset(core_config::DebugMode));
        self::assertArrayNotHasKey('mdg_core_debugmode', $GLOBALS['__middag_test_config']);
    }

    #[Test]
    public function testGetRejectsEnumOutsideTheNamingConvention(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not follow the {slug}_config naming convention');

        SettingsSupport::get(sample_status::Active);
    }

    #[Test]
    public function testSetRejectsEnumOutsideTheNamingConvention(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SettingsSupport::set(sample_status::Active, '1');
    }

    #[Test]
    public function testUnsetRejectsEnumOutsideTheNamingConvention(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SettingsSupport::unset(sample_status::Active);
    }
}
