<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Dto;

use Middag\Moodle\Entity\Course as course;

/**
 * Lightweight course projection for API responses and transport.
 *
 * Contains only the most commonly needed course fields, avoiding
 * the full 28-property course entity when only basic info is required.
 *
 * @api
 */
final readonly class CourseDto
{
    public function __construct(
        public int $id,
        public string $fullname,
        public string $shortname,
        public int $category,
        public ?string $idnumber = null,
        public ?string $format = null,
        public int $visible = 1,
        public int $startdate = 0,
        public int $enddate = 0,
    ) {}

    /**
     * Whether the course is currently visible.
     */
    public function is_visible(): bool
    {
        return $this->visible === 1;
    }

    /**
     * Whether the course has an end date.
     */
    public function has_end_date(): bool
    {
        return $this->enddate > 0;
    }

    /**
     * Create from a full course entity.
     */
    public static function fromEntity(course $course): self
    {
        return new self(
            id: $course->getId(),
            fullname: $course->get_fullname(),
            shortname: $course->get_shortname(),
            category: $course->get_category(),
            idnumber: $course->get_idnumber(),
            format: $course->get_format(),
            visible: $course->get_visible(),
            startdate: $course->get_startdate(),
            enddate: $course->get_enddate(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fullname' => $this->fullname,
            'shortname' => $this->shortname,
            'category' => $this->category,
            'idnumber' => $this->idnumber,
            'format' => $this->format,
            'visible' => $this->visible,
            'startdate' => $this->startdate,
            'enddate' => $this->enddate,
        ];
    }
}
