<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http;

use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\Moodle\Http\Contract\MoodleControllerInterface;
use Middag\Moodle\Http\MoodleHttpKernel;
use Middag\Moodle\Security\Attribute\Sesskey;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * MoodleHttpKernel extends the framework HttpKernel to add ONE Moodle-specific
 * hook: applyPlatformAuth(), which reads the #[Sesskey] attribute (method > class)
 * and, when it requires sesskey validation, flips the flag on a Moodle controller.
 *
 * The parent HttpKernel needs no Moodle runtime to construct — a real instance
 * over an empty route collection is built inline, then applyPlatformAuth() is
 * driven directly via reflection (it is protected) against annotated controller
 * fixtures. Every branch — method attribute, class fallback, no attribute,
 * require=false, and the non-Moodle-controller gate — is exercised with an
 * observable assertion on whether setRequireSesskey() was invoked.
 *
 * @internal
 */
#[CoversClass(MoodleHttpKernel::class)]
final class MoodleHttpKernelCoverageTest extends TestCase
{
    #[Test]
    public function testMethodLevelSesskeyFlagsTheMoodleController(): void
    {
        $controller = new HttpKernelSesskeyOnMethodController();

        $this->applyPlatformAuth($this->makeKernel(), $controller, 'mutate');

        // Method-level #[Sesskey] (require: true) → setRequireSesskey() called.
        self::assertTrue($controller->sesskeyRequired);
    }

    #[Test]
    public function testClassLevelSesskeyIsUsedWhenTheMethodHasNoAttribute(): void
    {
        $controller = new HttpKernelSesskeyOnClassController();

        // The action carries no attribute, so the kernel must fall back to the
        // class-level #[Sesskey] before flagging the controller.
        $this->applyPlatformAuth($this->makeKernel(), $controller, 'mutate');

        self::assertTrue($controller->sesskeyRequired);
    }

    #[Test]
    public function testNoAttributeAnywhereIsANoop(): void
    {
        $controller = new HttpKernelNoSesskeyController();

        // Neither the method nor the class declares #[Sesskey] → early return,
        // the controller is left untouched.
        $this->applyPlatformAuth($this->makeKernel(), $controller, 'mutate');

        self::assertFalse($controller->sesskeyRequired);
    }

    #[Test]
    public function testSesskeyWithRequireFalseIsANoop(): void
    {
        $controller = new HttpKernelSesskeyOptOutController();

        // #[Sesskey(require: false)] resolves to an instance whose require flag is
        // false → the kernel returns without calling setRequireSesskey().
        $this->applyPlatformAuth($this->makeKernel(), $controller, 'mutate');

        self::assertFalse($controller->sesskeyRequired);
    }

    #[Test]
    public function testNonMoodleControllerIsNotFlaggedEvenWhenSesskeyRequired(): void
    {
        $controller = new HttpKernelPlainSesskeyController();

        // The controller declares #[Sesskey] (require: true) but is only a
        // framework ControllerInterface, not a MoodleControllerInterface, so the
        // instanceof gate short-circuits before setRequireSesskey() would run.
        $this->applyPlatformAuth($this->makeKernel(), $controller, 'mutate');

        self::assertFalse($controller->sesskeyRequired);
    }

    /**
     * Build a genuine MoodleHttpKernel over an empty route collection.
     *
     * The kernel needs no Moodle runtime to construct; only applyPlatformAuth()
     * is exercised, so the routing/PSR plumbing is inert here.
     */
    private function makeKernel(): MoodleHttpKernel
    {
        $psr17 = new Psr17Factory();

        return new MoodleHttpKernel(
            new ContainerBuilder(),
            new RouteCollection(),
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );
    }

    /**
     * Invoke the protected applyPlatformAuth() hook via reflection.
     */
    private function applyPlatformAuth(MoodleHttpKernel $kernel, object $controller, string $method): void
    {
        (new ReflectionMethod($kernel, 'applyPlatformAuth'))->invoke($kernel, $controller, $method);
    }
}

/**
 * Shared no-op implementation of the Moodle controller contract that records
 * whether setRequireSesskey() was invoked. Fixtures vary only in where (or
 * whether) the #[Sesskey] attribute is declared.
 *
 * @internal
 */
trait HttpKernelMoodleControllerStub
{
    public bool $sesskeyRequired = false;

    public function handle(): void {}

    public function setContainer(ContainerInterface $container): void {}

    public function setRequest(Request $request): void {}

    public function preHandle(): void {}

    public function setRequireLogin(): void {}

    public function setRequireCapabilities(array $capabilities, mixed $context = null, int $instanceId = 0): void {}

    public function setRequireSesskey(bool $require = true): void
    {
        $this->sesskeyRequired = $require;
    }
}

/**
 * Method-level #[Sesskey] on a Moodle controller (require defaults to true).
 *
 * @internal
 */
final class HttpKernelSesskeyOnMethodController implements MoodleControllerInterface
{
    use HttpKernelMoodleControllerStub;

    #[Sesskey]
    public function mutate(): void {}
}

/**
 * Class-level #[Sesskey] on a Moodle controller; the action has no attribute so
 * the kernel must fall back to the class.
 *
 * @internal
 */
#[Sesskey]
final class HttpKernelSesskeyOnClassController implements MoodleControllerInterface
{
    use HttpKernelMoodleControllerStub;

    public function mutate(): void {}
}

/**
 * Moodle controller with no #[Sesskey] anywhere → applyPlatformAuth() early-returns.
 *
 * @internal
 */
final class HttpKernelNoSesskeyController implements MoodleControllerInterface
{
    use HttpKernelMoodleControllerStub;

    public function mutate(): void {}
}

/**
 * Moodle controller whose #[Sesskey(require: false)] opts out of validation.
 *
 * @internal
 */
final class HttpKernelSesskeyOptOutController implements MoodleControllerInterface
{
    use HttpKernelMoodleControllerStub;

    #[Sesskey(require: false)]
    public function mutate(): void {}
}

/**
 * A plain framework controller (NOT a MoodleControllerInterface) that still
 * declares #[Sesskey]. Carries its own setRequireSesskey() recorder to prove the
 * instanceof gate prevents the flag from being set.
 *
 * @internal
 */
final class HttpKernelPlainSesskeyController implements ControllerInterface
{
    public bool $sesskeyRequired = false;

    public function handle(): void {}

    public function setContainer(ContainerInterface $container): void {}

    public function setRequest(Request $request): void {}

    public function preHandle(): void {}

    public function setRequireLogin(): void {}

    public function setRequireCapabilities(array $capabilities, string $context = 'system', int $instanceId = 0): void {}

    public function setRequireSesskey(bool $require = true): void
    {
        $this->sesskeyRequired = $require;
    }

    #[Sesskey]
    public function mutate(): void {}
}
