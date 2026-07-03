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

use core\exception\moodle_exception;
use core\url as moodle_url;
use Exception;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Shared\Util\Debug as debug;

/**
 * Utility functions for Moodle URLs and redirection.
 *
 * Provides a centralized interface for creating Moodle URLs with proper
 * error handling and consistent parameter management.
 *
 * @internal
 */
class UrlSupport
{
    /**
     * Safely creates a Moodle URL object.
     *
     * @param moodle_url|string $url        URL path or moodle_url object
     * @param null|array        $params     optional query parameters
     * @param null|string       $anchor     optional anchor fragment
     * @param int               $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return moodle_url generated URL object
     *
     * @throws moodle_exception If strictness is MUST_EXIST and URL creation fails
     */
    public static function get(
        moodle_url|string $url,
        ?array $params = null,
        ?string $anchor = null,
        int $strictness = IGNORE_MISSING
    ): moodle_url {
        // Normalize double slashes
        if (is_string($url)) {
            $url = preg_replace('#/+#', '/', $url);
        }

        try {
            return new moodle_url($url, $params ?? [], $anchor);
        } catch (moodle_exception $moodleexception) {
            // If MUST_EXIST, re-throw the exception
            if ($strictness === MUST_EXIST) {
                throw $moodleexception;
            }

            // Log the error for debugging
            debug::traceException($moodleexception);
        }

        // Fallback to home page if IGNORE_MISSING
        return self::home();
    }

    /**
     * Retrieves a course view URL.
     *
     * @param int         $courseid Course ID
     * @param array       $params   Additional query parameters
     * @param null|string $anchor   optional anchor fragment
     *
     * @return moodle_url Course URL
     */
    public static function course(int $courseid, array $params = [], ?string $anchor = null): moodle_url
    {
        $path = '/course/view.php';
        $urlparams = $params + ['id' => $courseid];

        return self::get($path, $urlparams, $anchor);
    }

    /**
     * Retrieves a course module (activity) URL.
     *
     * @param int         $cmid   Course Module ID (cmid)
     * @param array       $params Additional query parameters
     * @param null|string $anchor optional anchor fragment
     *
     * @return moodle_url Module URL
     */
    public static function module(int $cmid, array $params = [], ?string $anchor = null): moodle_url
    {
        $path = '/mod/view.php';
        $urlparams = $params + ['id' => $cmid];

        return self::get($path, $urlparams, $anchor);
    }

    /**
     * Retrieves a user profile URL.
     *
     * @param int         $userid   User ID
     * @param null|int    $courseid Optional course context ID
     * @param array       $params   Additional query parameters
     * @param null|string $anchor   optional anchor fragment
     *
     * @return moodle_url user profile URL
     */
    public static function userProfile(
        int $userid,
        ?int $courseid = null,
        array $params = [],
        ?string $anchor = null
    ): moodle_url {
        $path = '/user/profile.php';
        $urlparams = $params + ['id' => $userid];

        if ($courseid !== null) {
            $urlparams['course'] = $courseid;
        }

        return self::get($path, $urlparams, $anchor);
    }

    /**
     * Retrieves a pluginfile URL for serving files.
     *
     * This is the proper way to generate URLs for files stored in Moodle's file storage.
     *
     * @param int         $contextid     Context ID
     * @param string      $component     Component name (e.g., 'local_example')
     * @param string      $filearea      File area name
     * @param int         $itemid        Item ID
     * @param string      $filepath      File path (e.g., '/')
     * @param string      $filename      File name
     * @param bool        $forcedownload Force download instead of display
     * @param null|string $preview       Preview mode ('thumb', 'tinyicon', 'bigicon')
     *
     * @return moodle_url pluginfile URL
     */
    public static function pluginfile(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        bool $forcedownload = false,
        ?string $preview = null
    ): moodle_url {
        $path = sprintf('/pluginfile.php/%d/%s/%s/%d%s%s', $contextid, $component, $filearea, $itemid, $filepath, $filename);

        $params = [];

        if ($forcedownload) {
            $params['forcedownload'] = 1;
        }

        if ($preview !== null) {
            $params['preview'] = $preview;
        }

        return self::get($path, $params);
    }

