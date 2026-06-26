<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel\Config;

use InvalidArgumentException;
use LogicException;
use Middag\Moodle\Kernel\Config\ComponentContext;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Middag\Moodle\Kernel\Config\ComponentContext
 */
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

        $this->expectException(InvalidArgumentException::class);

        ComponentContext::configure('');
    }

    public function testIsConfiguredIsFalseAfterReset(): void
    {
        ComponentContext::reset();

        self::assertFalse(ComponentContext::isConfigured());
    }
}
