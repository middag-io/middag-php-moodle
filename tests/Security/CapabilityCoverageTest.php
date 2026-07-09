<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Security;

use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Moodle\Domain\Context\Enum\ContextLevel;
use Middag\Moodle\Security\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Capability delegates permission checks to Moodle through the Support layer.
 *
 * has_capability() is provided by tests/bootstrap.php and driven via
 * $GLOBALS['__middag_test_has_capability'] (default true). The namespaced
 * context classes core\context\{course,coursecat,module,block,user} are
 * provided by tests/stubs/support/config-env.php and return their own subtype,
 * so resolveContext() succeeds for those levels. The SYSTEM level is special:
 * bootstrap models core\context\system::instance() as returning a *base*
 * core\context, which violates ContextSupport::system()'s declared subtype
 * return and surfaces a TypeError — the same stub artefact asserted by
 * ContextSupportCoverageTest. Tests therefore exercise the granted/denied paths
 * on a non-SYSTEM level and cover the SYSTEM match arm via that TypeError.
 *
 * @internal
 */
#[CoversClass(Capability::class)]
final class CapabilityCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        unset($GLOBALS['__middag_test_has_capability'], $GLOBALS['__middag_test_throw_has_capability']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_has_capability'], $GLOBALS['__middag_test_throw_has_capability']);
    }

    #[Test]
    public function testCanReturnsTrueWhenMoodleGrantsTheCapability(): void
    {
        $GLOBALS['__middag_test_has_capability'] = true;

        $capability = new Capability();

        self::assertTrue($capability->can('local/example:manage', ContextLevel::Course, 5));
    }

    #[Test]
    public function testCanReturnsFalseWhenMoodleDeniesTheCapability(): void
    {
        $GLOBALS['__middag_test_has_capability'] = false;

        $capability = new Capability();

        self::assertFalse($capability->can('local/example:manage', ContextLevel::Course, 5));
    }

    /**
     * Every non-SYSTEM match arm of resolveContext() resolves to a valid Moodle
     * context, so can() reaches has_capability() and returns its verdict.
     */
    #[Test]
    #[DataProvider('nonSystemContextLevels')]
    public function testCanResolvesEveryNonSystemContextLevel(ContextLevel $level, int $instanceid): void
    {
        $GLOBALS['__middag_test_has_capability'] = true;

        $capability = new Capability();

        self::assertTrue($capability->can('local/example:manage', $level, $instanceid));
    }

    /**
     * @return iterable<string, array{ContextLevel, int}>
     */
    public static function nonSystemContextLevels(): iterable
    {
        yield 'course' => [ContextLevel::Course, 5];

        yield 'coursecat' => [ContextLevel::Coursecat, 3];

        yield 'module' => [ContextLevel::Module, 7];

        yield 'block' => [ContextLevel::Block, 9];

        yield 'user' => [ContextLevel::User, 11];
    }

    /**
     * The SYSTEM match arm is reachable: it invokes ContextSupport::system(),
     * which the bootstrap stub makes surface a TypeError (base core\context
     * returned where core\context\system is declared). Real Moodle returns a
     * genuine system context here, so this documents a harness artefact — not a
     * source defect — while still executing the SYSTEM arm.
     */
    #[Test]
    public function testCanForSystemLevelSurfacesTheStubTypeError(): void
    {
        $GLOBALS['__middag_test_has_capability'] = true;

        $capability = new Capability();

        $this->expectException(TypeError::class);

        $capability->can('local/example:manage', ContextLevel::System);
    }

    #[Test]
    public function testAuthorizeReturnsWithoutThrowingWhenTheCapabilityIsGranted(): void
    {
        $GLOBALS['__middag_test_has_capability'] = true;

        $capability = new Capability();

        $capability->authorize('local/example:manage', ContextLevel::Course, 5);

        // Reaching this line proves authorize() did not throw when granted; the
        // assertion re-confirms the underlying grant is observable.
        self::assertTrue($capability->can('local/example:manage', ContextLevel::Course, 5));
    }

    #[Test]
    public function testAuthorizeThrowsAuthorizationExceptionWhenTheCapabilityIsDenied(): void
    {
        $GLOBALS['__middag_test_has_capability'] = false;

        $capability = new Capability();

        $this->expectException(MiddagAuthorizationException::class);
        $this->expectExceptionMessage('Missing capability: local/example:manage');

        $capability->authorize('local/example:manage', ContextLevel::Course, 5);
    }
}
