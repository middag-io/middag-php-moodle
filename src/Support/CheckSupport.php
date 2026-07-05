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
use Middag\Moodle\Domain\Platform\CheckResultStatus;
use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * Wrapper for Moodle's Check API.
 *
 * Encapsulates access to \core\check\manager and \core\check\result,
 * providing a stable internal API for the framework.
 *
 * @internal
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
                'action_link' => $checkresult->get_action_link()?->out(false),
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
            $id = $check['id'] ?? '';
            $result[$id] = new CheckResultDto(
                checkId: $id,
                status: CheckResultStatus::resolve($check['result'] ?? 'unknown'),
                summary: $check['summary'] ?? '',
                details: $check['details'] ?? null,
            );
        }

        return $result;
    }
}
