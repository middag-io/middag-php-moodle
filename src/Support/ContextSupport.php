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

use core\context;
use core\context\block as context_block;
use core\context\course as context_course;
use core\context\coursecat as context_coursecat;
use core\context\module as context_module;
use core\context\system as context_system;
use core\context\user as context_user;

/**
 * Utility to handle Moodle context classes across versions.
 *
 * Since Moodle 4.2 there are namespaced classes `core\context\*`.
 * In 4.1 these classes do not exist and only legacy contexts `context_*`
 * are available. Also, cross-version aliases are managed by Moodle core —
 * we must not create them here.
 *
 * This helper:
 * - Prioritises namespaced classes when present (4.2+);
 * - Safely falls back to legacy classes on 4.1;
 * - Exposes utility methods to obtain context instances;
 * - Avoids direct type hints for classes that may not exist in 4.1.
 *
 * @api
 */
class ContextSupport
{
    /**
     * Retrieves the system context.
     *
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return context_system System context instance
     */
    public static function system(int $strictness = MUST_EXIST): context_system
    {
        /** @var context_system $class */
        $class = self::classFor('system');

        return $class::instance(0, $strictness);
    }

    /**
     * Retrieves the course context for a given course ID.
     *
     * @param int $courseid   Course ID
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return context_course Course context instance
     */
    public static function course(int $courseid, int $strictness = MUST_EXIST): context_course
    {
        /** @var context_course $class */
        $class = self::classFor('course');

        return $class::instance($courseid, $strictness);
    }

    /**
     * Retrieves the course category context for a given category ID.
     *
     * @param int $categoryid Category ID
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return context_coursecat Category context instance
     */
    public static function coursecat(int $categoryid, int $strictness = MUST_EXIST): context_coursecat
    {
        /** @var context_coursecat $class */
        $class = self::classFor('coursecat');

        return $class::instance($categoryid, $strictness);
    }

    /**
     * Retrieves the course module context for a given module ID.
     *
     * @param int $cmid       Course Module ID (cmid)
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return context_module Module context instance
     */
    public static function module(int $cmid, int $strictness = MUST_EXIST): context_module
    {
        /** @var context_module $class */
        $class = self::classFor('module');

        return $class::instance($cmid, $strictness);
    }

    /**
     * Retrieves the user context for a given user ID.
     *
     * @param int $userid     User ID
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return context_user User context instance
     */
    public static function user(int $userid, int $strictness = MUST_EXIST): context_user
    {
        /** @var context_user $class */
        $class = self::classFor('user');

        return $class::instance($userid, $strictness);
    }

    /**
     * Retrieves the block context for a given block instance ID.
     *
     * @param int $blockid    Block instance ID
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return context_block Block context instance
     */
    public static function block(int $blockid, int $strictness = MUST_EXIST): context_block
    {
        /** @var context_block $class */
        $class = self::classFor('block');

        return $class::instance($blockid, $strictness);
    }

    /**
     * Retrieves a context instance by its ID.
     *
     * In Moodle 4.2+ the canonical entry point is \core\context\base::instance_by_id().
     * In 4.1 the legacy global \context::instance_by_id() must be used instead.
     *
     * This helper hides that difference so callers do not need to depend
     * directly on the global `context` class.
     *
     * @param int $id         Context ID
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return object The resolved context instance
     */
    public static function instanceById(int $id, int $strictness = MUST_EXIST): object
    {
        // Moodle 4.2+: prefer the namespaced base context API when available.
        if (VersionSupport::symbolExists('core\context\base')) {
            /** @var class-string $base */
            $base = '\core\context\base';

            return $base::instance_by_id($id, $strictness);
        }

        // Moodle 4.1 legacy fallback: global context class.
        return context::instance_by_id($id, $strictness);
    }

    /**
     * Returns the correct FQCN for the requested context type.
     *
     * Prioritises namespaced classes (4.2+) and falls back to legacy ones (4.1).
     *
     * @param string $type One of: system, course, module, coursecat, user, block
     *
     * @return class-string Fully-qualified context class name
     */
    private static function classFor(string $type): string
    {
        $map = [
            'system' => ['\core\context\system', 'context_system'],
            'course' => ['\core\context\course', 'context_course'],
            'module' => ['\core\context\module', 'context_module'],
            'coursecat' => ['\core\context\coursecat', 'context_coursecat'],
            'user' => ['\core\context\user', 'context_user'],
            'block' => ['\core\context\block', 'context_block'],
        ];

        if (!isset($map[$type])) {
            // Conservative fallback
            return 'context_system';
        }

        [$new, $legacy] = $map[$type];

        // If the namespaced class exists (4.2+), use it; otherwise use legacy
        if (class_exists($new)) {
            return $new;
        }

        return $legacy;
    }
}
