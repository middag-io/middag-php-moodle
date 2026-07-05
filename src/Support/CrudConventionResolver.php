<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use ReflectionClass;
use ReflectionProperty;

/**
 * Resolves CRUD conventions for an entity class.
 *
 * Given an entity FQCN, discovers form class, columns, slug, title,
 * and other conventions needed by the CrudBuilder. Moodle-specific: bakes in
 * Moodle column conventions, plugin form-class layout and capability strings,
 * so it lives in the Moodle adapter rather than the platform-agnostic UI lib.
 *
 * @internal
 */
final class CrudConventionResolver
{
    /**
     * Resolve the entity slug (pluralized lowercase basename).
     */
    public static function slug(string $entity_class): string
    {
        $parts = explode('\\', $entity_class);
        $basename = end($parts);

        return strtolower($basename) . 's';
    }

    /**
     * Resolve the display title (ucfirst pluralized).
     */
    public static function title(string $entity_class): string
    {
        return ucfirst(self::slug($entity_class));
    }

    /**
     * Resolve the singular display name.
     */
    public static function singular(string $entity_class): string
    {
        $parts = explode('\\', $entity_class);

        return ucfirst(strtolower(end($parts)));
    }

    /**
     * Discover columns from entity public properties.
     *
     * Excludes id, timecreated, timemodified, usermodified by convention.
     *
     * @return string[]
     */
    public static function columns(string $entity_class): array
    {
        if (!class_exists($entity_class)) {
            return [];
        }

        $ref = new ReflectionClass($entity_class);
        $hidden = ['id', 'timecreated', 'timemodified', 'usermodified'];
        $columns = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!in_array($prop->getName(), $hidden, true)) {
                $columns[] = $prop->getName();
            }
        }

        return $columns;
    }

    /**
     * Discover the form class by namespace convention.
     *
     * Convention: {Extension}\forms\{EntityBasename}_form
     *
     * @return null|string FQCN if found, null otherwise
     */
    public static function formClass(string $entity_class): ?string
    {
        $parts = explode('\\', $entity_class);
        $basename = end($parts);

        $ext_index = array_search('extensions', $parts, true);
        if ($ext_index === false) {
            return null;
        }

        $ext_ns = implode('\\', array_slice($parts, 0, $ext_index + 2));
        $candidate = $ext_ns . '\forms\\' . $basename . '_form';

        return class_exists($candidate) ? $candidate : null;
    }

    /**
     * Resolve capability name by convention.
     *
     * Convention: local/middag:manage_{singular}
     */
    public static function capability(string $entity_class): string
    {
        return 'local/middag:manage_' . rtrim(self::slug($entity_class), 's');
    }

    /**
     * Resolve route prefix by convention.
     */
    public static function routePrefix(string $entity_class): string
    {
        return self::slug($entity_class);
    }
}
