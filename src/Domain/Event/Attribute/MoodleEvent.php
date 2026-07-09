<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Event\Attribute;

use Attribute;

/**
 * Declares that a signal class handles a specific Moodle event.
 *
 * When placed on a class implementing AggregateSignalInterface,
 * the non-OSS MIDDAG signal loader automatically registers it on
 * the typed Moodle-event-to-signal bridge during boot — eliminating the need
 * for explicit bridge registration calls in extension boot().
 *
 * The annotated class constructor MUST accept \core\event\base as its
 * first (and only required) argument — the Moodle event instance.
 *
 * Usage:
 *
 *     #[MoodleEvent(\core\event\user_enrolment_created::class)]
 *     final readonly class EnrolmentCreatedSignal implements AggregateSignalInterface
 *     {
 *         public function __construct(public \core\event\base $event) {}
 *
 *         public function getAggregate(): string { return 'enrolment'; }
 *         public function getType(): ?string     { return 'user_enrolment'; }
 *         public function getAction(): Signal    { return Signal::Created; }
 *     }
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MoodleEvent
{
    /**
     * @param string $eventClass FQCN of the Moodle event class (e.g. \core\event\user_enrolment_created::class)
     */
    public function __construct(
        public string $eventClass,
    ) {}
}
