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

use core\check\check as core_check;
use core\check\manager as check_manager;
use core\check\result;
use Middag\Moodle\Domain\Platform\CheckResultDto;
use Middag\Moodle\Domain\Platform\Enum\CheckResultStatus;
use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * Wrapper for Moodle's Check API.
 *
 * Encapsulates access to \core\check\manager and \core\check\result,
 * providing a stable internal API for the framework.
 *
 * @api
 */
class CheckSupport
{
    /**
     * Get all registered checks of a given type.
     *
     * @param string $type check type (e.g. 'status', 'security', 'performance')
     *
     * @return core_check[] list of check objects
     */
    public static function getChecks(string $type = 'status'): array
    {
        try {
            return check_manager::get_checks($type);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return [];
        }
    }

    /**
     * Run a single check and return its result as an associative array.
     *
     * @param string $classname FQCN of the check class
     *
     * @return null|array{id: string, name: string, status: string, summary: string, details: string, action_link: ?string}
     */
    public static function runCheck(string $classname): ?array
    {
        try {
            /** @var core_check $check */
            $check = new $classname();
            $checkresult = $check->get_result();

            return [
                'id' => $check->get_id(),
                'name' => $check->get_name(),
                'status' => self::getResultStatusLabel($checkresult->get_status()),
                'summary' => $checkresult->get_summary(),
                'details' => $checkresult->get_details(),
                // The action link lives on the check, not the result: Moodle 5.0's
                // core\check\result has no get_action_link(); core\check\check does,
                // returning an \action_link whose ->url is the moodle_url.
                'action_link' => $check->get_action_link()?->url?->out(false),
            ];
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Map a \core\check\result status constant to a human-readable label.
     *
     * @param string $status one of the result::* status constants
     *
     * @return string lowercase label
     */
    public static function getResultStatusLabel(string $status): string
    {
        return match ($status) {
            result::OK => 'ok',
            result::INFO => 'info',
            result::WARNING => 'warning',
            result::ERROR => 'error',
            result::CRITICAL => 'critical',
            result::NA => 'na',
            default => 'unknown',
        };
    }

    /**
     * Returns check results as typed DTOs.
     *
     * @return array<string, CheckResultDto> indexed by check ID
     */
    public static function getCheckResults(string $type = 'status'): array
    {
        $checks = self::getChecks($type);
        $result = [];

        foreach ($checks as $check) {
            // check_manager::get_checks() returns core\check\check OBJECTS, so
            // read them through their accessors (mirrors runCheck()); array
            // access here would fatal against a real Moodle check.
            try {
                $checkresult = $check->get_result();
                // Key by get_ref() (component-qualified), not the bare get_id():
                // get_id() is only unique WITHIN a component, so two plugins'
                // checks can share an id and one would silently overwrite the
                // other. checkId keeps the bare id for display.
                $result[$check->get_ref()] = new CheckResultDto(
                    checkId: $check->get_id(),
                    status: CheckResultStatus::resolve(self::getResultStatusLabel($checkresult->get_status())),
                    summary: $checkresult->get_summary(),
                    details: $checkresult->get_details(),
                );
            } catch (Throwable $throwable) {
                // Per-check guard (mirrors runCheck()): one misbehaving check
                // must degrade gracefully instead of aborting the whole batch.
                Debug::traceException($throwable);
            }
        }

        return $result;
    }
}