    /**
     * Retrieves a component image URL from the theme.
     *
     * @param string $imagename Image file name (without extension)
     * @param string $component Component name
     *
     * @return moodle_url image URL
     *
     * @throws Exception if image retrieval fails
     */
    public static function imageUrl(string $imagename, ?string $component = null): moodle_url
    {
        global $OUTPUT;

        try {
            return $OUTPUT->image_url($imagename, $component ?? ComponentContext::name());
        } catch (Exception $exception) {
            debug::traceException($exception);

            throw $exception;
        }
    }

    /**
     * Retrieves a Moodle admin settings page URL.
     *
     * @param string      $section Settings section name
     * @param array       $params  Additional query parameters
     * @param null|string $anchor  optional anchor fragment
     *
     * @return moodle_url admin settings URL
     */
    public static function adminSettings(string $section, array $params = [], ?string $anchor = null): moodle_url
    {
        $path = '/admin/settings.php';
        $urlparams = $params + ['section' => $section];

        return self::get($path, $urlparams, $anchor);
    }

    /**
     * Retrieves the site home page URL.
     *
     * @param array       $params Additional query parameters
     * @param null|string $anchor optional anchor fragment
     *
     * @return moodle_url home page URL
     */
    public static function home(array $params = [], ?string $anchor = null): moodle_url
    {
        return self::get('/', $params, $anchor);
    }

    /**
     * Retrieves the user dashboard URL.
     *
     * @param array       $params Additional query parameters
     * @param null|string $anchor optional anchor fragment
     *
     * @return moodle_url dashboard URL
     */
    public static function dashboard(array $params = [], ?string $anchor = null): moodle_url
    {
        return self::get('/my/', $params, $anchor);
    }

    /**
     * Retrieves a grade report URL.
     *
     * @param int         $courseid Course ID
     * @param string      $report   Report type (e.g., 'user', 'grader')
     * @param array       $params   Additional query parameters
     * @param null|string $anchor   optional anchor fragment
     *
     * @return moodle_url grade report URL
     */
    public static function gradeReport(
        int $courseid,
        string $report = 'user',
        array $params = [],
        ?string $anchor = null
    ): moodle_url {
        $path = sprintf('/grade/report/%s/index.php', $report);
        $urlparams = $params + ['id' => $courseid];

        return self::get($path, $urlparams, $anchor);
    }

    /**
     * Converts a relative URL to an absolute URL.
     *
     * @param string $relativeurl Relative URL path
     *
     * @return string Absolute URL
     */
    public static function toAbsolute(string $relativeurl): string
    {
        global $CFG;

        // Already absolute
        if (str_starts_with($relativeurl, 'http://') || str_starts_with($relativeurl, 'https://')) {
            return $relativeurl;
        }

        // Remove leading slash if present
        $relativeurl = ltrim($relativeurl, '/');

        return rtrim($CFG->wwwroot, '/') . '/' . $relativeurl;
    }

    /**
     * Checks if a URL is external to the current Moodle site.
     *
     * @param string $url URL to check
     *
     * @return bool True if external, false if internal
     */
    public static function isExternal(string $url): bool
    {
        global $CFG;

        // Parse the URL
        $parsed = parse_url($url);

        if (!isset($parsed['host'])) {
            // Relative URL, so it's internal
            return false;
        }

        $sitehost = parse_url($CFG->wwwroot, PHP_URL_HOST);

        return $parsed['host'] !== $sitehost;
    }

    /**
     * Redirects the user to a specific URL.
     *
     * @param moodle_url|string $url         Destination URL
     * @param string            $message     Optional redirection message
     * @param int               $delay       Delay in seconds before redirection
     * @param string            $messagetype Moodle notification type
     */
    public static function redirect(moodle_url|string $url, string $message = '', int $delay = 0, string $messagetype = 'notifysuccess'): void
    {
        redirect($url, $message, $delay, $messagetype);
    }
}
