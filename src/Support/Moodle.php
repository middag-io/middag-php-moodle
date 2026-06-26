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

/**
 * Static aggregator for all Moodle support wrappers.
 *
 * Each method returns a fresh instance of the corresponding Support class.
 * Use this as the canonical entrypoint for Moodle platform APIs:
 *
 *   Moodle::db()->get_record(...)
 *   Moodle::user()->get_user($id)
 *   Moodle::config()->get('middag_key')
 *
 * Plugin shim `{component}\facade\moodle` aliases this class.
 *
 * @api
 */
final class Moodle
{
    public static function auth(): AuthSupport
    {
        return new AuthSupport();
    }

    public static function cache(): CacheSupport
    {
        return new CacheSupport();
    }

    public static function calendar(): CalendarSupport
    {
        return new CalendarSupport();
    }

    public static function capability(): CapabilitySupport
    {
        return new CapabilitySupport();
    }

    public static function category(): CategorySupport
    {
        return new CategorySupport();
    }

    public static function check(): CheckSupport
    {
        return new CheckSupport();
    }

    public static function cohort(): CohortSupport
    {
        return new CohortSupport();
    }

    public static function competency(): CompetencySupport
    {
        return new CompetencySupport();
    }

    public static function completion(): CompletionSupport
    {
        return new CompletionSupport();
    }

    public static function config(): ConfigSupport
    {
        return new ConfigSupport();
    }

    public static function context(): ContextSupport
    {
        return new ContextSupport();
    }

    public static function course(): CourseSupport
    {
        return new CourseSupport();
    }

    public static function customField(): CustomFieldSupport
    {
        return new CustomFieldSupport();
    }

    public static function db(): DbSupport
    {
        return new DbSupport();
    }

    public static function diBridge(): DiBridgeSupport
    {
        return new DiBridgeSupport();
    }

    public static function enrol(): EnrolSupport
    {
        return new EnrolSupport();
    }

    public static function event(): EventSupport
    {
        return new EventSupport();
    }

    public static function file(): FileSupport
    {
        return new FileSupport();
    }

    public static function grade(): GradeSupport
    {
        return new GradeSupport();
    }

    public static function group(): GroupSupport
    {
        return new GroupSupport();
    }

    public static function htmlWriter(): HtmlWriterSupport
    {
        return new HtmlWriterSupport();
    }

    public static function lang(): LangSupport
    {
        return new LangSupport();
    }

    public static function lock(): LockSupport
    {
        return new LockSupport();
    }

    public static function message(): MessageSupport
    {
        return new MessageSupport();
    }

    public static function notification(): NotificationSupport
    {
        return new NotificationSupport();
    }

    public static function output(): OutputSupport
    {
        return new OutputSupport();
    }

    public static function page(): PageSupport
    {
        return new PageSupport();
    }

    public static function plugin(): PluginSupport
    {
        return new PluginSupport();
    }

    public static function preference(): PreferenceSupport
    {
        return new PreferenceSupport();
    }

    public static function request(): RequestSupport
    {
        return new RequestSupport();
    }

    public static function role(): RoleSupport
    {
        return new RoleSupport();
    }

    public static function routerBridge(): RouterBridgeSupport
    {
        return new RouterBridgeSupport();
    }

    public static function session(): SessionSupport
    {
        return new SessionSupport();
    }

    public static function settings(): SettingsSupport
    {
        return new SettingsSupport();
    }

    public static function task(): TaskSupport
    {
        return new TaskSupport();
    }

    public static function theme(): ThemeSupport
    {
        return new ThemeSupport();
    }

    public static function time(): TimeSupport
    {
        return new TimeSupport();
    }

    public static function url(): UrlSupport
    {
        return new UrlSupport();
    }

    public static function user(): UserSupport
    {
        return new UserSupport();
    }

    public static function userField(): UserFieldSupport
    {
        return new UserFieldSupport();
    }

    public static function version(): VersionSupport
    {
        return new VersionSupport();
    }
}
