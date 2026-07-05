<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Definition;

use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Domain\Event\EventEdulevel;

/**
 * Typed event definition for Moodle event classes.
 *
 * Extensions declare outbound Moodle events via `get_event_definitions()`.
 * The `build_statics:events` pipeline generates the corresponding event
 * classes in `classes/event/` from these definitions.
 *
 * @api
 */
final readonly class EventDefinition implements DefinitionInterface
{
    /**
     * @param string        $name        event name without extension prefix (e.g. 'order_created')
     * @param string        $crud        CRUD operation: 'c', 'r', 'u', 'd'
     * @param EventEdulevel $edulevel    educational level
     * @param string        $objecttable Moodle DB table associated with the event
     *                                   (empty when the event is not tied to a table);
     *                                   the consumer supplies its own table — the adapter
     *                                   holds no product schema default
     * @param string        $description human-readable description for Moodle log
     * @param null|string   $min_moodle  minimum Moodle version (null = no minimum)
     * @param null|string   $max_moodle  maximum Moodle version (null = no maximum)
     */
    public function __construct(
        public string $name,
        public string $crud = 'c',
        public EventEdulevel $edulevel = EventEdulevel::OTHER,
        public string $objecttable = '',
        public string $description = '',
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
    ) {}

    /**
     * Render as Moodle-compatible array for db/events.php observer registration.
     */
    public function toMoodleArray(string $plugin_name): array
    {
        return [
            'crud' => $this->crud,
            'edulevel' => $this->edulevel->toMoodleValue(),
            'objecttable' => $this->objecttable,
        ];
    }

    /**
     * Get the fully qualified event class name.
     *
     * @param string      $plugin_name frankenstyle plugin name (e.g. 'local_example')
     * @param null|string $extension   extension slug
     */
    public function get_event_classname(string $plugin_name, ?string $extension = null): string
    {
        $prefix = $extension !== null && $extension !== 'core' ? $extension . '_' : '';

        return '\\' . str_replace('_', '\\', $plugin_name) . '\event\\' . $prefix . $this->name;
    }

    public function isCompatible(string $moodle_version): bool
    {
        if ($this->min_moodle !== null && version_compare($moodle_version, $this->min_moodle, '<')) {
            return false;
        }

        if ($this->max_moodle !== null && version_compare($moodle_version, $this->max_moodle, '>')) {
            return false;
        }

        return true;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
