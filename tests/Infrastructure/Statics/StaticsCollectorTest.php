<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Infrastructure\Statics;

use Middag\Moodle\Infrastructure\Statics\StaticsCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
#[CoversClass(StaticsCollector::class)]
final class StaticsCollectorTest extends TestCase
{
    public function testCollectReturnsEmptyForUnknownTarget(): void
    {
        $collector = new StaticsCollector();
        $extension = new FakeExtensionFixture('demo', []);

        self::assertSame([], $collector->collect('unknown_target', [$extension]));
    }

    public function testCollectCachesAggregatesAcrossExtensions(): void
    {
        $extA = new FakeExtensionFixture('ext_a', [
            'getCacheDefinitions' => [new FakeCacheDefinition('cache_one')],
        ]);
        $extB = new FakeExtensionFixture('ext_b', [
            'getCacheDefinitions' => [new FakeCacheDefinition('cache_two')],
        ]);

        $result = (new StaticsCollector())->collect('caches', [$extA, $extB]);

        self::assertCount(2, $result);
        self::assertSame('cache_one', $result[0]->getName());
        self::assertSame('cache_two', $result[1]->getName());
    }

    public function testCollectAccessPairsDefinitionWithExtensionName(): void
    {
        $ext = new FakeExtensionFixture('myext', [
            'getCapabilities' => [new FakeCapabilityDefinition('view')],
        ]);

        $result = (new StaticsCollector())->collect('access', [$ext]);

        self::assertCount(1, $result);
        self::assertSame('myext', $result[0]['extension']);
        self::assertSame('view', $result[0]['definition']->getName());
    }

    public function testCollectSwallowsPerExtensionExceptions(): void
    {
        $broken = new FakeExtensionFixture('broken', [
            'getCacheDefinitions' => new RuntimeException('boom'),
        ]);
        $ok = new FakeExtensionFixture('ok', [
            'getCacheDefinitions' => [new FakeCacheDefinition('survives')],
        ]);

        $result = (new StaticsCollector())->collect('caches', [$broken, $ok]);

        self::assertCount(1, $result);
        self::assertSame('survives', $result[0]->getName());
    }

    public function testFilterCompatibleDropsIncompatibleDefinitions(): void
    {
        $compat = new FakeCacheDefinition('keep');
        $incompat = new FakeCacheDefinition('drop', compatible: false);

        $filtered = (new StaticsCollector())->filterCompatible([$compat, $incompat], '4.3.0');

        self::assertCount(1, $filtered);
        self::assertSame('keep', $filtered[0]->getName());
    }

    #[DataProvider('eventNormalizationCases')]
    public function testNormalizeEventName(mixed $input, ?string $expected): void
    {
        self::assertSame($expected, StaticsCollector::normalizeEventName($input));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: ?string}>
     */
    public static function eventNormalizationCases(): iterable
    {
        yield 'non-string returns null' => [123, null];

        yield 'empty string returns null' => ['', null];

        yield 'wildcard preserved' => ['*', '*'];

        yield 'forward slashes converted' => ['foo/bar/event', '\foo\bar\event'];

        yield 'leading backslashes normalized' => ['\\\\\foo\event', '\foo\event'];

        yield 'plain class normalized' => ['foo\bar', '\foo\bar'];
    }

    public function testCollectEventsDeduplicatesAndSorts(): void
    {
        $extA = new FakeExtensionFixture('a', [
            'get_moodle_events' => ['core\event\user_created', 'core\event\login'],
        ]);
        $extB = new FakeExtensionFixture('b', [
            'get_moodle_events' => ['core\event\user_created', '*', 'core\event\logout'],
        ]);

        $events = (new StaticsCollector())->collectEvents([$extA, $extB]);

        self::assertSame(
            ['\core\event\login', '\core\event\logout', '\core\event\user_created'],
            $events,
        );
    }
}

final readonly class FakeCacheDefinition
{
    public function __construct(
        public string $name,
        public bool $compatible = true,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isCompatible(string $moodleVersion): bool
    {
        return $this->compatible;
    }

    /**
     * @return array<string, mixed>
     */
    public function to_moodle_array(string $pluginName): array
    {
        return ['mode' => 1, 'simplekeys' => false, 'simpledata' => false];
    }
}

final readonly class FakeCapabilityDefinition
{
    public function __construct(public string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isCompatible(string $moodleVersion): bool
    {
        return true;
    }

    public function get_qualified_name(string $pluginName, ?string $extension = null): string
    {
        return $pluginName . '/' . ($extension ?? 'core') . ':' . $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function to_moodle_array(string $pluginName): array
    {
        return [
            'riskbitmask' => 1,
            'captype' => 'read',
            'contextlevel' => 50,
            'archetypes' => ['student' => 'allow'],
        ];
    }
}

final readonly class FakeExtensionFixture
{
    /**
     * @param array<string, mixed> $methodReturns
     */
    public function __construct(
        private string $name,
        private array $methodReturns,
    ) {}

    public function __call(string $method, array $arguments): mixed
    {
        $value = $this->methodReturns[$method] ?? [];

        if ($value instanceof Throwable) {
            throw $value;
        }

        return $value;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
