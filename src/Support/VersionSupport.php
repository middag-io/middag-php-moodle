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

use InvalidArgumentException;
use Middag\Moodle\ValueObject\MoodleVersion as moodle_version;
use RuntimeException;
use stdClass;

/**
 * Infrastructure service to normalize and compare Moodle Core versions.
 *
 * This class normalizes core data to a "semver-like" format (x.y.z)
 * and exposes convenient comparators (>=, ranges, feature gate via declarative matrix).
 * Values are cached in memory after the first read from {@see bootstrap()}.
 *
 * @internal
 */
final class VersionSupport
{
    /** @var string "Semver-like" version derived from branch/release, e.g.: "4.4.0". */
    private static string $semantic = '';

    /** @var int Numeric Moodle branch (e.g.: 404 for 4.4). */
    private static int $branch = 0;

    /** @var int Numeric Moodle build (e.g.: 2024042200). */
    private static int $build = 0;

    /** @var bool Bootstrap flag (avoids repeated reprocessing). */
    private static bool $bootstrapped = false;

    /**
     * Retrieves the "semver-like" version of Moodle (e.g.: "4.4.0").
     *
     * The representation is preferably derived from {@see $CFG->branch}
     * (more predictable) and, as a fallback, from parsing {@see $CFG->release}.
     *
     * @return string Normalized version in x.y.z format.
     *
     * @example
     * if (moodle_versions::version_semver() === '4.4.0') { /* ... *\/ }
     */
    public static function versionSemver(): string
    {
        self::bootstrap();

        return self::$semantic;
    }

    /**
     * Returns the current Moodle version as a typed value object.
     */
    public static function version(): moodle_version
    {
        return moodle_version::from_string(self::versionSemver());
    }

    /**
     * Retrieves the numeric Moodle branch (e.g.: 404 for 4.4).
     *
     * @return int current branch (major*100 + minor)
     *
     * @example
     * [$major, $minor] = moodle_versions::major_minor(); // [4, 4]
     */
    public static function branch(): int
    {
        self::bootstrap();

        return self::$branch;
    }

    /**
     * Retrieves the numeric Moodle build (e.g.: 2024042200).
     *
     * Useful for patch/build comparisons when necessary.
     *
     * @return int current build number
     */
    public static function build(): int
    {
        self::bootstrap();

        return self::$build;
    }

    /**
     * Compares the current Moodle version with a simple constraint.
     *
     * The constraint accepts "x.y" or "x.y.z". The operator is passed to {@see version_compare()}.
     *
     * @param string $operator   valid version_compare() operator: "<", "<=", ">", ">=", "==", "!="
     * @param string $constraint Target version ("4.2" or "4.2.1").
     *
     * @return bool true if the comparison is satisfied
     *
     * @throws InvalidArgumentException if the constraint has an invalid format
     *
     * @example
     * moodle_versions::compare('>=', '4.2');   // true/false
     * moodle_versions::compare('<',  '4.5.0'); // true/false
     */
    public static function compare(string $operator, string $constraint): bool
    {
        self::bootstrap();
        $normalized_constraint = self::normalizeVersionString($constraint, 'invalidversionconstraint');

        return version_compare(self::$semantic, $normalized_constraint, $operator);
    }

    /**
     * Checks if the current version is at least the specified one.
     *
     * Syntactic sugar for {@see compare()} with ">=" operator.
     *
     * @param string $min Minimum version ("x.y" or "x.y.z").
     *
     * @return bool True if at least the specified version
     *
     * @example
     * if (moodle_versions::at_least('4.1')) {
     * }
     */
    public static function atLeast(moodle_version|string $min): bool
    {
        if ($min instanceof moodle_version) {
            $min = (string) $min;
        }

        return self::compare('>=', $min);
    }

    /**
     * Checks if the current version is between [min, max], inclusive.
     *
     * @param string $min Minimum version ("x.y" or "x.y.z").
     * @param string $max Maximum version ("x.y" or "x.y.z").
     *
     * @return bool True if within range
     *
     * @example
     * if (moodle_versions::between('4.0', '4.2')) {
     * }
     */
    public static function between(moodle_version|string $min, moodle_version|string $max): bool
    {
        if ($min instanceof moodle_version) {
            $min = (string) $min;
        }
        if ($max instanceof moodle_version) {
            $max = (string) $max;
        }

        return self::atLeast($min) && self::compare('<=', $max);
    }

    /**
     * Ensures the minimum version is met; otherwise throws an exception.
     *
     * If no message is provided, an internationalized message is used
     * ({@see local_example/lang/* requiresmoodlemin}).
     *
     * @param string      $min Required minimum version ("x.y" or "x.y.z").
     * @param null|string $msg Custom message (already translated), optional
     *
     * @throws RuntimeException if the current version is less than the minimum
     *
     * @example
     * moodle_versions::assert_min('4.0'); // throws RuntimeException if < 4.0
     */
    public static function assertMin(string $min, ?string $msg = null): void
    {
        if (!self::atLeast($min)) {
            $cur = self::versionSemver();
            $msg ??= self::str('requiresmoodlemin', (object) ['min' => $min, 'current' => $cur]);

            throw new RuntimeException($msg);
        }
    }

