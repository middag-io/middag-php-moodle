<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Statics;

use Middag\Framework\Kernel\Contract\ModuleInterface;
use Middag\Moodle\Definition\DefinitionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Aggregates Moodle db/*.php definitions from module instances.
 *
 * Walks a list of `ModuleInterface` instances and calls the appropriate
 * `get*Definitions()` method per target. Returns flat arrays of
 * `DefinitionInterface` instances (except for `access`, which returns
 * `{definition, extension}` pairs because capability names are qualified by
 * the owning module slug).
 *
 * The per-target definition methods and `get_moodle_events()` are not part of
 * the minimal `ModuleInterface`; they are resolved dynamically and guarded so
 * the collector tolerates modules that do not expose them.
 *
 * Side-effect free aside from optional logger calls on per-extension errors.
 *
 * @api
 */
final readonly class StaticsCollector
{
    /**
     * Map of target name → extension method that returns its definitions.
     *
     * @var array<string, string>
     */
    public const TARGET_METHOD_MAP = [
        'caches' => 'getCacheDefinitions',
        'access' => 'getCapabilities',
        'services' => 'getServiceDefinitions',
        'messages' => 'getMessageDefinitions',
        'hooks' => 'getHookDefinitions',
        'event_definitions' => 'getEventDefinitions',
        'fileareas' => 'getFileAreaDefinitions',
        'web_services' => 'getWebServiceDefinitions',
    ];

    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Collect definitions for the given target across all modules.
     *
     * @param ModuleInterface[] $extensions
     *
     * @return list<array{definition: DefinitionInterface, extension: string}|DefinitionInterface>
     */
    public function collect(string $target, array $extensions): array
    {
        $method = self::TARGET_METHOD_MAP[$target] ?? null;
        if ($method === null) {
            return [];
        }

        $all = [];

        foreach ($extensions as $extension) {
            $callback = [$extension, $method];
            if (!is_callable($callback)) {
                continue;
            }

            try {
                $definitions = $callback();
            } catch (Throwable $throwable) {
                $this->logger->warning(sprintf(
                    'Error collecting %s from extension "%s": %s',
                    $target,
                    $extension->getName(),
                    $throwable->getMessage(),
                ), ['exception' => $throwable]);

                continue;
            }

            if (!is_iterable($definitions)) {
                continue;
            }

            foreach ($definitions as $definition) {
                $all[] = $target === 'access' ? ['definition' => $definition, 'extension' => $extension->getName()] : $definition;
            }
        }

        return $all;
    }

    /**
     * Filter definitions by Moodle version compatibility.
     *
     * @param list<array{definition: DefinitionInterface, extension: string}|DefinitionInterface> $definitions
     *
     * @return list<array{definition: DefinitionInterface, extension: string}|DefinitionInterface>
     */
    public function filterCompatible(array $definitions, string $moodleVersion): array
    {
        return array_values(array_filter(
            $definitions,
            static function (mixed $item) use ($moodleVersion): bool {
                $def = is_array($item) ? $item['definition'] : $item;

                return $def->isCompatible($moodleVersion);
            },
        ));
    }

    /**
     * Collect explicit Moodle event names registered by modules.
     *
     * @param ModuleInterface[] $extensions
     *
     * @return list<string> sorted, deduplicated, normalized event class names
     */
    public function collectEvents(array $extensions): array
    {
        $events = [];

        foreach ($extensions as $extension) {
            $callback = [$extension, 'get_moodle_events'];
            if (!is_callable($callback)) {
                continue;
            }

            try {
                $declared = $callback();
            } catch (Throwable $throwable) {
                $this->logger->warning(sprintf(
                    'Error collecting events from extension "%s": %s',
                    $extension->getName(),
                    $throwable->getMessage(),
                ), ['exception' => $throwable]);

                continue;
            }

            if (!is_iterable($declared)) {
                continue;
            }

            foreach ($declared as $event) {
                $normalized = self::normalizeEventName($event);
                if ($normalized === null) {
                    continue;
                }
                if ($normalized === '*') {
                    continue;
                }
                $events[$normalized] = true;
            }
        }

        $list = array_keys($events);
        sort($list);

        return $list;
    }

    /**
     * Normalize a Moodle event class name to the canonical observer format.
     *
     * Slashes become backslashes, leading backslashes are normalized to
     * exactly one. The catch-all `*` is preserved.
     */
    public static function normalizeEventName(mixed $event): ?string
    {
        if (!is_string($event)) {
            return null;
        }

        $event = trim($event);
        if ($event === '') {
            return null;
        }

        if ($event === '*') {
            return $event;
        }

        $event = str_replace('/', '\\', $event);
        $event = ltrim($event, '\\');

        return '\\' . $event;
    }
}
