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
 * Request support wrapper for Moodle's parameter handling.
 *
 * @internal
 */
class RequestSupport
{
    /**
     * Retrieves an optional parameter.
     *
     * @param string $parname the parameter name
     * @param mixed  $default the default value if parameter is missing
     * @param string $type    The expected parameter type constant (e.g., PARAM_INT).
     *
     * @return mixed the parameter value
     */
    public static function optionalParam(string $parname, $default, string $type)
    {
        return optional_param($parname, $default, $type);
    }

    /**
     * Retrieves a required parameter.
     *
     * @param string $parname the parameter name
     * @param string $type    The expected parameter type constant (e.g., PARAM_INT).
     *
     * @return mixed the parameter value
     */
    public static function requiredParam(string $parname, string $type)
    {
        return required_param($parname, $type);
    }

    /**
     * Validates an email address using Moodle's validation rules.
     *
     * @param string $address the email address to validate
     *
     * @return bool True if valid, false otherwise
     */
    public static function validateEmail(string $address): bool
    {
        return validate_email($address);
    }
}
