<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Tooling;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Negative proof only, never merged: CI must fail on both deprecations.
 *
 * @internal
 */
final class DeprecationProbeTest extends TestCase
{
    #[Test]
    public function plainDeprecation(): void
    {
        trigger_error('probe: plain deprecation', E_USER_DEPRECATED);

        self::assertTrue(true);
    }

    #[Test]
    public function suppressedDeprecation(): void
    {
        @trigger_error('probe: suppressed deprecation', E_USER_DEPRECATED);

        self::assertTrue(true);
    }
}
