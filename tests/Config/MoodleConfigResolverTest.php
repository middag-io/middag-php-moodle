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

use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\Moodle\Config\MoodleConfigResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test MoodleConfigResolver.
 *
 * MoodleConfigResolver delegates to ConfigSupport::get() which calls
 * Moodle's get_config(). We stub get_config() at the global function
 * level to control return values.
 *
 * @internal
 */
#[CoversClass(MoodleConfigResolver::class)]
final class MoodleConfigResolverTest extends TestCase
{
    private MoodleConfigResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MoodleConfigResolver();
        // Reset stub return value
        $GLOBALS['__middag_test_config'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_config']);
    }

    #[Test]
    public function getReturnsDefaultWhenConfigReturnsFalse(): void
    {
        $GLOBALS['__middag_test_config']['nonexistent'] = false;

        $this->assertSame('default_val', $this->resolver->get('nonexistent', null, 'default_val'));
    }

    #[Test]
    public function getReturnsDefaultWhenConfigReturnsNull(): void
    {
        $GLOBALS['__middag_test_config']['missing'] = null;

        $this->assertSame('fallback', $this->resolver->get('missing', null, 'fallback'));
    }

    #[Test]
    public function getReturnsDefaultWhenConfigReturnsEmpty(): void
    {
        $GLOBALS['__middag_test_config']['empty'] = '';

        $this->assertSame('default', $this->resolver->get('empty', null, 'default'));
    }

    #[Test]
    public function getReturnsStringValue(): void
    {
        $GLOBALS['__middag_test_config']['stripe_key'] = 'sk_test_123';

        $this->assertSame('sk_test_123', $this->resolver->get('stripe_key'));
    }

    #[Test]
    public function getCastsIntToString(): void
    {
        $GLOBALS['__middag_test_config']['timeout'] = 42;

        $this->assertSame('42', $this->resolver->get('timeout'));
    }

    #[Test]
    public function getDefaultIsEmptyString(): void
    {
        $GLOBALS['__middag_test_config']['nope'] = false;

        $this->assertSame('', $this->resolver->get('nope'));
    }

    #[Test]
    public function hasReturnsTrueForExistingValue(): void
    {
        $GLOBALS['__middag_test_config']['exists'] = 'yes';

        $this->assertTrue($this->resolver->has('exists'));
    }

    #[Test]
    public function hasReturnsFalseForFalseValue(): void
    {
        $GLOBALS['__middag_test_config']['nope'] = false;

        $this->assertFalse($this->resolver->has('nope'));
    }

    #[Test]
    public function hasReturnsFalseForNullValue(): void
    {
        $GLOBALS['__middag_test_config']['nope'] = null;

        $this->assertFalse($this->resolver->has('nope'));
    }

    #[Test]
    public function entitySlugIsIgnored(): void
    {
        $GLOBALS['__middag_test_config']['key'] = 'value';

        $this->assertSame(
            $this->resolver->get('key'),
            $this->resolver->get('key', 'some_entity')
        );
    }

    #[Test]
    public function implementsConfigResolverInterface(): void
    {
        $this->assertInstanceOf(
            ConfigResolverInterface::class,
            $this->resolver
        );
    }

    #[Test]
    public function classIsFinal(): void
    {
        $ref = new ReflectionClass(MoodleConfigResolver::class);
        $this->assertTrue($ref->isFinal());
    }
}
