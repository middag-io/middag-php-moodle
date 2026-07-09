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
 * Runtime stubs for Moodle's core_external API.
 *
 * michaelmeneses/moodle-stubs ships these for PHPStan (scanDirectories) only —
 * they are NOT autoloadable at runtime. Any class that extends
 * core_external\external_api (e.g. Middag\Moodle\WebService\AbstractExternal)
 * would fatal under PHPUnit without a behavioural stand-in. These minimal stubs
 * mirror the real constructor signatures and store the declared shape so tests can
 * assert it. They do NOT reproduce Moodle's parameter/return validation engine —
 * that requires a booted Moodle and is out of scope for unit tests.
 */

// Moodle global constants used when declaring external structures.
if (!defined('VALUE_REQUIRED')) {
    define('VALUE_REQUIRED', 1);
}
if (!defined('VALUE_OPTIONAL')) {
    define('VALUE_OPTIONAL', 2);
}
if (!defined('VALUE_DEFAULT')) {
    define('VALUE_DEFAULT', 0);
}
if (!defined('NULL_NOT_ALLOWED')) {
    define('NULL_NOT_ALLOWED', 0);
}
if (!defined('NULL_ALLOWED')) {
    define('NULL_ALLOWED', 1);
}
if (!defined('PARAM_INT')) {
    define('PARAM_INT', 'int');
}
if (!defined('PARAM_TEXT')) {
    define('PARAM_TEXT', 'text');
}

if (!class_exists('core_external\external_api', false)) {
    eval(<<<'PHP'
        namespace core_external;

        /** Base class for external api methods (runtime stub). */
        class external_api
        {
            /**
             * Simplified coercion of a response against its declared structure.
             *
             * Stands in for Moodle's external_api::clean_returnvalue() for unit tests:
             * it casts scalars to their PARAM_* family and recurses structures, which is
             * enough to prove a declared return structure serializes its data. It does
             * NOT reproduce Moodle's full validation (required-key enforcement, rich
             * PARAM cleaning, exceptions) — that needs a booted Moodle.
             */
            public static function clean_returnvalue(external_description $description, mixed $response): mixed
            {
                if ($description instanceof external_value) {
                    return self::coerce_scalar($description->type, $response);
                }

                if ($description instanceof external_single_structure) {
                    $clean = [];
                    $data = (array) $response;
                    foreach ($description->keys as $key => $keyDescription) {
                        if (array_key_exists($key, $data)) {
                            $clean[$key] = self::clean_returnvalue($keyDescription, $data[$key]);
                        }
                    }

                    return $clean;
                }

                if ($description instanceof external_multiple_structure) {
                    return array_map(
                        static fn ($item): mixed => self::clean_returnvalue($description->content, $item),
                        (array) $response,
                    );
                }

                return $response;
            }

            private static function coerce_scalar(mixed $type, mixed $value): mixed
            {
                return match ($type) {
                    'int' => (int) $value,
                    'float' => (float) $value,
                    'bool' => (bool) $value,
                    'text', 'raw', 'alpha', 'alphanum' => (string) $value,
                    default => $value,
                };
            }
        }

        /** Common parameter/return description base (runtime stub). */
        abstract class external_description
        {
            public mixed $default;
            public int $required;
            public string $desc;
            public bool $allownull;

            public function __construct(string $desc, int $required = VALUE_REQUIRED, mixed $default = null, int $allownull = NULL_NOT_ALLOWED)
            {
                $this->desc = $desc;
                $this->required = $required;
                $this->default = $default;
                $this->allownull = (bool) $allownull;
            }
        }

        /** Scalar value description (runtime stub). */
        class external_value extends external_description
        {
            public mixed $type;

            public function __construct(mixed $type, string $desc = '', int $required = VALUE_REQUIRED, mixed $default = null, int $allownull = NULL_ALLOWED)
            {
                parent::__construct($desc, $required, $default, $allownull);
                $this->type = $type;
            }
        }

        /** Associative structure description (runtime stub). */
        class external_single_structure extends external_description
        {
            /** @var array<string, external_description> */
            public array $keys;

            public function __construct(array $keys, string $desc = '', int $required = VALUE_REQUIRED, mixed $default = null, int $allownull = NULL_NOT_ALLOWED)
            {
                parent::__construct($desc, $required, $default, $allownull);
                $this->keys = $keys;
            }
        }

        /** Function parameter list description (runtime stub). */
        class external_function_parameters extends external_single_structure
        {
            public function __construct(array $keys, string $desc = '', int $required = VALUE_REQUIRED, mixed $default = null)
            {
                parent::__construct($keys, $desc, $required, $default);
            }
        }

        /** Homogeneous list description (runtime stub). */
        class external_multiple_structure extends external_description
        {
            public external_description $content;

            public function __construct(external_description $content, string $desc = '', int $required = VALUE_REQUIRED, mixed $default = null, int $allownull = NULL_NOT_ALLOWED)
            {
                parent::__construct($desc, $required, $default, $allownull);
                $this->content = $content;
            }
        }
        PHP);
}
