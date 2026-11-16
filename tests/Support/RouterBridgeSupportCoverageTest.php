<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use Middag\Moodle\Runtime\Kernel;
use Middag\Moodle\Support\RouterBridgeSupport;
use Middag\Moodle\Support\VersionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @internal
 */
#[CoversClass(RouterBridgeSupport::class)]
final class RouterBridgeSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    /** @var array<string, mixed> */
    private array $prevServer = [];

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevServer = $_SERVER;
        $GLOBALS['CFG'] = (object) ['wwwroot' => 'https://moodle.test'];

        Kernel::shutdown();
    }

    protected function tearDown(): void
    {
        Kernel::shutdown();

        $GLOBALS['CFG'] = $this->prevCfg;
        $_SERVER = $this->prevServer;

        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
    }

    #[Test]
    public function testIsAvailableReturnsTrueOnMoodle51(): void
    {
        $this->setMoodleBranch(501);

        self::assertTrue(RouterBridgeSupport::isAvailable());
    }

    #[Test]
    public function testIsAvailableReturnsFalseBeforeMoodle51(): void
    {
        $this->setMoodleBranch(500);

        self::assertFalse(RouterBridgeSupport::isAvailable());
    }

    #[Test]
    public function testRegisterIsNoopWhenRouterUnavailable(): void
    {
        $this->setMoodleBranch(500);

        self::assertFalse(RouterBridgeSupport::isAvailable());
        self::assertNull(RouterBridgeSupport::register());
    }

    #[Test]
    public function testRegisterIsNoopWhenRouterAvailable(): void
    {
        $this->setMoodleBranch(501);

        self::assertTrue(RouterBridgeSupport::isAvailable());
        self::assertNull(RouterBridgeSupport::register());
    }

    #[Test]
    public function testGetOpenapiJsonUrlUsesWwwroot(): void
    {
        self::assertSame(
            'https://moodle.test/local/example/index.php/api/openapi.json',
            RouterBridgeSupport::getOpenapiJsonUrl(),
        );
    }

    #[Test]
    public function testGetOpenapiYamlUrlUsesWwwroot(): void
    {
        self::assertSame(
            'https://moodle.test/local/example/index.php/api/openapi.yaml',
            RouterBridgeSupport::getOpenapiYamlUrl(),
        );
    }

    private function setMoodleBranch(int $branch): void
    {
        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
        $GLOBALS['CFG'] = (object) ['branch' => $branch, 'wwwroot' => 'https://moodle.test'];
    }
}
