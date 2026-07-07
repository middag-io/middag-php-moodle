<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Moodle request/param function stubs for RequestSupport coverage.
 * Guarded so this file is order-independent and additive.
 */

if (!function_exists('optional_param')) {
    function optional_param(string $parname, mixed $default, string $type): mixed
    {
        return $GLOBALS['__middag_test_params'][$parname] ?? $default;
    }
}

if (!function_exists('required_param')) {
    function required_param(string $parname, string $type): mixed
    {
        if (!isset($GLOBALS['__middag_test_params'][$parname])) {
            throw new coding_exception('missing required param: ' . $parname);
        }

        return $GLOBALS['__middag_test_params'][$parname];
    }
}

if (!function_exists('validate_email')) {
    function validate_email(string $address): bool
    {
        return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
    }
}
