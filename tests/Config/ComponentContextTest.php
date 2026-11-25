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

        $this->expectException(MoodleConfigurationException::class);

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

    public function testConfiguringTheSameComponentAgainIsANoOp(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_acme');

        ComponentContext::configure('local_acme');
        ComponentContext::configure('local_acme', 'local_acme_autoload');
        ComponentContext::configure('local_acme', '');
        ComponentContext::configure('local_acme', 'LOCAL_ACME_AUTOLOAD');

        self::assertSame('local_acme', ComponentContext::name());
        self::assertSame('local_acme_autoload', ComponentContext::autoloadFunction());
    }

    public function testConfiguringAnotherComponentThrowsAndKeepsTheFirst(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_acme');

        try {
            ComponentContext::configure('local_other');
            self::fail('A second component must not configure the process.');
        } catch (MoodleConfigurationException $moodleConfigurationException) {
            self::assertStringContainsString('another MIDDAG product (local_acme)', $moodleConfigurationException->getMessage());
            self::assertStringContainsString('one MIDDAG product per site', $moodleConfigurationException->getMessage());
        }

        self::assertSame('local_acme', ComponentContext::name());
        self::assertSame('local_acme_autoload', ComponentContext::autoloadFunction());
    }

    public function testConfiguringTheSameComponentWithAnotherAutoloadFunctionThrows(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_acme');

        try {
            ComponentContext::configure('local_acme', 'other_autoload');
            self::fail('The same component with another autoload function must not reconfigure the process.');
        } catch (MoodleConfigurationException $moodleConfigurationException) {
            self::assertStringContainsString('local_acme_autoload', $moodleConfigurationException->getMessage());
        }

        self::assertSame('local_acme_autoload', ComponentContext::autoloadFunction());
    }

    public function testEmptyComponentIsRejectedWithoutTouchingTheConfiguredOne(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_acme');

        try {
            ComponentContext::configure('');
            self::fail('An empty component must be rejected.');
        } catch (MoodleConfigurationException) {
        }

        self::assertSame('local_acme', ComponentContext::name());
    }

    public function testResetAllowsAnotherComponent(): void
    {
        ComponentContext::reset();
        ComponentContext::configure('local_acme');

        ComponentContext::reset();
        ComponentContext::configure('local_other');

        self::assertSame('local_other', ComponentContext::name());
        self::assertSame('local_other_autoload', ComponentContext::autoloadFunction());
    }

    public function testCapabilityComponentThrowsWhenUnconfigured(): void
    {
        ComponentContext::reset();

        $this->expectException(MoodleConfigurationException::class);

        ComponentContext::capabilityComponent();
    }

    public function testBaseUrlPathThrowsWhenUnconfigured(): void
    {
        ComponentContext::reset();

        $this->expectException(MoodleConfigurationException::class);

        ComponentContext::baseUrlPath();
    }
}
