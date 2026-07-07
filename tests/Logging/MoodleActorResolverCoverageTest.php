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

use Middag\Framework\Logging\Contract\ActorResolverInterface;
use Middag\Moodle\Logging\MoodleActorResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test MoodleActorResolver.
 *
 * Resolves a log actor label from the CLI_SCRIPT sentinel or the $USER global.
 * The CLI branch defines a permanent constant, so it runs in an isolated
 * process to avoid poisoning the non-CLI cases.
 *
 * @internal
 */
#[CoversClass(MoodleActorResolver::class)]
final class MoodleActorResolverCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['USER']);
    }

    #[Test]
    public function resolvesAuthenticatedUserById(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 42];

        $this->assertSame('user:42', (new MoodleActorResolver())->resolve());
    }

    #[Test]
    public function resolvesSystemWhenNoUserIsSet(): void
    {
        unset($GLOBALS['USER']);

        $this->assertSame('system', (new MoodleActorResolver())->resolve());
    }

    #[Test]
    public function resolvesSystemWhenUserIdIsZero(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 0];

        $this->assertSame('system', (new MoodleActorResolver())->resolve());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function resolvesCliWhenCliScriptSentinelIsSet(): void
    {
        define('CLI_SCRIPT', true);

        $this->assertSame('cli', (new MoodleActorResolver())->resolve());
    }

    #[Test]
    public function implementsActorResolverInterface(): void
    {
        $this->assertInstanceOf(ActorResolverInterface::class, new MoodleActorResolver());
    }
}
