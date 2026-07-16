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

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\ThemeSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(ThemeSupport::class)]
final class ThemeSupportCoverageTest extends TestCase
{
    /** The canonical config key ThemeSupport reads for the inheritance toggle. */
    private const INHERIT_KEY = 'mdg_core_inherit_theme_colors';

    private mixed $prevPage;

    private mixed $prevConfig;

    protected function setUp(): void
    {
        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $this->prevConfig = $GLOBALS['__middag_test_config'] ?? null;

        $GLOBALS['PAGE'] = new stdClass();
        $GLOBALS['__middag_test_config'] = [];
        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    protected function tearDown(): void
    {
        $GLOBALS['PAGE'] = $this->prevPage;
        $GLOBALS['__middag_test_config'] = $this->prevConfig;
    }

    #[Test]
    public function testGetBrandColorReturnsTheColorWhenSet(): void
    {
        $GLOBALS['PAGE'] = $this->pageWithBrand('#0f6cbf');

        self::assertSame('#0f6cbf', ThemeSupport::getBrandColor());
    }

    #[Test]
    public function testGetBrandColorReturnsNullWhenUnset(): void
    {
        self::assertNull(ThemeSupport::getBrandColor());
    }

    #[Test]
    public function testGetBrandColorReturnsNullForAnEmptyString(): void
    {
        $GLOBALS['PAGE'] = $this->pageWithBrand('');

        self::assertNull(ThemeSupport::getBrandColor());
    }

    #[Test]
    public function testGetBrandColorReturnsNullForANonStringValue(): void
    {
        $GLOBALS['PAGE'] = $this->pageWithBrand(123);

        self::assertNull(ThemeSupport::getBrandColor());
    }

    #[Test]
    public function testGetBrandColorRejectsANonColorToken(): void
    {
        // The value is interpolated verbatim into inline <style> content by
        // getCssInjection(); anything that is not a well-formed CSS color
        // token must be rejected, not injected.
        $GLOBALS['PAGE'] = $this->pageWithBrand('#fff; } body { display:none');

        self::assertNull(ThemeSupport::getBrandColor());
    }

    #[Test]
    public function testGetBrandColorAcceptsFunctionalAndNamedColorTokens(): void
    {
        $GLOBALS['PAGE'] = $this->pageWithBrand('rgba(15, 108, 191, 0.5)');
        self::assertSame('rgba(15, 108, 191, 0.5)', ThemeSupport::getBrandColor());

        $GLOBALS['PAGE'] = $this->pageWithBrand('rebeccapurple');
        self::assertSame('rebeccapurple', ThemeSupport::getBrandColor());

        $GLOBALS['PAGE'] = $this->pageWithBrand('#ABC');
        self::assertSame('#ABC', ThemeSupport::getBrandColor());
    }

    #[Test]
    public function testIsInheritanceEnabledReadsTheConfigFlag(): void
    {
        $GLOBALS['__middag_test_config'][self::INHERIT_KEY] = 1;

        self::assertTrue(ThemeSupport::isInheritanceEnabled());
    }

    #[Test]
    public function testIsInheritanceEnabledDefaultsToFalse(): void
    {
        self::assertFalse(ThemeSupport::isInheritanceEnabled());
    }

    #[Test]
    public function testGetCssInjectionReturnsNullWhenInheritanceIsDisabled(): void
    {
        self::assertNull(ThemeSupport::getCssInjection());
    }

    #[Test]
    public function testGetCssInjectionReturnsNullWhenNoBrandColorIsAvailable(): void
    {
        $GLOBALS['__middag_test_config'][self::INHERIT_KEY] = 1;
        $GLOBALS['PAGE'] = new stdClass();

        self::assertNull(ThemeSupport::getCssInjection());
    }

    #[Test]
    public function testGetCssInjectionReturnsTheRuleWhenEnabledAndColored(): void
    {
        $GLOBALS['__middag_test_config'][self::INHERIT_KEY] = 1;
        $GLOBALS['PAGE'] = $this->pageWithBrand('#123456');

        self::assertSame(':root { --middag-brand: #123456; }', ThemeSupport::getCssInjection());
    }

    #[Test]
    public function testBuildThemeReturnsColorAndInheritWhenEnabled(): void
    {
        $GLOBALS['__middag_test_config'][self::INHERIT_KEY] = 1;
        $GLOBALS['PAGE'] = $this->pageWithBrand('#abcdef');

        self::assertSame(['brandColor' => '#abcdef', 'inherit' => true], ThemeSupport::buildTheme());
    }

    #[Test]
    public function testBuildThemeReturnsNullColorWhenDisabled(): void
    {
        $GLOBALS['PAGE'] = $this->pageWithBrand('#abcdef');

        self::assertSame(['brandColor' => null, 'inherit' => false], ThemeSupport::buildTheme());
    }

    private function pageWithBrand(mixed $brandcolor): stdClass
    {
        return (object) [
            'theme' => (object) [
                'settings' => (object) ['brandcolor' => $brandcolor],
            ],
        ];
    }
}
