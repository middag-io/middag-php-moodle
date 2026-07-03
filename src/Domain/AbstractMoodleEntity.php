<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain;

use BadMethodCallException;
use Middag\Framework\Persistence\Contract\EntityInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;

/**
 * Base class for Moodle native entities.
 *
 * Implements the framework {@see EntityInterface} (OSS) directly so the OSS
 * host-entity base remains free of any non-OSS MIDDAG dependency. The four
 * magic members (__get/__isset/__set/jsonSerialize) that previously came from
 * a non-OSS MIDDAG base are defined here, making this base self-sufficient on
 * the OSS contract.
 *
 * @api
 */
abstract class AbstractMoodleEntity implements EntityInterface
{
    protected int $id = 0;

    protected int $timecreated = 0;

    protected int $timemodified = 0;

    /**
     * Magic getter to allow reading protected properties.
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        return null;
    }

    /**
     * Magic isset to allow checking protected properties.
     *
     * @param string $name Property name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name) && $this->{$name} !== null;
    }

    /**
     * Magic setter.
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    public function __set(string $name, mixed $value): void
    {
        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        }
    }

    /**
     * Magic accessor to support get_* and with_* helpers for entity properties.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (str_starts_with($name, 'get_')) {
            $property = substr($name, 4);

            if (!property_exists($this, $property)) {
                throw new BadMethodCallException(sprintf('Property %s does not exist on ', $property) . static::class);
            }

            $reflection = new ReflectionProperty($this, $property);

            return $reflection->getValue($this);
        }

        if (str_starts_with($name, 'with_')) {
            $property = substr($name, 5);

            if (!property_exists($this, $property)) {
                throw new BadMethodCallException(sprintf('Property %s does not exist on ', $property) . static::class);
            }

            $value = $arguments[0] ?? null;
            self::writeProperty($this, $property, self::castValue($this, $property, $value));

            return $this;
        }

        throw new BadMethodCallException(sprintf('Method %s not supported in ', $name) . static::class);
    }

    /**
     * Serializes the object to a value that can be natively serialized by json_encode().
     *
     * @return array<string, mixed>
     *
     * @noinspection PhpMethodNamingConventionInspection
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the Moodle database table name.
     *
     * @return string
     */
    abstract public static function getTable(): string;

    /**
     * Factory method to create an entity from a Moodle record.
     *
     * Automatically casts values to match property types (int, string, etc.)
     * since Moodle's database layer often returns numeric values as strings.
     *
     * @param array|stdClass $record
     *
     * @return static
     */
    public static function fromRecord(array|stdClass $record): static
    {
        $entity = new static();
        $data = (object) $record;

        foreach (get_object_vars($data) as $property => $value) {
            if (property_exists($entity, $property)) {
                self::writeProperty(
                    $entity,
                    $property,
                    self::castValue($entity, $property, $value)
                );
            }
        }

        return $entity;
    }

    /**
     * Converts the entity to a stdClass record for Moodle APIs.
     *
     * @return stdClass
     */
    public function toRecord(): stdClass
    {
        $record = new stdClass();

        foreach ($this->getAllProperties($this) as $property) {
            $record->{$property->getName()} = $property->getValue($this);
        }

        return $record;
    }

    /**
     * Returns the entity as stdClass (alias for to_record).
     *
     * @return stdClass
     */
    public function asStdClass(): stdClass
    {
        return $this->toRecord();
    }

    /**
     * Get the entity unique identifier.
     *
     * @return null|int
     */
    public function getId(): ?int
    {
        return $this->id > 0 ? $this->id : null;
    }

    /**
     * Set entity identifier.
     *
     * @param null|int $id
     *
     * @return $this
     */
    public function withId(?int $id): self
    {
        $this->id = $id ?? 0;

        return $this;
    }

    /**
     * Get entity creation timestamp.
     *
     * @return int
     */
    public function getTimecreated(): int
    {
        return $this->timecreated;
    }

    /**
     * Set entity creation timestamp.
     *
     * @param int $timecreated
     *
     * @return $this
     */
    public function withTimecreated(int $timecreated): self
    {
        $this->timecreated = $timecreated;

        return $this;
    }

    /**
     * Get entity modification timestamp.
     *
     * @return int
     */
    public function getTimemodified(): int
    {
        return $this->timemodified;
    }

    /**
     * Set entity modification timestamp.
     *
     * @param int $timemodified
     *
     * @return $this
     */
    public function withTimemodified(int $timemodified): self
    {
        $this->timemodified = $timemodified;

        return $this;
    }

    /**
     * Implementation for entity_interface.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return (array) $this->toRecord();
    }

    /**
     * Cast a value to match the property type.
     *
     * @param object $entity
     * @param string $property
     * @param mixed  $value
     *
     * @return mixed
     */
    private static function castValue(object $entity, string $property, mixed $value): mixed
    {
        if ($value === null) {
            return $value;
        }

        $reflection = new ReflectionProperty($entity, $property);

        $type = $reflection->getType();

        if ($type === null) {
            return $value;
        }

        $allows_null = $type->allowsNull();
        $type_name = null;

        if ($type instanceof ReflectionNamedType) {
            $type_name = $type->getName();
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $union_type) {
                if ($union_type instanceof ReflectionNamedType && $union_type->getName() !== 'null') {
                    $type_name = $union_type->getName();

                    break;
                }
            }
        }

        if ($type_name === null) {
            return $value;
        }

        if ($value === null && $allows_null) {
            return null;
        }

        return match ($type_name) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            default => $value,
        };
    }

    /**
     * Write a property value regardless of visibility.
     *
     * @param object $entity
     * @param string $property
     * @param mixed  $value
     */
    private static function writeProperty(object $entity, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($entity, $property);
        $reflection->setValue($entity, $value);
    }

    /**
     * Get all instance properties (including private and inherited).
     *
     * @param object $entity
     *
     * @return array<string, ReflectionProperty>
     */
    private function getAllProperties(object $entity): array
    {
        $properties = [];
        $class = new ReflectionClass($entity);

        while ($class !== false) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $name = $property->getName();
                if (!array_key_exists($name, $properties)) {
                    $properties[$name] = $property;
                }
            }

            $class = $class->getParentClass();
        }

        return $properties;
    }
}
