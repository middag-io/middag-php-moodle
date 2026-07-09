<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Platform;

use Middag\Framework\Shared\Dto\AbstractDto;
use Middag\Moodle\Shared\Enum\TextFormat;

/**
 * Site-level information projection (course id=1).
 *
 * Typed projection of Moodle's $SITE global (course id=1).
 *
 * @api
 */
final class SiteInfoDto extends AbstractDto
{
    public function __construct(
        public int $id = 1,
        public string $fullname = '',
        public string $shortname = '',
        public string $summary = '',
        public TextFormat $summaryformat = TextFormat::Html,
        public string $format = '',
        public string $lang = '',
        public string $theme = '',
        public int $timecreated = 0,
        public int $timemodified = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fullname' => $this->fullname,
            'shortname' => $this->shortname,
            'summary' => $this->summary,
            'summaryformat' => $this->summaryformat->value,
            'format' => $this->format,
            'lang' => $this->lang,
            'theme' => $this->theme,
            'timecreated' => $this->timecreated,
            'timemodified' => $this->timemodified,
        ];
    }
}
