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

use Middag\Framework\Exception\MiddagDomainException;
use Middag\Moodle\Security\ValueObject\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * @internal
 */
#[CoversClass(Capability::class)]
final class CapabilityTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithValidIdentifier(): void
    {
        $cap = new Capability('local/middag:manage');
        $this->assertSame('local/middag:manage', $cap->identifier);
    }

    #[Test]
    public function throwsForIdentifierWithoutColon(): void
    {
        $this->expectException(MiddagDomainException::class);
        $this->expectExceptionMessage('Invalid capability format');
        new Capability('local/middag');
    }

    #[Test]
    public function throwsForIdentifierWithoutSlash(): void
    {
        $this->expectException(MiddagDomainException::class);
        new Capability('localmiddag:manage');
    }

    #[Test]
    public function throwsForEmptyNamePart(): void
    {
        $this->expectException(MiddagDomainException::class);
        new Capability('local/middag:');
    }

    #[Test]
    public function toStringReturnsIdentifier(): void
    {
        $cap = new Capability('local/middag:manage');
        $this->assertSame('local/middag:manage', (string) $cap);
    }

    #[Test]
    public function implementsStringable(): void
    {
        $cap = new Capability('local/middag:manage');
        $this->assertInstanceOf(Stringable::class, $cap);
    }

    #[Test]
    public function componentReturnsPartBeforeColon(): void
    {
        $cap = new Capability('local/middag:manage');
        $this->assertSame('local/middag', $cap->component());
    }

    #[Test]
    public function componentReturnsMoodleCoreComponent(): void
    {
        $cap = new Capability('moodle/course:view');
        $this->assertSame('moodle/course', $cap->component());
    }

    #[Test]
    public function nameReturnsPartAfterColon(): void
    {
        $cap = new Capability('local/middag:manage');
        $this->assertSame('manage', $cap->name());
    }

    #[Test]
    public function nameReturnsComplexName(): void
    {
        $cap = new Capability('mod/forum:addinstance');
        $this->assertSame('addinstance', $cap->name());
    }

    #[Test]
    public function isMiddagReturnsTrueForMiddagCapabilities(): void
    {
        $cap = new Capability('local/middag:manage');
        $this->assertTrue($cap->is_middag());
    }

    #[Test]
    public function isMiddagReturnsFalseForNonMiddagCapabilities(): void
    {
        $cap = new Capability('moodle/course:view');
        $this->assertFalse($cap->is_middag());

        $cap2 = new Capability('mod/forum:addinstance');
        $this->assertFalse($cap2->is_middag());
    }

    #[Test]
    public function isValidFormatReturnsTrueForValid(): void
    {
        $this->assertTrue(Capability::is_valid_format('local/middag:manage'));
        $this->assertTrue(Capability::is_valid_format('moodle/course:view'));
        $this->assertTrue(Capability::is_valid_format('mod/forum:addinstance'));
    }

    #[Test]
    public function isValidFormatReturnsFalseForInvalid(): void
    {
        $this->assertFalse(Capability::is_valid_format('localmiddag:manage'));
        $this->assertFalse(Capability::is_valid_format('local/middag'));
        $this->assertFalse(Capability::is_valid_format('local/middag:'));
        $this->assertFalse(Capability::is_valid_format('manage'));
        $this->assertFalse(Capability::is_valid_format(''));
    }

    #[Test]
    public function middagFactoryCreatesMiddagScopedCapability(): void
    {
        $cap = Capability::middag('manage');
        $this->assertSame('local/middag:manage', $cap->identifier);
        $this->assertTrue($cap->is_middag());
    }

    #[Test]
    public function middagFactoryWithDifferentNames(): void
    {
        $view = Capability::middag('view');
        $this->assertSame('local/middag:view', $view->identifier);

        $configure = Capability::middag('configure');
        $this->assertSame('local/middag:configure', $configure->identifier);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(Capability::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(Capability::class);
        $this->assertTrue($reflection->isFinal());
    }
}
