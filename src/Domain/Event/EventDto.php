<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Event;

use Middag\Framework\Shared\Dto\AbstractDto as abstract_dto;

/**
 * Immutable DTO representing a Moodle event class for listing/filtering.
 *
 * Hydrated by event_support when scanning plugin and core events.
 *
 * @api
 */
final class EventDto extends abstract_dto
{
    public function __construct(
        public string $fqcn,
        public string $displayname,
        public int $edulevel = 0,
        public string $pluginname = 'core',
        public string $plugintype = 'core',
        public string $plugindisplayname = '',
    ) {}

    public function toArray(): array
    {
        return [
            'fqcn' => $this->fqcn,
            'displayname' => $this->displayname,
            'edulevel' => $this->edulevel,
            'pluginname' => $this->pluginname,
            'plugintype' => $this->plugintype,
            'plugindisplayname' => $this->plugindisplayname,
        ];
    }
}
