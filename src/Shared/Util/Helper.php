<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Shared\Util;

use core\output\core_renderer;
use core\output\notification;
use Exception;

/**
 * General helper and utility functions for various common operations.
 *
 * @internal
 */
class Helper
{
    /**
     * Logs a message during plugin installation or upgrade process, using Moodle notifications.
     *
     * @param string $message Message to log
     * @param string $type    Notification type (defaults to success)
     */
    public static function installOrUpgradeLog(string $message, string $type = notification::NOTIFY_SUCCESS): void
    {
        global $OUTPUT, $PAGE;

        $output = $OUTPUT;
        if (!$OUTPUT instanceof core_renderer) {
            $output = $PAGE->get_renderer('core');
        }

        try {
            $notification = new notification($message, $type, false);
            echo $output->render_from_template($notification->get_template_name(), $notification->export_for_template($output));
        } catch (Exception $exception) {
            debug::traceException($exception);
        }
    }
}
