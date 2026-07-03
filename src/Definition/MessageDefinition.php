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
use Middag\Moodle\Domain\Message\MessagePermission as message_permission;

/**
 * Message provider definition for db/messages.php.
 *
 * @api
 */
final readonly class MessageDefinition implements DefinitionInterface
{
    public function __construct(
        public string $name,
        public message_permission $popup = message_permission::PERMITTED,
        public message_permission $email = message_permission::PERMITTED,
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
    ) {}

    public function toMoodleArray(string $plugin_name): array
    {
        return [
            'defaults' => [
                'popup' => $this->popup->toMoodleValue(),
                'email' => $this->email->toMoodleValue(),
            ],
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
