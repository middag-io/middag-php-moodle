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

use Middag\Moodle\Definition\CacheDefinition;
use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Domain\Platform\CacheMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(CacheDefinition::class)]
final class CacheDefinitionCoverageTest extends TestCase
{
    #[Test]
    public function constructorAppliesDefaults(): void
    {
        $cache = new CacheDefinition(name: 'sessions');

        self::assertSame('sessions', $cache->name);
        self::assertSame(CacheMode::Application, $cache->mode);
        self::assertTrue($cache->simple_keys);
        self::assertFalse($cache->simple_data);
        self::assertNull($cache->min_moodle);
        self::assertNull($cache->max_moodle);
    }

    #[Test]
    public function constructorStoresAllArguments(): void
    {
        $cache = new CacheDefinition(
            name: 'lookups',
            mode: CacheMode::Session,
            simple_keys: false,
            simple_data: true,
            min_moodle: '4.0',
            max_moodle: '5.0',
        );

        self::assertSame('lookups', $cache->name);
        self::assertSame(CacheMode::Session, $cache->mode);
        self::assertFalse($cache->simple_keys);
        self::assertTrue($cache->simple_data);
        self::assertSame('4.0', $cache->min_moodle);
        self::assertSame('5.0', $cache->max_moodle);
    }

    #[Test]
    public function implementsDefinitionInterface(): void
    {
        self::assertInstanceOf(DefinitionInterface::class, new CacheDefinition(name: 'sessions'));
    }

    #[Test]
    public function getNameReturnsTheDefinitionName(): void
    {
        self::assertSame('sessions', (new CacheDefinition(name: 'sessions'))->getName());
    }

    #[Test]
    public function toMoodleArrayEmitsModeAndSimpleKeysWithoutSimpleDataByDefault(): void
    {
        $entry = (new CacheDefinition(name: 'sessions'))->toMoodleArray('local_example');

        // simple_data defaults to false → the 'simpledata' key must be absent.
        self::assertSame(
            ['mode' => CacheMode::Application->value, 'simplekeys' => true],
            $entry,
        );
        self::assertArrayNotHasKey('simpledata', $entry);
    }

    #[Test]
    public function toMoodleArrayForwardsSimpleKeysFalse(): void
    {
        $entry = (new CacheDefinition(name: 'sessions', simple_keys: false))->toMoodleArray('local_example');

        self::assertFalse($entry['simplekeys']);
    }

    #[Test]
    public function toMoodleArrayAddsSimpleDataWhenEnabled(): void
    {
        $entry = (new CacheDefinition(name: 'sessions', simple_data: true))->toMoodleArray('local_example');

        self::assertArrayHasKey('simpledata', $entry);
        self::assertTrue($entry['simpledata']);
    }

    #[Test]
    public function toMoodleArrayMapsSessionMode(): void
    {
        $entry = (new CacheDefinition(name: 'sessions', mode: CacheMode::Session))->toMoodleArray('local_example');

        self::assertSame(CacheMode::Session->value, $entry['mode']);
    }

    #[Test]
    public function toMoodleArrayMapsRequestMode(): void
    {
        $entry = (new CacheDefinition(name: 'sessions', mode: CacheMode::Request))->toMoodleArray('local_example');

        self::assertSame(CacheMode::Request->value, $entry['mode']);
    }

    #[Test]
    public function isCompatibleReturnsTrueWithNoVersionConstraints(): void
    {
        $cache = new CacheDefinition(name: 'sessions');

        self::assertTrue($cache->isCompatible('3.0'));
        self::assertTrue($cache->isCompatible('5.1'));
    }

    #[Test]
    public function isCompatibleRespectsMinMoodle(): void
    {
        $cache = new CacheDefinition(name: 'sessions', min_moodle: '4.0');

        self::assertFalse($cache->isCompatible('3.11')); // below min → false
        self::assertTrue($cache->isCompatible('4.0'));   // equal to min → allowed
        self::assertTrue($cache->isCompatible('4.5'));   // above min → allowed
    }

    #[Test]
    public function isCompatibleRespectsMaxMoodle(): void
    {
        $cache = new CacheDefinition(name: 'sessions', max_moodle: '4.5');

        self::assertFalse($cache->isCompatible('4.6')); // above max → false
        self::assertTrue($cache->isCompatible('4.5'));  // equal to max → allowed
        self::assertTrue($cache->isCompatible('4.0'));  // below max → allowed
    }

    #[Test]
    public function isCompatibleRespectsMinAndMaxMoodle(): void
    {
        $cache = new CacheDefinition(name: 'sessions', min_moodle: '4.0', max_moodle: '4.5');

        self::assertFalse($cache->isCompatible('3.11')); // below min → false
        self::assertTrue($cache->isCompatible('4.0'));
        self::assertTrue($cache->isCompatible('4.3'));
        self::assertTrue($cache->isCompatible('4.5'));
        self::assertFalse($cache->isCompatible('4.6'));  // above max → false
    }

    #[Test]
    public function classIsFinalAndReadonly(): void
    {
        $reflection = new ReflectionClass(CacheDefinition::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }
}
