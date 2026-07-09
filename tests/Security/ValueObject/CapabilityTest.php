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
use Middag\Moodle\Config\ComponentContext;
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
    /**
     * Pin a deterministic host component so the host-scoped factory/predicate
     * derive a known capability prefix (local/middag).
     */
    protected function setUp(): void
    {
        parent::setUp();

        ComponentContext::reset();
        ComponentContext::configure('local_middag');
    }

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
    public function isHostComponentReturnsTrueForHostCapabilities(): void
    {
        $cap = new Capability('local/middag:manage');
        $this->assertTrue($cap->isHostComponent());
    }

    #[Test]
    public function isHostComponentReturnsFalseForNonHostCapabilities(): void
    {
        $cap = new Capability('moodle/course:view');
        $this->assertFalse($cap->isHostComponent());

        $cap2 = new Capability('mod/forum:addinstance');
        $this->assertFalse($cap2->isHostComponent());
    }

    #[Test]
    public function isValidFormatReturnsTrueForValid(): void
    {
        $this->assertTrue(Capability::isValidFormat('local/middag:manage'));
        $this->assertTrue(Capability::isValidFormat('moodle/course:view'));
        $this->assertTrue(Capability::isValidFormat('mod/forum:addinstance'));
    }

    #[Test]
    public function isValidFormatReturnsFalseForInvalid(): void
    {
        $this->assertFalse(Capability::isValidFormat('localmiddag:manage'));
        $this->assertFalse(Capability::isValidFormat('local/middag'));
        $this->assertFalse(Capability::isValidFormat('local/middag:'));
        $this->assertFalse(Capability::isValidFormat('manage'));
        $this->assertFalse(Capability::isValidFormat(''));
    }

    #[Test]
    public function forHostComponentFactoryCreatesHostScopedCapability(): void
    {
        $cap = Capability::forHostComponent('manage');
        $this->assertSame('local/middag:manage', $cap->identifier);
        $this->assertTrue($cap->isHostComponent());
    }

    #[Test]
    public function forHostComponentFactoryWithDifferentNames(): void
    {
        $view = Capability::forHostComponent('view');
        $this->assertSame('local/middag:view', $view->identifier);

        $configure = Capability::forHostComponent('configure');
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
