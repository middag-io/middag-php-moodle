<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Platform;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Moodle\Domain\Platform\Frankenstyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * @internal
 */
#[CoversClass(Frankenstyle::class)]
final class FrankenstyleTest extends TestCase
{
    #[Test]
    public function canBeConstructedDirectly(): void
    {
        $fs = new Frankenstyle(type: 'mod', name: 'assign');
        $this->assertSame('mod', $fs->type);
        $this->assertSame('assign', $fs->name);
    }

    #[Test]
    public function toStringReturnsFrankenstyleFormat(): void
    {
        $fs = new Frankenstyle(type: 'local', name: 'example');
        $this->assertSame('local_example', (string) $fs);
    }

    #[Test]
    public function implementsStringable(): void
    {
        $fs = new Frankenstyle(type: 'mod', name: 'forum');
        $this->assertInstanceOf(Stringable::class, $fs);
    }

    #[Test]
    public function fromStringParsesValidComponent(): void
    {
        $fs = Frankenstyle::fromString('mod_assign');
        $this->assertSame('mod', $fs->type);
        $this->assertSame('assign', $fs->name);
    }

    #[Test]
    public function fromStringParsesLocalComponent(): void
    {
        $fs = Frankenstyle::fromString('local_example');
        $this->assertSame('local', $fs->type);
        $this->assertSame('example', $fs->name);
    }

    #[Test]
    public function fromStringTrimsWhitespace(): void
    {
        $fs = Frankenstyle::fromString('  mod_assign  ');
        $this->assertSame('mod', $fs->type);
        $this->assertSame('assign', $fs->name);
    }

    #[Test]
    public function fromStringThrowsForEmptyString(): void
    {
        $this->expectException(MiddagValidationException::class);
        Frankenstyle::fromString('');
    }

    #[Test]
    public function fromStringThrowsForStringWithoutUnderscore(): void
    {
        $this->expectException(MiddagValidationException::class);
        Frankenstyle::fromString('modassign');
    }

    #[Test]
    public function fromStringThrowsForInvalidNameWithUppercase(): void
    {
        $this->expectException(MiddagValidationException::class);
        Frankenstyle::fromString('mod_Assign');
    }

    #[Test]
    public function fromStringThrowsForInvalidNameWithSpecialChars(): void
    {
        $this->expectException(MiddagValidationException::class);
        Frankenstyle::fromString('mod_assign-2');
    }

    #[Test]
    public function fromStringThrowsForInvalidTypeWithNumbers(): void
    {
        $this->expectException(MiddagValidationException::class);
        Frankenstyle::fromString('mod2_assign');
    }

    #[Test]
    public function fromStringThrowsForInvalidTypeWithUppercase(): void
    {
        $this->expectException(MiddagValidationException::class);
        Frankenstyle::fromString('Mod_assign');
    }

    #[Test]
    public function fromStringHandlesNameWithNumbers(): void
    {
        $fs = Frankenstyle::fromString('mod_assign2');
        $this->assertSame('mod', $fs->type);
        $this->assertSame('assign2', $fs->name);
    }

    #[Test]
    public function fromStringUsesFirstUnderscoreForSplit(): void
    {
        // 'block_my_plugin' -> type='block', name='my_plugin'
        // But 'my_plugin' won't match /^[a-z][a-z0-9]*$/ because of the underscore
        $this->expectException(MiddagValidationException::class);
        Frankenstyle::fromString('block_my_plugin');
    }

    #[Test]
    public function localFactoryCreatesLocalComponent(): void
    {
        $fs = Frankenstyle::local('example');
        $this->assertSame('local', $fs->type);
        $this->assertSame('example', $fs->name);
        $this->assertSame('local_example', (string) $fs);
    }

    #[Test]
    public function modFactoryCreatesModComponent(): void
    {
        $fs = Frankenstyle::mod('assign');
        $this->assertSame('mod', $fs->type);
        $this->assertSame('assign', $fs->name);
        $this->assertSame('mod_assign', (string) $fs);
    }

    #[Test]
    public function blockFactoryCreatesBlockComponent(): void
    {
        $fs = Frankenstyle::block('calendar');
        $this->assertSame('block', $fs->type);
        $this->assertSame('calendar', $fs->name);
        $this->assertSame('block_calendar', (string) $fs);
    }

    #[Test]
    public function authFactoryCreatesAuthComponent(): void
    {
        $fs = Frankenstyle::auth('oauth2');
        $this->assertSame('auth', $fs->type);
        $this->assertSame('oauth2', $fs->name);
        $this->assertSame('auth_oauth2', (string) $fs);
    }

    #[Test]
    public function toolFactoryCreatesToolComponent(): void
    {
        $fs = Frankenstyle::tool('uploadcourse');
        $this->assertSame('tool', $fs->type);
        $this->assertSame('uploadcourse', $fs->name);
        $this->assertSame('tool_uploadcourse', (string) $fs);
    }

    #[Test]
    public function equalsReturnsTrueForSameTypeAndName(): void
    {
        $a = new Frankenstyle(type: 'mod', name: 'assign');
        $b = new Frankenstyle(type: 'mod', name: 'assign');
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentType(): void
    {
        $a = new Frankenstyle(type: 'mod', name: 'assign');
        $b = new Frankenstyle(type: 'local', name: 'assign');
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentName(): void
    {
        $a = new Frankenstyle(type: 'mod', name: 'assign');
        $b = new Frankenstyle(type: 'mod', name: 'forum');
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(Frankenstyle::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(Frankenstyle::class);
        $this->assertTrue($reflection->isFinal());
    }
}
