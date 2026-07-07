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
    case debugmode = 'debugmode';
}

enum ecommerce_config: string
{
    case sendfromwoo = 'sendfromwoo';
}

enum EcommerceConfig: string
{
    case sendfromwoo = 'sendfromwoo';
}

enum FrameworkConfig: string
{
    case debugmode = 'debugmode';
}

enum sample_status: string
{
    case active = 'active';
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

        self::assertSame('2', SettingsSupport::get(core_config::debugmode));
    }

    #[Test]
    public function testGetResolvesCrossExtensionSlug(): void
    {
        $GLOBALS['__middag_test_config']['mdg_ecommerce_sendfromwoo'] = '1';

        self::assertSame('1', SettingsSupport::get(ecommerce_config::sendfromwoo));
    }

    #[Test]
    public function testGetNormalisesPascalCaseEnumToSnakeCaseSlug(): void
    {
        $GLOBALS['__middag_test_config']['mdg_ecommerce_sendfromwoo'] = '1';

        self::assertSame('1', SettingsSupport::get(EcommerceConfig::sendfromwoo));
    }

    #[Test]
    public function testPascalCaseFrameworkEnumHitsTheCoreSpecialCase(): void
    {
        // Regression for the P0-7 footgun: "FrameworkConfig" used to derive the
        // dead key mdg_FrameworkConfig_debugmode and silently read false.
        $GLOBALS['__middag_test_config']['mdg_core_debugmode'] = '2';

        self::assertSame('2', SettingsSupport::get(FrameworkConfig::debugmode));
    }

    #[Test]
    public function testSetWritesTheCanonicalKey(): void
    {
        self::assertTrue(SettingsSupport::set(core_config::debugmode, '1'));
        self::assertSame('1', $GLOBALS['__middag_test_config']['mdg_core_debugmode']);
    }

    #[Test]
    public function testUnsetRemovesTheCanonicalKey(): void
    {
        $GLOBALS['__middag_test_config']['mdg_core_debugmode'] = '1';

        self::assertTrue(SettingsSupport::unset(core_config::debugmode));
        self::assertArrayNotHasKey('mdg_core_debugmode', $GLOBALS['__middag_test_config']);
    }

    #[Test]
    public function testGetRejectsEnumOutsideTheNamingConvention(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not follow the {slug}_config naming convention');

        SettingsSupport::get(sample_status::active);
    }

    #[Test]
    public function testSetRejectsEnumOutsideTheNamingConvention(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SettingsSupport::set(sample_status::active, '1');
    }

    #[Test]
    public function testUnsetRejectsEnumOutsideTheNamingConvention(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SettingsSupport::unset(sample_status::active);
    }
}