    /**
     * Checks if a feature is supported according to a declarative matrix.
     *
     * Each map entry accepts the keys:
     * - "since" => minimum version (inclusive)
     * - "until" => maximum version (inclusive)
     *
     * @param string                                               $feature feature name (map key)
     * @param array<string, array{since?: string, until?: string}> $matrix  rule map per feature
     *
     * @return bool true if the current version satisfies the feature rules
     *
     * @example
     * $matrix = [
     *     'new_tasks_api'   => ['since' => '4.3'],
     *     'legacy_renderer' => ['until' => '4.2'],
     *     'cool_api'        => ['since' => '4.2', 'until' => '4.5'],
     * ];
     * if (moodle_versions::supports('cool_api', $matrix)) {
     * }
     */
    public static function supports(string $feature, array $matrix): bool
    {
        if (!isset($matrix[$feature])) {
            // If the feature is not in the matrix, it is not supported.
            return false;
        }

        $rule = $matrix[$feature];

        // A feature is supported if the current version is >= 'since' AND <= 'until'.
        $since_ok = empty($rule['since']) || self::atLeast($rule['since']);
        $until_ok = empty($rule['until']) || self::compare('<=', $rule['until']);

        return $since_ok && $until_ok;
    }

    /**
     * Reports whether an optional Moodle API symbol (class, interface, or trait)
     * is defined in the running Moodle.
     *
     * Adapter code probes newer-Moodle symbols (e.g. the 5.1+ native router) that
     * legitimately do not exist on the supported floor. A direct
     * {@see class_exists()} on a literal class-string lets PHPStan constant-fold
     * the check to "always false" whenever the analysed moodle-stubs set omits the
     * symbol, which makes static analysis non-deterministic across stub releases.
     * Probing through this {@see string}-typed seam keeps the runtime check exact
     * while preventing the false static narrowing, and also covers interfaces and
     * traits, which {@see class_exists()} alone does not.
     *
     * @param string $symbol fully qualified class, interface, or trait name
     *
     * @return bool true if the symbol is defined in the current runtime
     */
    public static function symbolExists(string $symbol): bool
    {
        return class_exists($symbol)
            || interface_exists($symbol)
            || trait_exists($symbol);
    }

    /**
     * Returns a [major, minor] pair, e.g.: [4, 4].
     *
     * Useful for quick switches by major/minor version.
     *
     * @return array{0: int, 1: int} array with [major, minor]
     */
    public static function majorMinor(): array
    {
        self::bootstrap();
        [$major, $minor] = array_map('intval', explode('.', self::$semantic));

        return [$major, $minor];
    }

    /**
     * Internal i18n helper for the plugin component.
     *
     * @param string               $id string identifier
     * @param null|stdClass|string $a  data for placeholders
     *
     * @return string localized string
     *
     * @internal internal use to centralize calls to {@see get_string()}
     */
    private static function str(string $id, stdClass|string|null $a = null): string
    {
        return LangSupport::get($id, $a);
    }

    /**
     * Initializes and caches version data from Moodle global configuration.
     *
     * Order of preference:
     * 1) $CFG->branch defines major/minor and builds "x.y.0"
     * 2) Parse $CFG->release extracts x.y(.z) if possible
     * 3) Fallback to "0.0.0"
     */
    private static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        global $CFG;

        // Build (always numeric in core).
        self::$build = isset($CFG->version) ? (int) $CFG->version : 0;

        // Branch (e.g.: "404" -> 404).
        if (!empty($CFG->branch) && ctype_digit((string) $CFG->branch)) {
            self::$branch = (int) $CFG->branch;
        } else {
            // Fallback: try to extract from $CFG->release
            $release = isset($CFG->release) ? (string) $CFG->release : '';
            $branch_from_release = preg_match('~(\d+)\.(\d+)~', $release, $m) ? ((int) $m[1] * 100 + (int) $m[2]) : 0;
            self::$branch = $branch_from_release;
        }

        // "Semver-like": 4.4.0 from branch, or parse from release.
        if (self::$branch > 0) {
            $major = intdiv(self::$branch, 100);
            $minor = self::$branch % 100;
            self::$semantic = sprintf('%d.%d.0', $major, $minor);
        } else {
            $release = isset($CFG->release) ? (string) $CFG->release : '';
            if (preg_match('~^(\d+)\.(\d+)(?:\.(\d+))?~', $release, $m)) {
                $major = (int) $m[1];
                $minor = (int) $m[2];
                $patch = isset($m[3]) ? (int) $m[3] : 0;
                self::$semantic = sprintf('%d.%d.%d', $major, $minor, $patch);
            } else {
                self::$semantic = '0.0.0';
            }
        }

        self::$bootstrapped = true;

        self::registerAliases();
    }

    /**
     * Registers class aliases for Moodle version compatibility.
     */
    private static function registerAliases(): void
    {
        // Example: Handle core class renames between Moodle versions.
        // if (self::at_least('4.4') && !class_exists('some_old_class')) {
        //     class_alias('some_new_class', 'some_old_class');
        // }
    }

    /**
     * Normalizes and validates a version string to x.y.z format.
     *
     * Accepts "x.y" or "x.y.z". In case of "x.y", appends ".0".
     *
     * @param string $version       version string to validate
     * @param string $errorstringid error string ID for the exception
     *
     * @return string Normalized version in x.y.z format.
     *
     * @throws InvalidArgumentException If the format does not match x.y(.z).
     */
    private static function normalizeVersionString(string $version, string $errorstringid = 'invalidversion'): string
    {
        // Optimized regex check logic
        if (!preg_match('~^\d+\.\d+(\.\d+)?$~', $version)) {
            throw new InvalidArgumentException(self::str($errorstringid, $version));
        }

        if (substr_count($version, '.') === 1) {
            $version .= '.0';
        }

        return $version;
    }
}
