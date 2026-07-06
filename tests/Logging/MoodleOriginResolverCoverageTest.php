<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Logging;

use Middag\Framework\Logging\Contract\OriginResolverInterface;
use Middag\Moodle\Logging\MoodleOriginResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test MoodleOriginResolver.
 *
 * Resolves a log origin label from the CLI_SCRIPT sentinel or Moodle's
 * getremoteaddr() (stubbed in tests/bootstrap.php via
 * $GLOBALS['__middag_test_remoteaddr']). The CLI branch runs isolated.
 *
 * @internal
 */
#[CoversClass(MoodleOriginResolver::class)]
final class MoodleOriginResolverCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_remoteaddr']);
    }

    #[Test]
    public function resolvesRemoteIpWhenAvailable(): void
    {
        $GLOBALS['__middag_test_remoteaddr'] = '203.0.113.7';

        $this->assertSame('ip:203.0.113.7', (new MoodleOriginResolver())->resolve());
    }

    #[Test]
    public function resolvesSystemWhenNoRemoteAddress(): void
    {
        $GLOBALS['__middag_test_remoteaddr'] = '';

        $this->assertSame('system', (new MoodleOriginResolver())->resolve());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function resolvesCliWhenCliScriptSentinelIsSet(): void
    {
        define('CLI_SCRIPT', true);

        $this->assertSame('cli', (new MoodleOriginResolver())->resolve());
    }

    #[Test]
    public function implementsOriginResolverInterface(): void
    {
        $this->assertInstanceOf(OriginResolverInterface::class, new MoodleOriginResolver());
    }
}
