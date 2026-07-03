<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use core\component as core_component;
use core\event\base;
use core\plugin_manager as core_plugin_manager;
use Middag\Moodle\Domain\Event\EventDto as event_dto;
use Middag\Moodle\Shared\Util\Environment as environment;
use ReflectionClass;
use Throwable;

/**
 * Utility functions for Moodle events.
 *
 * @internal
 */
class EventSupport
{
    private const CACHE_AREA = 'loader';

    private const CACHE_KEY = 'events';

    /** @var null|event_dto[] Request-level cache for events. */
    private ?array $cachedEvents = null;

    /**
     * Retrieves all Moodle events (core + plugins) converted to DTOs.
     *
     * @return event_dto[] list of event DTOs
     */
    public function getAllEvents(): array
    {
        // Return from request-level cache if available
        if ($this->cachedEvents !== null) {
            return $this->cachedEvents;
        }

        // Bypass persistent cache in development mode
        if (!environment::isDevelopment()) {
            $events = CacheSupport::get(self::CACHE_KEY, self::CACHE_AREA);

            if ($events !== false && is_array($events)) {
                return $this->cachedEvents = $events;
            }
        }

        // Load events from filesystem
        $pluginEvents = $this->loadPluginEvents();
        $coreEvents = $this->loadCoreEvents();

        $events = array_merge($pluginEvents, $coreEvents);

        // Store in persistent cache
        CacheSupport::set(self::CACHE_KEY, $events, self::CACHE_AREA);

        return $this->cachedEvents = $events;
    }

    /**
     * Retrieves events filtered by education level.
     *
     * @param int $level the education level constant
     *
     * @return event_dto[] list of filtered event DTOs
     */
    public function getEventsByLevel(int $level = base::LEVEL_PARTICIPATING): array
    {
        return array_filter(
            $this->getAllEvents(),
            fn (event_dto $e): bool => $e->edulevel === $level
        );
    }

    /**
     * Loads all events defined by plugins.
     *
     * @return event_dto[]
     */
    private function loadPluginEvents(): array
    {
        $core_component = core_component::class;
        $manager = core_plugin_manager::instance();
        $out = [];
        foreach ($core_component::get_plugin_types() as $type => $dir) {
            foreach ($core_component::get_plugin_list($type) as $plugin => $plugindir) {
                $eventDir = $plugindir . '/classes/event';
                $files = $this->scanEventFiles($eventDir);

                foreach ($files as $shortname) {
                    $fqcn = sprintf('\%s_%s\event\%s', $type, $plugin, $shortname);

                    if (!$this->isValidEvent($fqcn)) {
                        continue;
                    }

                    $info = $this->getStaticInfoSafe($fqcn);

                    $out[] = new event_dto(
                        fqcn: $fqcn,
                        displayname: $fqcn::get_name(),
                        edulevel: $info['edulevel'] ?? base::LEVEL_OTHER,
                        pluginname: sprintf('%s_%s', $type, $plugin),
                        plugintype: $type,
                        plugindisplayname: $manager->plugin_name(sprintf('%s_%s', $type, $plugin))
                    );
                }
            }
        }

        return $out;
    }

    /**
     * Loads all events defined by Moodle core.
     *
     * @return event_dto[]
     */
    private function loadCoreEvents(): array
    {
        global $CFG;

        $out = [];
        $eventDir = $CFG->libdir . '/classes/event';
        $files = $this->scanEventFiles($eventDir);

        foreach ($files as $shortname) {
            $fqcn = '\core\event\\' . $shortname;

            if (!$this->isValidEvent($fqcn)) {
                continue;
            }

            $info = $this->getStaticInfoSafe($fqcn);

            if ($info && method_exists($fqcn, 'get_name')) {
                $out[] = new event_dto(
                    fqcn: $fqcn,
                    displayname: $fqcn::get_name(),
                    edulevel: $info['edulevel'] ?? base::LEVEL_OTHER,
                );
            }
        }

        return $out;
    }

    /**
     * Scans a directory for event class files.
     *
     * @param string $dir the directory to scan
     *
     * @return string[] list of event class short names
     */
    private function scanEventFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    fn (string $f): string => substr($f, 0, -4),
                    array_filter(scandir($dir), fn ($f): bool => $f !== '.' && $f !== '..')
                ),
                fn (string $name): bool => str_ends_with($name, '') // always true
            )
        );
    }

    /**
     * Checks if a class is a valid Moodle event.
     *
     * @param string $fqcn fully qualified class name
     *
     * @return bool True if valid, false otherwise
     */
    private function isValidEvent(string $fqcn): bool
    {
        return class_exists($fqcn)
            && is_subclass_of($fqcn, base::class)
            && !$fqcn::is_deprecated()
            && !(new ReflectionClass($fqcn))->isAbstract();
    }

    /**
     * Safely retrieves static information from an event class.
     *
     * @param string $eventname the event class name
     *
     * @return array the static info array
     */
    private function getStaticInfoSafe(string $eventname): array
    {
        try {
            if (!method_exists($eventname, 'get_static_info')) {
                return [];
            }

            return $eventname::get_static_info();
        } catch (Throwable) {
            return [];
        }
    }
}
