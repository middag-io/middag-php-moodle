<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\ValueObject;

use Middag\Moodle\Kernel\Config\ComponentContext;
use Stringable;

/**
 * Typed reference to a Moodle file area location.
 *
 * Encapsulates the 4-tuple (contextid, component, filearea, itemid) used
 * throughout Moodle's File API to identify a storage location. Eliminates
 * the need to pass 4 separate parameters in file operations.
 *
 * @api
 */
final readonly class FileReference implements Stringable
{
    public function __construct(
        /** Moodle context ID. */
        public int $contextid,
        /** Plugin component (e.g. 'local_example'). */
        public string $component,
        /** File area name (e.g. 'uploads', 'content'). */
        public string $filearea,
        /** Item ID within the area. */
        public int $itemid,
    ) {}

    /**
     * String representation: "contextid:component:filearea:itemid".
     */
    public function __toString(): string
    {
        return sprintf('%d:%s:%s:%d', $this->contextid, $this->component, $this->filearea, $this->itemid);
    }

    /**
     * Whether two file references point to the same location.
     */
    public function equals(self $other): bool
    {
        return $this->contextid === $other->contextid
            && $this->component === $other->component
            && $this->filearea === $other->filearea
            && $this->itemid === $other->itemid;
    }

    /**
     * Create a MIDDAG-scoped file reference.
     *
     * @param int    $contextid Moodle context ID
     * @param string $filearea  File area within the configured component
     * @param int    $itemid    Item ID
     */
    public static function middag(int $contextid, string $filearea, int $itemid): self
    {
        return new self($contextid, ComponentContext::name(), $filearea, $itemid);
    }
}
