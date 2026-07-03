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
use Middag\Moodle\Domain\Platform\CheckType as check_type;

/**
 * Check definition for Moodle's Check API.
 *
 * Extensions declare checks via typed metadata. Checks are collected at
 * runtime for lib.php callbacks and are not generated into db/ files.
 *
 * @api
 */
final readonly class CheckDefinition implements DefinitionInterface
{
    /**
     * @param string      $name       check identifier (e.g. 'eav_integrity')
     * @param string      $classname  FQCN of the check class extending \core\check\check
     * @param check_type  $type       check category
     * @param null|string $min_moodle minimum Moodle version (null = no minimum)
     * @param null|string $max_moodle maximum Moodle version (null = no maximum)
     */
    public function __construct(
        public string $name,
        public string $classname,
        public check_type $type = check_type::STATUS,
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
    ) {}

    public function toMoodleArray(string $plugin_name): array
    {
        return [
            'classname' => $this->classname,
            'type' => $this->type->toMoodleValue(),
        ];
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
