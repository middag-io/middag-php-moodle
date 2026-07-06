<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Platform;

use core\check\result;
use Middag\Moodle\Domain\Platform\PlatformService;
use Middag\Moodle\Domain\Platform\PluginDto;
use Middag\Moodle\Support\PluginSupport;
use Middag\Moodle\Support\VersionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * A \core\check\result-shaped double returned by the check fixture below.
 *
 * @internal
 */
final readonly class PlatformServiceResultFixture
{
    public function get_status(): string
    {
        return result::OK;
    }

    public function get_summary(): string
    {
        return 'all good';
    }

    public function get_details(): string
    {
        return 'nothing to report';
    }

    public function get_action_link(): ?object
    {
        return null;
    }
}

/**
 * A \core\check\check-shaped double the runCheck() delegation instantiates.
 *
 * @internal
 */
final class PlatformServiceCheckFixture
{
    public function get_id(): string
    {
        return 'platform_ok';
    }

    public function get_name(): string
    {
        return 'Platform OK';
    }

    public function get_result(): PlatformServiceResultFixture
    {
        return new PlatformServiceResultFixture();
    }
}

/**
 * A check double whose result resolution throws, driving CheckSupport's catch
 * so PlatformService::runCheck() returns null via the delegate.
 *
 * @internal
 */
final class PlatformServiceThrowingCheckFixture
{
    public function get_id(): string
    {
        return 'boom';
    }

    public function get_name(): string
    {
        return 'Boom';
    }

    public function get_result(): PlatformServiceResultFixture
    {
        throw new RuntimeException('check failed');
    }
}

/**
 * PlatformService composes VersionSupport (static, $CFG-driven), CheckSupport
 * (static) and an injected PluginSupport. The version helpers are made
 * deterministic by driving $CFG (branch 405 -> "4.5.0") and resetting
 * VersionSupport's private static cache in setUp/tearDown; PluginSupport is a
 * mock so getPluginInfo()'s exists / not-exists branches are exercised in
 * isolation; runCheck() is covered against a real CheckSupport via check
 * fixtures.
 *
 * @internal
 */
#[CoversClass(PlatformService::class)]
final class PlatformServiceCoverageTest extends TestCase
{
    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        // Running version: branch 405 -> "4.5.0".
        $GLOBALS['CFG'] = (object) [
            'branch' => '405',
            'version' => 2024100100,
            'release' => '4.5+ (Build: 20241001)',
        ];

        $this->resetVersionCache();
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;

        $this->resetVersionCache();
    }

    #[Test]
    public function testVersionDelegatesToVersionSupport(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        self::assertSame(VersionSupport::versionSemver(), $service->version());
        self::assertSame('4.5.0', $service->version());
    }

    #[Test]
    public function testBranchDelegatesToVersionSupport(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        self::assertSame(405, $service->branch());
    }

    #[Test]
    public function testAtLeastDelegatesToVersionSupport(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        self::assertTrue($service->atLeast('4.0'));
        self::assertFalse($service->atLeast('9.0'));
    }

    #[Test]
    public function testBetweenDelegatesToVersionSupport(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        self::assertTrue($service->between('4.0', '5.0'));
        self::assertFalse($service->between('4.0', '4.4'));
    }

    #[Test]
    public function testSupportsDelegatesToVersionSupport(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        self::assertTrue($service->supports('feat', ['feat' => ['since' => '4.0']]));
        self::assertFalse($service->supports('missing', []));
    }

    #[Test]
    public function testAssertMinPassesWhenTheVersionIsMet(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        $thrown = false;

        try {
            $service->assertMin('4.0');
        } catch (RuntimeException) {
            $thrown = true;
        }

        self::assertFalse($thrown);
    }

    #[Test]
    public function testAssertMinThrowsWithACustomMessage(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('needs newer moodle');

        $service->assertMin('9.0', 'needs newer moodle');
    }

    #[Test]
    public function testGetPluginInfoReturnsNullWhenThePluginDoesNotExist(): void
    {
        $plugin = $this->createMock(PluginSupport::class);
        $plugin->method('pluginExists')->with('local', 'ghost')->willReturn(false);
        // getPluginInfo() must NOT be reached when the plugin is absent.
        $plugin->expects(self::never())->method('getPluginInfo');

        $service = new PlatformService($plugin);

        self::assertNull($service->getPluginInfo('local', 'ghost'));
    }

    #[Test]
    public function testGetPluginInfoReturnsTheDtoWhenThePluginExists(): void
    {
        $dto = new PluginDto(
            type: 'local',
            name: 'middag',
            component: 'local_middag',
            rootdir: '/var/www/local/middag',
            displayname: 'MIDDAG',
            source: 'extension',
            versiondisk: 2024010100,
            versiondb: 2024010100,
            versionrequires: 2022010100,
            dependencies: null,
            enabled: true,
            release: '1.0.0',
            supported: null,
            incompatible: null,
            status: 'uptodate',
        );

        $plugin = $this->createMock(PluginSupport::class);
        $plugin->method('pluginExists')->with('local', 'middag')->willReturn(true);
        $plugin->method('getPluginInfo')->with('local', 'middag')->willReturn($dto);

        $service = new PlatformService($plugin);

        self::assertSame($dto, $service->getPluginInfo('local', 'middag'));
    }

    #[Test]
    public function testPluginExistsDelegatesToPluginSupport(): void
    {
        $plugin = $this->createMock(PluginSupport::class);
        $plugin->method('pluginExists')
            ->willReturnMap([
                ['local', 'middag', true],
                ['local', 'ghost', false],
            ]);

        $service = new PlatformService($plugin);

        self::assertTrue($service->pluginExists('local', 'middag'));
        self::assertFalse($service->pluginExists('local', 'ghost'));
    }

    #[Test]
    public function testRunCheckDelegatesToCheckSupport(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        $result = $service->runCheck(PlatformServiceCheckFixture::class);

        self::assertIsArray($result);
        self::assertSame('platform_ok', $result['id']);
        self::assertSame('Platform OK', $result['name']);
        self::assertSame('ok', $result['status']);
        self::assertSame('all good', $result['summary']);
    }

    #[Test]
    public function testRunCheckReturnsNullWhenTheCheckFails(): void
    {
        $service = new PlatformService($this->createStub(PluginSupport::class));

        self::assertNull($service->runCheck(PlatformServiceThrowingCheckFixture::class));
    }

    private function resetVersionCache(): void
    {
        $reflection = new ReflectionClass(VersionSupport::class);
        $reflection->getProperty('bootstrapped')->setValue(null, false);
        $reflection->getProperty('semantic')->setValue(null, '');
        $reflection->getProperty('branch')->setValue(null, 0);
        $reflection->getProperty('build')->setValue(null, 0);
    }
}
