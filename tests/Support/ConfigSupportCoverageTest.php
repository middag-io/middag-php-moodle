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

use ArrayAccess;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Shared\Enum\TextFormat;
use Middag\Moodle\Support\ConfigSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
#[CoversClass(ConfigSupport::class)]
final class ConfigSupportCoverageTest extends TestCase
{
    private mixed $prevConfig;

    private mixed $prevCfg;

    private mixed $prevSite;

    protected function setUp(): void
    {
        $this->prevConfig = $GLOBALS['__middag_test_config'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevSite = $GLOBALS['SITE'] ?? null;

        $GLOBALS['__middag_test_config'] = [];
        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    protected function tearDown(): void
    {
        $GLOBALS['__middag_test_config'] = $this->prevConfig;
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['SITE'] = $this->prevSite;

        // Restore the composition-root seam that some tests deliberately reset.
        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    #[Test]
    public function testPluginNameReturnsConfiguredComponent(): void
    {
        self::assertSame('local_example', ConfigSupport::pluginName());
    }

    #[Test]
    public function testGetWithoutNameReturnsAllConfigAsObject(): void
    {
        $GLOBALS['__middag_test_config'] = ['alpha' => '1', 'beta' => '2'];

        $result = ConfigSupport::get();

        self::assertInstanceOf(stdClass::class, $result);
        self::assertSame('1', $result->alpha);
    }

    #[Test]
    public function testGetWithNameReturnsTheValue(): void
    {
        $GLOBALS['__middag_test_config']['foo'] = 'bar';

        self::assertSame('bar', ConfigSupport::get('foo'));
    }

    #[Test]
    public function testGetReturnsFalseWhenComponentResolutionThrows(): void
    {
        ComponentContext::reset();

        self::assertFalse(ConfigSupport::get('foo'));
    }

    #[Test]
    public function testGetConfigWithNameReturnsTheValue(): void
    {
        $GLOBALS['__middag_test_config']['x'] = 'y';

        self::assertSame('y', ConfigSupport::getConfig('local_example', 'x'));
    }

    #[Test]
    public function testGetConfigWithoutNameReturnsObject(): void
    {
        self::assertInstanceOf(stdClass::class, ConfigSupport::getConfig('local_example'));
    }

    #[Test]
    public function testGetConfigReturnsFalseWhenReadThrows(): void
    {
        $GLOBALS['__middag_test_config'] = new class implements ArrayAccess {
            public function offsetExists(mixed $offset): bool
            {
                return true;
            }

            public function offsetGet(mixed $offset): mixed
            {
                throw new RuntimeException('config read failed');
            }

            public function offsetSet(mixed $offset, mixed $value): void {}

            public function offsetUnset(mixed $offset): void {}
        };

        self::assertFalse(ConfigSupport::getConfig('local_example', 'anykey'));
    }

    #[Test]
    public function testSetConfigStoresTheValue(): void
    {
        self::assertTrue(ConfigSupport::setConfig('greeting', 'hello'));
        self::assertSame('hello', $GLOBALS['__middag_test_config']['greeting']);
    }

    #[Test]
    public function testSetConfigReturnsFalseWhenComponentResolutionThrows(): void
    {
        ComponentContext::reset();

        self::assertFalse(ConfigSupport::setConfig('greeting', 'hello'));
    }

    #[Test]
    public function testUnsetConfigRemovesTheValue(): void
    {
        $GLOBALS['__middag_test_config']['greeting'] = 'hello';

        self::assertTrue(ConfigSupport::unsetConfig('greeting'));
        self::assertArrayNotHasKey('greeting', $GLOBALS['__middag_test_config']);
    }

    #[Test]
    public function testUnsetConfigReturnsFalseWhenComponentResolutionThrows(): void
    {
        ComponentContext::reset();

        self::assertFalse(ConfigSupport::unsetConfig('greeting'));
    }

    #[Test]
    public function testGetGlobalReturnsTheCfgProperty(): void
    {
        $GLOBALS['CFG'] = (object) ['wwwroot' => 'https://moodle.test'];

        self::assertSame('https://moodle.test', ConfigSupport::getGlobal('wwwroot'));
    }

    #[Test]
    public function testGetGlobalReturnsNullForMissingProperty(): void
    {
        $GLOBALS['CFG'] = new stdClass();

        self::assertNull(ConfigSupport::getGlobal('doesnotexist'));
    }

    #[Test]
    public function testGetSiteInfoMapsTheSiteGlobalToADto(): void
    {
        $GLOBALS['SITE'] = (object) [
            'id' => 1,
            'fullname' => 'Full Name',
            'shortname' => 'Short',
            'summary' => 'A summary',
            'summaryformat' => 1,
            'format' => 'site',
            'lang' => 'en',
            'theme' => 'boost',
            'timecreated' => 100,
            'timemodified' => 200,
        ];

        $dto = ConfigSupport::getSiteInfo();

        self::assertSame(1, $dto->id);
        self::assertSame('Full Name', $dto->fullname);
        self::assertSame('Short', $dto->shortname);
        self::assertSame(TextFormat::Html, $dto->summaryformat);
        self::assertSame(200, $dto->timemodified);
    }

    #[Test]
    public function testGetSiteInfoUsesDefaultsForMissingFields(): void
    {
        $GLOBALS['SITE'] = (object) ['id' => 1];

        $dto = ConfigSupport::getSiteInfo();

        self::assertSame('', $dto->fullname);
        self::assertSame(0, $dto->timecreated);
        self::assertSame(TextFormat::Html, $dto->summaryformat);
    }

    #[Test]
    public function testGetSiteIdReturnsTheSiteidConstant(): void
    {
        self::assertSame(1, ConfigSupport::getSiteId());
    }
}
