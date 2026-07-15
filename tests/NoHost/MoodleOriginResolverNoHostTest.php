<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\NoHost;

use Middag\Moodle\Logging\MoodleOriginResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MoodleOriginResolver::class)]
final class MoodleOriginResolverNoHostTest extends TestCase
{
    public function testResolvesToSystemWithoutGetremoteaddr(): void
    {
        // getremoteaddr() is undefined and CLI_SCRIPT is not defined by this
        // bootstrap: the resolver degrades to the neutral system origin.
        self::assertSame('system', (new MoodleOriginResolver())->resolve());
    }
}
