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

use Middag\Moodle\Runtime\MoodleHostContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MoodleHostContext::class)]
final class MoodleHostContextNoHostTest extends TestCase
{
    public function testResolveDegradesGracefullyWithoutMoodleRuntime(): void
    {
        $context = MoodleHostContext::resolve();

        self::assertSame('local_example', $context->componentName());
        // get_config() is undefined here: ConfigSupport degrades to false and
        // the context falls back to the stable cache-busting token.
        self::assertSame('0', $context->assetVersion());
        // core\component is absent: the base path degrades to null per contract.
        self::assertNull($context->basePath());
    }
}
