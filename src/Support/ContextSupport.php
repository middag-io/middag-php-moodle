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
 * Utility to obtain Moodle context instances.
 *
 * The supported Moodle matrix (4.5→5.2) always ships the namespaced
 * `core\context\*` classes (introduced in 4.2), so this helper targets them
 * directly — there is no pre-4.2 `context_*` fallback. Cross-version aliases
 * are managed by Moodle core; we must not create them here.
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
        return context_system::instance(0, $strictness);
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
        return context_course::instance($courseid, $strictness);
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
        return context_coursecat::instance($categoryid, $strictness);
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
        return context_module::instance($cmid, $strictness);
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
        return context_user::instance($userid, $strictness);
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
        return context_block::instance($blockid, $strictness);
    }

    /**
     * Retrieves a context instance by its ID.
     *
     * Thin wrapper over the namespaced `core\context::instance_by_id()` so
     * callers stay off the host class.
     *
     * @param int $id         Context ID
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return context The resolved context instance
     */
    public static function instanceById(int $id, int $strictness = MUST_EXIST): context
    {
        return context::instance_by_id($id, $strictness);
    }
}
