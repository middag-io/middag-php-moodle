<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\CustomField;

/**
 * Typed custom field value — wraps Moodle's customfield_data with field metadata.
 *
 * Replaces raw arrays returned by custom_field_support with a typed, queryable object.
 * Each instance represents one field's value for a specific entity instance.
 *
 * @api
 */
final readonly class CustomFieldValueDto
{
    public function __construct(
        /** Field shortname (unique identifier within category). */
        public string $shortname,
        /** The actual value (string representation — Moodle stores all as text). */
        public ?string $value,
        /** Field type (text, textarea, select, date, checkbox, etc.). */
        public string $type,
        /** Human-readable field name. */
        public string $name,
        /** Whether this field is required. */
        public bool $required = false,
        /** Field category name (optional). */
        public ?string $category = null,
    ) {}

    /**
     * Whether this field has a value set.
     */
    public function hasValue(): bool
    {
        return $this->value !== null && $this->value !== '';
    }

    /**
     * Get value as integer (for numeric/date fields).
     */
    public function intValue(): int
    {
        return (int) ($this->value ?? 0);
    }

    /**
     * Get value as boolean (for checkbox fields).
     */
    public function boolValue(): bool
    {
        return (bool) ($this->value ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'shortname' => $this->shortname,
            'value' => $this->value,
            'type' => $this->type,
            'name' => $this->name,
            'required' => $this->required,
            'category' => $this->category,
        ];
    }
}
