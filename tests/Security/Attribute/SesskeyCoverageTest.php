<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Security\Attribute;

use Attribute;
use Error;
use Middag\Moodle\Security\Attribute\Sesskey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Sesskey is a marker attribute composing the Moodle CSRF requirement onto a
 * controller method or class. It carries a single promoted `bool $require`
 * (default true). The class needs no Moodle runtime; every branch is exercised
 * by direct construction and by reading the attribute back off annotated
 * fixtures via reflection.
 *
 * @internal
 */
#[CoversClass(Sesskey::class)]
final class SesskeyCoverageTest extends TestCase
{
    #[Test]
    public function testRequireDefaultsToTrue(): void
    {
        $attribute = new Sesskey();

        self::assertTrue($attribute->require);
    }

    #[Test]
    public function testRequireCanBeEnabledExplicitly(): void
    {
        $attribute = new Sesskey(require: true);

        self::assertTrue($attribute->require);
    }

    #[Test]
    public function testRequireCanBeDisabledExplicitly(): void
    {
        $attribute = new Sesskey(require: false);

        self::assertFalse($attribute->require);
    }

    #[Test]
    public function testRequirePropertyIsReadonly(): void
    {
        $attribute = new Sesskey();

        $this->expectException(Error::class);

        // @phpstan-ignore-next-line property.readOnlyAssignNotInConstructor
        $attribute->require = false;
    }

    #[Test]
    public function testAttributeTargetsBothMethodsAndClasses(): void
    {
        $reflection = new ReflectionClass(Sesskey::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        self::assertCount(1, $attributes);

        $attributeMeta = $attributes[0]->newInstance();

        self::assertSame(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS, $attributeMeta->flags);
    }

    #[Test]
    public function testInstanceCanBeReadBackFromAnAnnotatedMethodWithDefaults(): void
    {
        $method = new ReflectionMethod(SesskeyAnnotatedFixture::class, 'requiresSesskeyByDefault');
        $attributes = $method->getAttributes(Sesskey::class);

        self::assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();

        self::assertInstanceOf(Sesskey::class, $instance);
        self::assertTrue($instance->require);
    }

    #[Test]
    public function testInstanceCanBeReadBackFromAnAnnotatedClassWithRequireDisabled(): void
    {
        $reflection = new ReflectionClass(SesskeyOptOutFixture::class);
        $attributes = $reflection->getAttributes(Sesskey::class);

        self::assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();

        self::assertInstanceOf(Sesskey::class, $instance);
        self::assertFalse($instance->require);
    }
}

/**
 * Method-target fixture: the attribute with its default (`require: true`).
 *
 * @internal
 */
final class SesskeyAnnotatedFixture
{
    #[Sesskey]
    public function requiresSesskeyByDefault(): void {}
}

/**
 * Class-target fixture: the attribute opting out (`require: false`).
 *
 * @internal
 */
#[Sesskey(require: false)]
final class SesskeyOptOutFixture {}
