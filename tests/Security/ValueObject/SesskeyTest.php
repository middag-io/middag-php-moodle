<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Security\ValueObject;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Moodle\Security\ValueObject\Sesskey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * @internal
 */
#[CoversClass(Sesskey::class)]
final class SesskeyTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_sesskey']);
    }

    #[Test]
    public function fromCurrentReadsTheCurrentMoodleSessionKey(): void
    {
        // from_current() wraps Moodle's sesskey(); the bootstrap stub returns
        // whatever the driving global holds.
        $GLOBALS['__middag_test_sesskey'] = 'abc123';

        self::assertSame('abc123', Sesskey::from_current()->value);
    }

    #[Test]
    public function canBeConstructedWithValue(): void
    {
        $sesskey = new Sesskey(value: 'abc123');
        $this->assertSame('abc123', $sesskey->value);
    }

    #[Test]
    public function toStringReturnsValue(): void
    {
        $sesskey = new Sesskey(value: 'abc123');
        $this->assertSame('abc123', (string) $sesskey);
    }

    #[Test]
    public function implementsStringable(): void
    {
        $sesskey = new Sesskey(value: 'abc123');
        $this->assertInstanceOf(Stringable::class, $sesskey);
    }

    #[Test]
    public function fromStringCreatesFromValidValue(): void
    {
        $sesskey = Sesskey::from_string('abcDEF123');
        $this->assertSame('abcDEF123', $sesskey->value);
    }

    #[Test]
    public function fromStringTrimsWhitespace(): void
    {
        $sesskey = Sesskey::from_string('  abc123  ');
        $this->assertSame('abc123', $sesskey->value);
    }

    #[Test]
    public function fromStringThrowsForEmptyString(): void
    {
        $this->expectException(MiddagValidationException::class);
        $this->expectExceptionMessage('Invalid sesskey: must be 1-40 characters');
        Sesskey::from_string('');
    }

    #[Test]
    public function fromStringThrowsForWhitespaceOnly(): void
    {
        $this->expectException(MiddagValidationException::class);
        Sesskey::from_string('   ');
    }

    #[Test]
    public function fromStringThrowsForValueOver40Characters(): void
    {
        $this->expectException(MiddagValidationException::class);
        $this->expectExceptionMessage('Invalid sesskey: must be 1-40 characters');
        Sesskey::from_string(str_repeat('a', 41));
    }

    #[Test]
    public function fromStringAccepts40CharacterValue(): void
    {
        $value = str_repeat('a', 40);
        $sesskey = Sesskey::from_string($value);
        $this->assertSame($value, $sesskey->value);
    }

    #[Test]
    public function fromStringAcceptsSingleCharacter(): void
    {
        $sesskey = Sesskey::from_string('a');
        $this->assertSame('a', $sesskey->value);
    }

    #[Test]
    public function fromStringThrowsForNonAlphanumeric(): void
    {
        $this->expectException(MiddagValidationException::class);
        $this->expectExceptionMessage('Invalid sesskey: must be alphanumeric');
        Sesskey::from_string('abc-123');
    }

    #[Test]
    public function fromStringThrowsForSpecialCharacters(): void
    {
        $this->expectException(MiddagValidationException::class);
        Sesskey::from_string('abc!@#');
    }

    #[Test]
    public function fromStringThrowsForSpacesInValue(): void
    {
        $this->expectException(MiddagValidationException::class);
        Sesskey::from_string('abc 123');
    }

    #[Test]
    public function matchesReturnsTrueForEqualValue(): void
    {
        $sesskey = new Sesskey(value: 'abc123');
        $this->assertTrue($sesskey->matches('abc123'));
    }

    #[Test]
    public function matchesReturnsFalseForDifferentValue(): void
    {
        $sesskey = new Sesskey(value: 'abc123');
        $this->assertFalse($sesskey->matches('xyz789'));
    }

    #[Test]
    public function matchesReturnsFalseForEmptyString(): void
    {
        $sesskey = new Sesskey(value: 'abc123');
        $this->assertFalse($sesskey->matches(''));
    }

    #[Test]
    public function matchesIsCaseSensitive(): void
    {
        $sesskey = new Sesskey(value: 'AbC123');
        $this->assertFalse($sesskey->matches('abc123'));
        $this->assertTrue($sesskey->matches('AbC123'));
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(Sesskey::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(Sesskey::class);
        $this->assertTrue($reflection->isFinal());
    }
}
