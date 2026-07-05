<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Event\Attribute;

use Attribute;
use Middag\Moodle\Domain\Event\Attribute\MoodleEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(MoodleEvent::class)]
final class MoodleEventTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithEventClass(): void
    {
        $attr = new MoodleEvent(eventClass: '\core\event\user_created');
        $this->assertSame('\core\event\user_created', $attr->eventClass);
    }

    #[Test]
    public function storesEventClassAsString(): void
    {
        $attr = new MoodleEvent(eventClass: '\core\event\user_enrolment_created');
        $this->assertSame('\core\event\user_enrolment_created', $attr->eventClass);
    }

    #[Test]
    public function isAPhpAttribute(): void
    {
        $reflection = new ReflectionClass(MoodleEvent::class);
        $attributes = $reflection->getAttributes(Attribute::class);
        $this->assertCount(1, $attributes);
    }

    #[Test]
    public function targetsClassesOnly(): void
    {
        $reflection = new ReflectionClass(MoodleEvent::class);
        $attributes = $reflection->getAttributes(Attribute::class);
        $this->assertCount(1, $attributes);

        $attrInstance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_CLASS, $attrInstance->flags);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(MoodleEvent::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(MoodleEvent::class);
        $this->assertTrue($reflection->isFinal());
    }

    #[Test]
    public function constructorHasOneParameter(): void
    {
        $reflection = new ReflectionClass(MoodleEvent::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
    }

    #[Test]
    public function eventClassParameterIsString(): void
    {
        $reflection = new ReflectionClass(MoodleEvent::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();
        $this->assertSame('eventClass', $params[0]->getName());
        $this->assertSame('string', $params[0]->getType()->getName());
    }

    #[Test]
    public function canBeUsedAsAttributeOnClass(): void
    {
        // Create a test class with the attribute applied
        $testClass = new #[MoodleEvent('\core\event\course_viewed')] class {};

        $reflection = new ReflectionClass($testClass);
        $attributes = $reflection->getAttributes(MoodleEvent::class);

        $this->assertCount(1, $attributes);
        $instance = $attributes[0]->newInstance();
        $this->assertSame('\core\event\course_viewed', $instance->eventClass);
    }
}
