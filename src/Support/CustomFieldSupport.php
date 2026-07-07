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

use core_customfield\handler;
use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * Utility wrapper for Moodle's Custom Fields API (core_customfield).
 *
 * Provides a stable, static interface for reading and writing custom field
 * data, converting Moodle handler/controller objects into plain arrays and
 * primitives so that extensions never depend on core_customfield types.
 *
 * @internal
 */
class CustomFieldSupport
{
    /**
     * Retrieves all custom field values for a given instance as an associative array.
     *
     * @param string $component  the component name (e.g. 'core_course')
     * @param string $area       the area name (e.g. 'course')
     * @param int    $instanceid the instance ID (e.g. course id)
     *
     * @return array<string, mixed> associative array [shortname => value], empty on error
     */
    public static function getFieldValues(string $component, string $area, int $instanceid): array
    {
        try {
            $handler = handler::get_handler($component, $area);
            $data = $handler->export_instance_data_object($instanceid);

            return (array) $data;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Retrieves a single custom field value by shortname.
     *
     * @param string $component  the component name (e.g. 'core_course')
     * @param string $area       the area name (e.g. 'course')
     * @param int    $instanceid the instance ID
     * @param string $shortname  the field shortname
     *
     * @return mixed the field value, or null if not found
     */
    public static function getFieldValue(string $component, string $area, int $instanceid, string $shortname): mixed
    {
        $values = self::getFieldValues($component, $area, $instanceid);

        return $values[$shortname] ?? null;
    }

    /**
     * Retrieves custom field values for multiple instances in bulk.
     *
     * @param string $component   the component name (e.g. 'core_course')
     * @param string $area        the area name (e.g. 'course')
     * @param int[]  $instanceids the instance IDs
     *
     * @return array<int, array<string, mixed>> array indexed by instance ID [instanceid => [shortname => value, ...]]
     */
    public static function getFieldValuesBulk(string $component, string $area, array $instanceids): array
    {
        try {
            $handler = handler::get_handler($component, $area);
            $alldata = $handler->get_instances_data($instanceids);
            $result = [];

            foreach ($alldata as $instanceid => $controllers) {
                $values = [];
                foreach ($controllers as $controller) {
                    $field = $controller->get_field();
                    $shortname = $field->get('shortname');
                    $values[$shortname] = $controller->export_value();
                }
                $result[$instanceid] = $values;
            }

            return $result;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Lists all defined custom fields for a component and area.
     *
     * @param string $component the component name (e.g. 'core_course')
     * @param string $area      the area name (e.g. 'course')
     *
     * @return array<int, array{shortname: string, name: string, type: string, configdata: string}> list of field definitions
     */
    public static function getFieldDefinitions(string $component, string $area): array
    {
        try {
            $handler = handler::get_handler($component, $area);
            $fields = $handler->get_fields();
            $definitions = [];

            foreach ($fields as $field) {
                $definitions[] = [
                    'shortname' => $field->get('shortname'),
                    'name' => $field->get('name'),
                    'type' => $field->get('type'),
                    'configdata' => $field->get('configdata'),
                ];
            }

            return $definitions;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Saves custom field values for an instance.
     *
     * @param string               $component  the component name (e.g. 'core_course')
     * @param string               $area       the area name (e.g. 'course')
     * @param int                  $instanceid the instance ID
     * @param array<string, mixed> $data       associative array [shortname => value]
     *
     * @return bool true on success, false on failure
     */
    public static function saveFieldData(string $component, string $area, int $instanceid, array $data): bool
    {
        try {
            $handler = handler::get_handler($component, $area);
            $controllers = $handler->get_instance_data($instanceid);

            foreach ($controllers as $controller) {
                $field = $controller->get_field();
                $shortname = $field->get('shortname');

                if (array_key_exists($shortname, $data)) {
                    $controller->set($controller->datafield(), $data[$shortname]);
                    $controller->set('value', $data[$shortname]);
                    $controller->save();
                }
            }

            return true;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Deletes all custom field data for an instance.
     *
     * @param string $component  the component name (e.g. 'core_course')
     * @param string $area       the area name (e.g. 'course')
     * @param int    $instanceid the instance ID
     *
     * @return bool true on success, false on failure
     */
    public static function deleteInstanceData(string $component, string $area, int $instanceid): bool
    {
        try {
            $handler = handler::get_handler($component, $area);
            $handler->delete_instance($instanceid);

            return true;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }
}
