<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Config;

use LogicException;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Exception\MoodleConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ComponentContext::class)]
final class ComponentContextTest extends TestCase
{
    /**
     * Restore the bootstrap-configured default so global static state stays
     * deterministic for the rest of the suite regardless of test order.
     */
    protected function tearDown(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_example', 'local_example_autoload');

        parent::tearDown();
    }

    public function testNameThrowsWhenUnconfigured(): void
    {
        ComponentContext::reset();

        $this->expectException(LogicException::class);

        ComponentContext::name();
    }

    public function testConfigureSetsComponentAndDerivesAutoloadFunction(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('mod_acme');

        self::assertSame('mod_acme', ComponentContext::name());
        self::assertSame('mod_acme_autoload', ComponentContext::autoloadFunction());
        self::assertTrue(ComponentContext::isConfigured());
    }

    public function testConfigureAcceptsExplicitAutoloadFunction(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_foo', 'custom_autoload');

        self::assertSame('local_foo', ComponentContext::name());
        self::assertSame('custom_autoload', ComponentContext::autoloadFunction());
    }

    public function testEmptyComponentIsRejected(): void
    {
        ComponentContext::reset();

        $this->expectException(MoodleConfigurationException::class);

        ComponentContext::configure('');
    }

    public function testIsConfiguredIsFalseAfterReset(): void
    {
        ComponentContext::reset();

        self::assertFalse(ComponentContext::isConfigured());
    }

    public function testCapabilityComponentRewritesFirstUnderscoreForLocalPlugin(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_middag');

        self::assertSame('local/middag', ComponentContext::capabilityComponent());
    }

    public function testCapabilityComponentRewritesFirstUnderscoreForModPlugin(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('mod_unidade');

        self::assertSame('mod/unidade', ComponentContext::capabilityComponent());
    }

    public function testBaseUrlPathDerivesFromComponent(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_middag');

        self::assertSame('/local/middag', ComponentContext::baseUrlPath());

        ComponentContext::reset();
        ComponentContext::configure('mod_unidade');

        self::assertSame('/mod/unidade', ComponentContext::baseUrlPath());
    }

    public function testCapabilityComponentThrowsWhenUnconfigured(): void
    {
        ComponentContext::reset();

        $this->expectException(LogicException::class);

        ComponentContext::capabilityComponent();
    }

    public function testBaseUrlPathThrowsWhenUnconfigured(): void
    {
        ComponentContext::reset();

        $this->expectException(LogicException::class);

        ComponentContext::baseUrlPath();
    }
}
