<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel\Facade\Fixture;

/**
 * Stand-in for a THIRD-PARTY plugin service: plain class, no middag-io/core
 * dependency — only what the adapter + framework already provide.
 *
 * @internal
 */
final class ThirdPartyGreeter
{
    public function greet(string $name): string
    {
        return 'hello, ' . $name;
    }
}
