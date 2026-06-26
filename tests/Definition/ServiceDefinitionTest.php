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

use Middag\Moodle\Definition\DefinitionInterface;
use Middag\Moodle\Definition\Service;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class ServiceDefinitionTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithRequiredArgs(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external\do_thing');

        $this->assertSame('do_thing', $service->name);
        $this->assertSame('local_example\external\do_thing', $service->classname);
    }

    #[Test]
    public function hasCorrectDefaults(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');

        $this->assertSame('read', $service->type);
        $this->assertNull($service->method);
        $this->assertNull($service->description);
        $this->assertTrue($service->ajax);
        $this->assertSame([], $service->services);
        $this->assertNull($service->min_moodle);
        $this->assertNull($service->max_moodle);
        $this->assertNull($service->capabilities);
    }

    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $service = new Service(
            name: 'do_thing',
            classname: 'local_example\external\do_thing',
            type: 'write',
            method: 'execute',
            description: 'Does the thing',
            ajax: false,
            services: ['local_example_service'],
            min_moodle: '4.5',
            max_moodle: '5.0',
            capabilities: 'local/example:dothing, local/example:view',
        );

        $this->assertSame('write', $service->type);
        $this->assertSame('execute', $service->method);
        $this->assertSame('Does the thing', $service->description);
        $this->assertFalse($service->ajax);
        $this->assertSame(['local_example_service'], $service->services);
        $this->assertSame('4.5', $service->min_moodle);
        $this->assertSame('5.0', $service->max_moodle);
        $this->assertSame('local/example:dothing, local/example:view', $service->capabilities);
    }

    #[Test]
    public function implementsDefinitionInterface(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');
        $this->assertInstanceOf(DefinitionInterface::class, $service);
    }

    #[Test]
    public function getNameReturnsFunctionName(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');
        $this->assertSame('do_thing', $service->getName());
    }

    #[Test]
    public function getQualifiedNamePrefixesPlugin(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');
        $this->assertSame('local_example_do_thing', $service->get_qualified_name('local_example'));
    }

    #[Test]
    public function toMoodleArrayReturnsBasicStructure(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');
        $result = $service->toMoodleArray('local_example');

        $this->assertSame('local_example\external', $result['classname']);
        $this->assertSame('do_thing', $result['methodname']);
        $this->assertSame('', $result['description']);
        $this->assertSame('read', $result['type']);
        $this->assertTrue($result['ajax']);
    }

    #[Test]
    public function toMoodleArrayMethodDefaultsToName(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');
        $this->assertSame('do_thing', $service->toMoodleArray('local_example')['methodname']);
    }

    #[Test]
    public function toMoodleArrayUsesExplicitMethod(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external', method: 'execute');
        $this->assertSame('execute', $service->toMoodleArray('local_example')['methodname']);
    }

    #[Test]
    public function toMoodleArrayIncludesServicesWhenSet(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external', services: ['s1', 's2']);
        $this->assertSame(['s1', 's2'], $service->toMoodleArray('local_example')['services']);
    }

    #[Test]
    public function toMoodleArrayOmitsServicesWhenEmpty(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');
        $this->assertArrayNotHasKey('services', $service->toMoodleArray('local_example'));
    }

    #[Test]
    public function toMoodleArrayIncludesCapabilitiesWhenSet(): void
    {
        $service = new Service(
            name: 'do_thing',
            classname: 'local_example\external',
            capabilities: 'local/example:dothing',
        );

        $result = $service->toMoodleArray('local_example');

        $this->assertArrayHasKey('capabilities', $result);
        $this->assertSame('local/example:dothing', $result['capabilities']);
    }

    #[Test]
    public function toMoodleArrayTrimsCapabilities(): void
    {
        $service = new Service(
            name: 'do_thing',
            classname: 'local_example\external',
            capabilities: '  local/example:dothing  ',
        );

        $this->assertSame('local/example:dothing', $service->toMoodleArray('local_example')['capabilities']);
    }

    #[Test]
    public function toMoodleArrayOmitsCapabilitiesWhenNull(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');
        $this->assertArrayNotHasKey('capabilities', $service->toMoodleArray('local_example'));
    }

    #[Test]
    public function toMoodleArrayOmitsCapabilitiesWhenEmptyOrWhitespace(): void
    {
        $empty = new Service(name: 'a', classname: 'local_example\external', capabilities: '');
        $blank = new Service(name: 'b', classname: 'local_example\external', capabilities: '   ');

        $this->assertArrayNotHasKey('capabilities', $empty->toMoodleArray('local_example'));
        $this->assertArrayNotHasKey('capabilities', $blank->toMoodleArray('local_example'));
    }

    #[Test]
    public function isCompatibleReturnsTrueWithNoBounds(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external');
        $this->assertTrue($service->isCompatible('4.5'));
        $this->assertTrue($service->isCompatible('5.1'));
    }

    #[Test]
    public function isCompatibleRespectsMinMoodle(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external', min_moodle: '4.5');
        $this->assertFalse($service->isCompatible('4.4'));
        $this->assertTrue($service->isCompatible('4.5'));
        $this->assertTrue($service->isCompatible('5.1'));
    }

    #[Test]
    public function isCompatibleRespectsMaxMoodle(): void
    {
        $service = new Service(name: 'do_thing', classname: 'local_example\external', max_moodle: '5.0');
        $this->assertTrue($service->isCompatible('4.5'));
        $this->assertFalse($service->isCompatible('5.1'));
    }

    #[Test]
    public function isReadonly(): void
    {
        $this->assertTrue((new ReflectionClass(Service::class))->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $this->assertTrue((new ReflectionClass(Service::class))->isFinal());
    }
}
