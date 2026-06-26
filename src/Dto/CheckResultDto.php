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

use Middag\Framework\Shared\Dto\AbstractDto as abstract_dto;
use Middag\Moodle\Enum\CheckResultStatus as check_result_status;

/**
 * System check result projection.
 *
 * @api
 */
final class CheckResultDto extends abstract_dto
{
    public function __construct(
        public string $checkId = '',
        public check_result_status $status = check_result_status::UNKNOWN,
        public string $summary = '',
        public ?string $details = null,
        public ?int $timecreated = null,
    ) {}

    public function isHealthy(): bool
    {
        return $this->status->isHealthy();
    }

    public function toArray(): array
    {
        return [
            'check_id' => $this->checkId,
            'status' => $this->status->value,
            'summary' => $this->summary,
            'details' => $this->details,
            'timecreated' => $this->timecreated,
        ];
    }
}
