<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Grade;

use Middag\Framework\Shared\Dto\AbstractDto;

/**
 * Grade projection for a user in a course.
 *
 * @api
 */
final class GradeDto extends AbstractDto
{
    public function __construct(
        public int $userid = 0,
        public int $courseid = 0,
        public int $itemid = 0,
        public ?float $finalgrade = null,
        public ?float $rawgrade = null,
        public string $displayValue = '',
        public ?bool $passed = null,
        public ?string $feedback = null,
        public ?int $timemodified = null,
    ) {}

    public function toArray(): array
    {
        return [
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'itemid' => $this->itemid,
            'finalgrade' => $this->finalgrade,
            'rawgrade' => $this->rawgrade,
            'display_value' => $this->displayValue,
            'passed' => $this->passed,
            'feedback' => $this->feedback,
            'timemodified' => $this->timemodified,
        ];
    }
}
