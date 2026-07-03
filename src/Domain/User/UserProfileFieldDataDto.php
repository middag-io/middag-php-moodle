<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\User;

use Middag\Framework\Shared\Dto\AbstractDto as abstract_dto;
use stdClass;

/**
 * Value of a user profile field for a specific user (`mdl_user_info_data`).
 *
 * Distinct from `user_profile_field` (definition) — this is the user-scoped
 * value. Kept as DTO (not entity) because it has no lifecycle of its own;
 * it only exists as a value paired to (userid, fieldid).
 *
 * @api
 */
final class UserProfileFieldDataDto extends abstract_dto
{
    public function __construct(
        public ?int $id = null,
        public int $userid = 0,
        public int $fieldid = 0,
        public string $shortname = '',
        public string $data = '',
        public int $dataformat = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->data === '';
    }

    /**
     * @return array<string, null|int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'userid' => $this->userid,
            'fieldid' => $this->fieldid,
            'shortname' => $this->shortname,
            'data' => $this->data,
            'dataformat' => $this->dataformat,
        ];
    }

    public function toObject(): stdClass
    {
        $obj = new stdClass();
        foreach ($this->toArray() as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }
}
