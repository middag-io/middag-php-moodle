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
use Middag\Moodle\Definition\WebServiceDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * WebServiceDefinition is a pure, readonly value object: it holds a web-service
 * group descriptor and renders it to the $services array shape used in
 * db/services.php. It touches no Moodle runtime symbol (only the native
 * version_compare()), so every line is exercised by direct construction.
 *
 * @internal
 */
#[CoversClass(WebServiceDefinition::class)]
final class WebServiceDefinitionCoverageTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithRequiredArgs(): void
    {
        $service = new WebServiceDefinition(
            name: 'Example service',
            shortname: 'local_example_service',
            functions: ['local_example_do_thing', 'local_example_read_thing'],
        );

        $this->assertSame('Example service', $service->name);
        $this->assertSame('local_example_service', $service->shortname);
        $this->assertSame(['local_example_do_thing', 'local_example_read_thing'], $service->functions);
    }

    #[Test]
    public function hasCorrectDefaults(): void
    {
        $service = new WebServiceDefinition(
            name: 'Example service',
            shortname: 'local_example_service',
            functions: [],
        );

        $this->assertTrue($service->enabled);
        $this->assertSame(0, $service->restricted_users);
        $this->assertNull($service->min_moodle);
        $this->assertNull($service->max_moodle);
    }

    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $service = new WebServiceDefinition(
            name: 'Example service',
            shortname: 'local_example_service',
            functions: ['local_example_do_thing'],
            enabled: false,
            restricted_users: 1,
            min_moodle: '4.5',
            max_moodle: '5.0',
        );

        $this->assertSame('Example service', $service->name);
        $this->assertSame('local_example_service', $service->shortname);
        $this->assertSame(['local_example_do_thing'], $service->functions);
        $this->assertFalse($service->enabled);
        $this->assertSame(1, $service->restricted_users);
        $this->assertSame('4.5', $service->min_moodle);
        $this->assertSame('5.0', $service->max_moodle);
    }

    #[Test]
    public function implementsDefinitionInterface(): void
    {
        $service = new WebServiceDefinition(name: 'S', shortname: 's', functions: []);

        $this->assertInstanceOf(DefinitionInterface::class, $service);
    }

    #[Test]
    public function getNameReturnsShortname(): void
    {
        $service = new WebServiceDefinition(name: 'Example service', shortname: 'local_example_service', functions: []);

        $this->assertSame('local_example_service', $service->getName());
    }

    #[Test]
    public function toMoodleArrayReturnsTheServicesShape(): void
    {
        $service = new WebServiceDefinition(
            name: 'Example service',
            shortname: 'local_example_service',
            functions: ['local_example_do_thing', 'local_example_read_thing'],
            restricted_users: 1,
        );

        $result = $service->toMoodleArray('local_example');

        $this->assertSame('local_example_service', $result['shortname']);
        $this->assertSame(1, $result['restrictedusers']);
        $this->assertSame(['local_example_do_thing', 'local_example_read_thing'], $result['functions']);
        $this->assertSame(['shortname', 'enabled', 'restrictedusers', 'functions'], array_keys($result));
    }

    #[Test]
    public function toMoodleArrayCastsEnabledTrueToOne(): void
    {
        $service = new WebServiceDefinition(name: 'S', shortname: 's', functions: [], enabled: true);

        $this->assertSame(1, $service->toMoodleArray('local_example')['enabled']);
    }

    #[Test]
    public function toMoodleArrayCastsEnabledFalseToZero(): void
    {
        $service = new WebServiceDefinition(name: 'S', shortname: 's', functions: [], enabled: false);

        $this->assertSame(0, $service->toMoodleArray('local_example')['enabled']);
    }

    #[Test]
    public function toMoodleArrayIgnoresThePluginNameArgument(): void
    {
        // The $plugin_name argument is part of the DefinitionInterface contract
        // but does not influence the $services shape (the shortname is the key
        // elsewhere), so the output is identical regardless of the plugin.
        $service = new WebServiceDefinition(name: 'S', shortname: 's', functions: ['f']);

        $this->assertSame(
            $service->toMoodleArray('local_example'),
            $service->toMoodleArray('mod_other'),
        );
    }

    #[Test]
    public function isCompatibleReturnsTrueWithNoBounds(): void
    {
        $service = new WebServiceDefinition(name: 'S', shortname: 's', functions: []);

        $this->assertTrue($service->isCompatible('4.5'));
        $this->assertTrue($service->isCompatible('5.1'));
    }

    #[Test]
    public function isCompatibleRespectsMinMoodle(): void
    {
        $service = new WebServiceDefinition(name: 'S', shortname: 's', functions: [], min_moodle: '4.5');

        $this->assertFalse($service->isCompatible('4.4'));
        $this->assertTrue($service->isCompatible('4.5'));
        $this->assertTrue($service->isCompatible('5.1'));
    }

    #[Test]
    public function isCompatibleRespectsMaxMoodle(): void
    {
        $service = new WebServiceDefinition(name: 'S', shortname: 's', functions: [], max_moodle: '5.0');

        $this->assertTrue($service->isCompatible('4.5'));
        $this->assertTrue($service->isCompatible('5.0'));
        $this->assertFalse($service->isCompatible('5.1'));
    }

    #[Test]
    public function isCompatibleRespectsMinAndMaxMoodle(): void
    {
        $service = new WebServiceDefinition(
            name: 'S',
            shortname: 's',
            functions: [],
            min_moodle: '4.0',
            max_moodle: '4.5',
        );

        $this->assertFalse($service->isCompatible('3.11'));
        $this->assertTrue($service->isCompatible('4.0'));
        $this->assertTrue($service->isCompatible('4.3'));
        $this->assertTrue($service->isCompatible('4.5'));
        $this->assertFalse($service->isCompatible('4.6'));
    }

    #[Test]
    public function isReadonly(): void
    {
        $this->assertTrue((new ReflectionClass(WebServiceDefinition::class))->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $this->assertTrue((new ReflectionClass(WebServiceDefinition::class))->isFinal());
    }
}
