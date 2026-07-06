<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Statics;

use Middag\Moodle\Statics\StaticsCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * Complements StaticsCollectorTest by exercising the guard/skip branches its
 * happy-path cases never reach: modules that do not expose the target method
 * (is_callable false), methods returning a non-iterable, event names that
 * normalize to null, and the per-module error path in collectEvents(). The
 * collector touches no Moodle runtime symbols, so no Moodle stubs are involved.
 *
 * @internal
 */
#[CoversClass(StaticsCollector::class)]
final class StaticsCollectorCoverageTest extends TestCase
{
    #[Test]
    public function testCollectSkipsModulesMissingTheTargetMethod(): void
    {
        // [$module, 'getCacheDefinitions'] is not callable (no method, no __call)
        // → the module is skipped; only the healthy module contributes.
        $missing = new CoverageNonCallableModule('missing');
        $valid = new CoverageConfigurableModule('valid', [
            'getCacheDefinitions' => [new CoverageDefinition('kept')],
        ]);

        $result = (new StaticsCollector())->collect('caches', [$missing, $valid]);

        self::assertCount(1, $result);
        self::assertSame('kept', $result[0]->getName());
    }

    #[Test]
    public function testCollectSkipsNonIterableDefinitionReturns(): void
    {
        // getCacheDefinitions returns a scalar → not iterable → skipped.
        $scalar = new CoverageConfigurableModule('scalar', ['getCacheDefinitions' => 42]);
        $valid = new CoverageConfigurableModule('valid', [
            'getCacheDefinitions' => [new CoverageDefinition('kept')],
        ]);

        $result = (new StaticsCollector())->collect('caches', [$scalar, $valid]);

        self::assertSame(
            ['kept'],
            array_map(static fn (CoverageDefinition $d): string => $d->getName(), $result),
        );
    }

    #[Test]
    public function testCollectEventsSkipsModulesWithoutTheEventsMethod(): void
    {
        // [$module, 'get_moodle_events'] not callable → module skipped.
        $missing = new CoverageNonCallableModule('missing');
        $valid = new CoverageConfigurableModule('valid', [
            'get_moodle_events' => ['core\event\course_viewed'],
        ]);

        $events = (new StaticsCollector())->collectEvents([$missing, $valid]);

        self::assertSame(['\core\event\course_viewed'], $events);
    }

    #[Test]
    public function testCollectEventsSkipsNonIterableEventDeclarations(): void
    {
        // get_moodle_events returns a scalar → not iterable → skipped.
        $scalar = new CoverageConfigurableModule('scalar', ['get_moodle_events' => 7]);
        $valid = new CoverageConfigurableModule('valid', [
            'get_moodle_events' => ['core\event\user_loggedin'],
        ]);

        $events = (new StaticsCollector())->collectEvents([$scalar, $valid]);

        self::assertSame(['\core\event\user_loggedin'], $events);
    }

    #[Test]
    public function testCollectEventsDropsEventNamesThatNormalizeToNull(): void
    {
        // Non-string elements normalize to null and are skipped; the string
        // sibling survives normalization.
        $module = new CoverageConfigurableModule('mixed', [
            'get_moodle_events' => [123, null, 'core\event\user_created'],
        ]);

        $events = (new StaticsCollector())->collectEvents([$module]);

        self::assertSame(['\core\event\user_created'], $events);
    }

    #[Test]
    public function testCollectEventsLogsAndSkipsWhenAModuleThrows(): void
    {
        // get_moodle_events throws → a warning is logged and the module is
        // skipped, while the healthy module still contributes.
        $logger = new RecordingEventsLogger();
        $broken = new CoverageConfigurableModule('broken', [
            'get_moodle_events' => new RuntimeException('events boom'),
        ]);
        $healthy = new CoverageConfigurableModule('healthy', [
            'get_moodle_events' => ['core\event\config_log_created'],
        ]);

        $events = (new StaticsCollector($logger))->collectEvents([$broken, $healthy]);

        self::assertSame(['\core\event\config_log_created'], $events);
        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertStringContainsString('broken', $logger->records[0]['message']);
        self::assertStringContainsString('events boom', $logger->records[0]['message']);
        self::assertArrayHasKey('exception', $logger->records[0]['context']);
        self::assertInstanceOf(RuntimeException::class, $logger->records[0]['context']['exception']);
    }
}

/**
 * Module double whose definition/event methods return a preconfigured value or
 * throw a preconfigured Throwable, dispatched through __call so is_callable()
 * reports the method as present.
 */
final class CoverageConfigurableModule
{
    /**
     * @param array<string, mixed> $returns
     */
    public function __construct(
        private readonly string $name,
        private array $returns,
    ) {}

    public function __call(string $method, array $arguments): mixed
    {
        $value = $this->returns[$method] ?? [];

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

/**
 * Module double exposing only getName(): it has neither the target definition
 * methods nor __call, so [$module, 'get*Definitions'] / [$module,
 * 'get_moodle_events'] is not callable.
 */
final readonly class CoverageNonCallableModule
{
    public function __construct(private string $name) {}

    public function getName(): string
    {
        return $this->name;
    }
}

final readonly class CoverageDefinition
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
}

/**
 * Minimal PSR-3 logger that records every log() call so the collector's
 * per-module warning can be asserted.
 */
final class RecordingEventsLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
