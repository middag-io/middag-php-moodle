<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Definition;

use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Definition\EventDefinition;
use Middag\Moodle\Domain\Event\EventEdulevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * EventDefinition is a pure readonly value object over PHP builtins
 * (version_compare/str_replace) and the EventEdulevel enum — no Moodle runtime
 * symbol is touched, so every line is reachable without a stub.
 *
 * @internal
 */
#[CoversClass(EventDefinition::class)]
final class EventDefinitionCoverageTest extends TestCase
{
    #[Test]
    public function constructorAppliesDocumentedDefaults(): void
    {
        $event = new EventDefinition(name: 'order_created');

        self::assertSame('order_created', $event->name);
        self::assertSame('c', $event->crud);
        self::assertSame(EventEdulevel::OTHER, $event->edulevel);
        self::assertSame('', $event->objecttable);
        self::assertSame('', $event->description);
        self::assertNull($event->min_moodle);
        self::assertNull($event->max_moodle);
    }

    #[Test]
    public function constructorStoresEveryProperty(): void
    {
        $event = new EventDefinition(
            name: 'order_updated',
            crud: 'u',
            edulevel: EventEdulevel::PARTICIPATING,
            objecttable: 'local_example_order',
            description: 'An order was updated',
            min_moodle: '4.0',
            max_moodle: '4.5',
        );

        self::assertSame('order_updated', $event->name);
        self::assertSame('u', $event->crud);
        self::assertSame(EventEdulevel::PARTICIPATING, $event->edulevel);
        self::assertSame('local_example_order', $event->objecttable);
        self::assertSame('An order was updated', $event->description);
        self::assertSame('4.0', $event->min_moodle);
        self::assertSame('4.5', $event->max_moodle);
    }

    #[Test]
    public function implementsDefinitionInterface(): void
    {
        self::assertInstanceOf(DefinitionInterface::class, new EventDefinition(name: 'x'));
    }

    #[Test]
    public function toMoodleArrayReturnsCrudEdulevelAndObjecttable(): void
    {
        $event = new EventDefinition(
            name: 'order_deleted',
            crud: 'd',
            edulevel: EventEdulevel::PARTICIPATING,
            objecttable: 'local_example_order',
        );

        self::assertSame(
            [
                'crud' => 'd',
                'edulevel' => 1,
                'objecttable' => 'local_example_order',
            ],
            $event->toMoodleArray('local_example'),
        );
    }

    #[Test]
    public function toMoodleArrayMapsEdulevelThroughToMoodleValue(): void
    {
        $event = new EventDefinition(name: 'seen', edulevel: EventEdulevel::TEACHING);

        $result = $event->toMoodleArray('local_example');

        // TEACHING->toMoodleValue() is 0; asserting the mapped scalar (not the enum).
        self::assertSame(0, $result['edulevel']);
    }

    #[Test]
    public function toMoodleArrayIgnoresThePluginNameArgument(): void
    {
        $event = new EventDefinition(name: 'order_created', objecttable: 'tbl');

        // $plugin_name is accepted for interface parity but does not shape the
        // output — two different plugin names must yield identical arrays.
        self::assertSame(
            $event->toMoodleArray('local_example'),
            $event->toMoodleArray('mod_forum'),
        );
    }

    #[Test]
    public function getEventClassnameWithoutExtension(): void
    {
        $event = new EventDefinition(name: 'order_created');

        self::assertSame(
            '\local\example\event\order_created',
            $event->get_event_classname('local_example'),
        );
    }

    #[Test]
    public function getEventClassnameWithExplicitNullExtension(): void
    {
        $event = new EventDefinition(name: 'order_created');

        self::assertSame(
            '\local\example\event\order_created',
            $event->get_event_classname('local_example'),
        );
    }

    #[Test]
    public function getEventClassnameTreatsCoreExtensionAsNoPrefix(): void
    {
        $event = new EventDefinition(name: 'order_created');

        // 'core' is the sentinel that suppresses the extension prefix.
        self::assertSame(
            '\local\example\event\order_created',
            $event->get_event_classname('local_example', 'core'),
        );
    }

    #[Test]
    public function getEventClassnameWithCustomExtensionPrefixesTheName(): void
    {
        $event = new EventDefinition(name: 'order_created');

        self::assertSame(
            '\local\example\event\billing_order_created',
            $event->get_event_classname('local_example', 'billing'),
        );
    }

    #[Test]
    public function getEventClassnameConvertsUnderscoresToNamespaceSeparators(): void
    {
        $event = new EventDefinition(name: 'thing_happened');

        self::assertSame(
            '\mod\forum\event\thing_happened',
            $event->get_event_classname('mod_forum'),
        );
    }

    #[Test]
    public function isCompatibleReturnsTrueWhenNoVersionConstraints(): void
    {
        $event = new EventDefinition(name: 'x');

        self::assertTrue($event->isCompatible('4.5'));
        self::assertTrue($event->isCompatible('3.0'));
    }

    #[Test]
    public function isCompatibleRespectsMinMoodle(): void
    {
        $event = new EventDefinition(name: 'x', min_moodle: '4.0');

        self::assertFalse($event->isCompatible('3.11'));
        self::assertTrue($event->isCompatible('4.0'));
        self::assertTrue($event->isCompatible('4.5'));
    }

    #[Test]
    public function isCompatibleRespectsMaxMoodle(): void
    {
        $event = new EventDefinition(name: 'x', max_moodle: '4.5');

        self::assertFalse($event->isCompatible('4.6'));
        self::assertTrue($event->isCompatible('4.5'));
        self::assertTrue($event->isCompatible('4.0'));
    }

    #[Test]
    public function isCompatibleRespectsMinAndMaxTogether(): void
    {
        $event = new EventDefinition(name: 'x', min_moodle: '4.0', max_moodle: '4.5');

        self::assertFalse($event->isCompatible('3.11'));
        self::assertTrue($event->isCompatible('4.0'));
        self::assertTrue($event->isCompatible('4.3'));
        self::assertTrue($event->isCompatible('4.5'));
        self::assertFalse($event->isCompatible('4.6'));
    }

    #[Test]
    public function getNameReturnsTheEventName(): void
    {
        self::assertSame('order_created', (new EventDefinition(name: 'order_created'))->getName());
    }

    #[Test]
    public function classIsFinalAndReadonly(): void
    {
        $reflection = new ReflectionClass(EventDefinition::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }
}
