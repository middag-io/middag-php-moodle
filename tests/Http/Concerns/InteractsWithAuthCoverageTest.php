<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Concerns;

use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Framework\Http\Auth\CapabilityReference;
use Middag\Framework\Http\Auth\CapabilityRequirement;
use Middag\Framework\Http\Contract\CapabilityDefinitionInterface;
use Middag\Moodle\Domain\Context\ContextLevel;
use Middag\Moodle\Http\Concerns\InteractsWithAuth;
use Middag\Moodle\Security\Contract\AuthenticationInterface;
use Middag\Moodle\Security\Contract\CapabilityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * InteractsWithAuth is a controller mixin that reads its capability/login flags
 * (populated via the set* setters) and delegates enforcement to the
 * authentication and capability adapters it resolves from the DI container.
 *
 * The trait is exercised through a concrete anonymous class that `use`s it and
 * provides the collaborators the trait reaches for ($container, $course, $cm,
 * $request). Two recording doubles capture the delegated calls so every branch
 * is asserted against observable behaviour without a Moodle runtime.
 *
 * The trait invokes `requireLogin()` / `requireSesskey()` (camelCase) on the
 * object returned by authentication(), matching the methods declared by
 * AuthenticationInterface. The recording authentication double implements those
 * two methods and records each invocation (identifier + args) so the delegated
 * calls are asserted against observable behaviour.
 *
 * @internal
 */
#[CoversClass(InteractsWithAuth::class)]
final class InteractsWithAuthCoverageTest extends TestCase
{
    #[Test]
    public function testSetRequireLoginTriggersLoginWithTheCourseId(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController($this->makeContainer($auth, $this->makeCapability()));

        $course = new class {
            public function get_id(): int
            {
                return 42;
            }
        };

        $controller->setRequireLogin($course, (object) ['id' => 7]);
        $controller->runRequireLogin();

        self::assertContains(['requireLogin', 42, true], $auth->calls);
        self::assertTrue($controller->loginWasRequired());
    }

    #[Test]
    public function testRequireLoginPassesNullCourseIdWhenNoCourseGiven(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController($this->makeContainer($auth, $this->makeCapability()));

        // course stays null → the nullsafe operator short-circuits get_id().
        $controller->setRequireLogin(null, null);
        $controller->runRequireLogin();

        self::assertContains(['requireLogin', null, true], $auth->calls);
        self::assertTrue($controller->loginWasRequired());
    }

    #[Test]
    public function testRequireLoginIsSkippedWhenNotRequired(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController($this->makeContainer($auth, $this->makeCapability()));

        // No setRequireLogin() and no setRequireSesskey(): both guards are false.
        $controller->runRequireLogin();

        self::assertSame([], $auth->calls);
        self::assertFalse($controller->loginWasRequired());
    }

    #[Test]
    public function testSesskeyIsRequiredForNonIdempotentRequests(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController($this->makeContainer($auth, $this->makeCapability()));

        $controller->setRequireSesskey(true);
        $controller->request = Request::create('/submit', 'POST');
        $controller->runRequireLogin();

        self::assertContains(['requireSesskey'], $auth->calls);
    }

    #[Test]
    public function testSesskeyIsNotRequiredForIdempotentRequests(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController($this->makeContainer($auth, $this->makeCapability()));

        $controller->setRequireSesskey(true);
        $controller->request = Request::create('/view', 'GET');
        $controller->runRequireLogin();

        self::assertNotContains(['requireSesskey'], $auth->calls);
    }

    #[Test]
    public function testSesskeyCheckIsSkippedWhenNoRequestIsBound(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController($this->makeContainer($auth, $this->makeCapability()));

        // Sesskey required, but no request bound → isset($this->request) is false.
        $controller->setRequireSesskey(true);
        $controller->runRequireLogin();

        self::assertSame([], $auth->calls);
    }

    #[Test]
    public function testCheckCapabilitiesIsNoopWhenNoCapabilitiesConfigured(): void
    {
        $capability = $this->makeCapability();
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        $controller->runCheckCapabilities();

        self::assertSame([], $capability->calls);
    }

    #[Test]
    public function testCheckCapabilitiesAuthorizesEachCapabilityWithTheGivenContext(): void
    {
        $capability = $this->makeCapability();
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        $controller->setRequireCapabilities(['mod/x:view', 'mod/x:edit'], ContextLevel::Course, 55);
        $controller->runCheckCapabilities();

        self::assertSame([
            ['authorize', 'mod/x:view', ContextLevel::Course, 55],
            ['authorize', 'mod/x:edit', ContextLevel::Course, 55],
        ], $capability->calls);
    }

    #[Test]
    public function testCheckCapabilitiesDefaultsToSystemContextForUnknownContextNames(): void
    {
        $capability = $this->makeCapability();
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        // An UNKNOWN name resolves to null via ContextLevel::fromString(), so the
        // check falls back to the SYSTEM context level.
        $controller->setRequireCapabilities(['mod/x:view'], 'not-a-context-level', 0);
        $controller->runCheckCapabilities();

        self::assertSame([
            ['authorize', 'mod/x:view', ContextLevel::System, 0],
        ], $capability->calls);
    }

    #[Test]
    public function testCheckCapabilitiesDefaultsToSystemContextWhenContextIsOmitted(): void
    {
        $capability = $this->makeCapability();
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        // No third arg at all (neither a ContextLevel nor a string) — the
        // match's `default => null` arm, distinct from the unknown-string-name
        // case above, which goes through ContextLevel::fromString() instead.
        $controller->setRequireCapabilities(['mod/x:view']);
        $controller->runCheckCapabilities();

        self::assertSame([
            ['authorize', 'mod/x:view', ContextLevel::System, 0],
        ], $capability->calls);
    }

    #[Test]
    public function testCheckCapabilitiesResolvesKnownContextNameInsteadOfDegradingToSystem(): void
    {
        $capability = $this->makeCapability();
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        // A KNOWN name string (as #[Auth(context: 'course')] passes) is resolved to
        // the matching ContextLevel — it must NOT silently degrade to SYSTEM.
        $controller->setRequireCapabilities(['mod/x:view'], 'course', 55);
        $controller->runCheckCapabilities();

        self::assertSame([
            ['authorize', 'mod/x:view', ContextLevel::Course, 55],
        ], $capability->calls);
    }

    #[Test]
    public function testCheckCapabilitiesPropagatesTheAuthorizationException(): void
    {
        $capability = $this->makeCapability(new MiddagAuthorizationException('denied'));
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        $controller->setRequireCapabilities(['mod/x:manage'], ContextLevel::System, 0);

        $this->expectException(MiddagAuthorizationException::class);

        $controller->runCheckCapabilities();
    }

    #[Test]
    public function testCheckCapabilitiesResolvesContextPerRichRequirement(): void
    {
        $capability = $this->makeCapability();
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        $controller->setRequireCapabilityRequirements([
            new CapabilityRequirement(reference: new CapabilityReference('mod/x:view'), options: ['contextlevel' => 'course', 'instanceid' => 55]),
            new CapabilityRequirement(reference: new CapabilityReference('mod/x:edit'), options: ['contextlevel' => 'module', 'instanceid' => 9]),
        ]);
        $controller->runCheckCapabilities();

        self::assertSame([
            ['authorize', 'mod/x:view', ContextLevel::Course, 55],
            ['authorize', 'mod/x:edit', ContextLevel::Module, 9],
        ], $capability->calls);
    }

    #[Test]
    public function testRichRequirementFallsBackToClassWideContextAndWinsOverLegacyList(): void
    {
        $capability = $this->makeCapability();
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        // Class-wide context set by the legacy call the kernel also makes; the
        // rich list wins and inherits that context when a requirement omits it.
        $controller->setRequireCapabilities(['legacy/only'], 'course', 7);
        $controller->setRequireCapabilityRequirements([
            new CapabilityRequirement(reference: new CapabilityReference('rich/a')),
        ]);
        $controller->runCheckCapabilities();

        self::assertSame([
            ['authorize', 'rich/a', ContextLevel::Course, 7],
        ], $capability->calls);
    }

    #[Test]
    public function testDefinitionClassOnlyRequirementIsSkipped(): void
    {
        $capability = $this->makeCapability();
        $controller = $this->makeController($this->makeContainer($this->makeAuth(), $capability));

        $controller->setRequireCapabilityRequirements([
            new CapabilityRequirement(definitionClass: InteractsWithAuthFakeDefinition::class),
            new CapabilityRequirement(reference: new CapabilityReference('mod/x:real')),
        ]);
        $controller->runCheckCapabilities();

        self::assertSame([
            ['authorize', 'mod/x:real', ContextLevel::System, 0],
        ], $capability->calls);
    }

    /**
     * Recording authentication double. Implements AuthenticationInterface so it
     * satisfies authentication()'s return type, and records each requireLogin()
     * / requireSesskey() invocation (the methods the trait delegates to) with
     * its arguments so the delegated calls can be asserted.
     */
    private function makeAuth(): object
    {
        return new class implements AuthenticationInterface {
            /** @var list<array<int, mixed>> */
            public array $calls = [];

            public function requireLogin(?int $courseid = null, bool $autologinguest = true): void
            {
                $this->calls[] = ['requireLogin', $courseid, $autologinguest];
            }

            public function isLoggedIn(): bool
            {
                return true;
            }

            public function isGuest(): bool
            {
                return false;
            }

            public function requireSesskey(): void
            {
                $this->calls[] = ['requireSesskey'];
            }
        };
    }

    /**
     * Recording capability double; optionally throws from authorize() to
     * exercise the exception-propagation path.
     */
    private function makeCapability(?Throwable $throw = null): object
    {
        return new class($throw) implements CapabilityInterface {
            /** @var list<array<int, mixed>> */
            public array $calls = [];

            public function __construct(private readonly ?Throwable $throw) {}

            public function can(string $capability, ContextLevel $contextlevel = ContextLevel::System, int $instanceid = 0, ?int $userid = null): bool
            {
                return true;
            }

            public function authorize(string $capability, ContextLevel $contextlevel = ContextLevel::System, int $instanceid = 0, ?int $userid = null): void
            {
                $this->calls[] = ['authorize', $capability, $contextlevel, $instanceid];

                if ($this->throw instanceof Throwable) {
                    throw $this->throw;
                }
            }
        };
    }

    private function makeContainer(object $auth, object $capability): ContainerInterface
    {
        return new class($auth, $capability) implements ContainerInterface {
            public function __construct(private readonly object $auth, private readonly object $capability) {}

            public function get(string $id): mixed
            {
                return match ($id) {
                    AuthenticationInterface::class => $this->auth,
                    CapabilityInterface::class => $this->capability,
                    default => throw new RuntimeException('unexpected service: ' . $id),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [AuthenticationInterface::class, CapabilityInterface::class], true);
            }
        };
    }

    /**
     * Concrete host for the trait: declares the collaborators the trait reads
     * ($container, $course, $cm, $request) and public passthroughs for its two
     * protected guard methods.
     */
    private function makeController(ContainerInterface $container): object
    {
        $controller = new class {
            use InteractsWithAuth;

            public ContainerInterface $container;

            public mixed $course = null;

            public mixed $cm = null;

            public ?Request $request = null;

            public function runRequireLogin(): void
            {
                $this->requireLogin();
            }

            public function runCheckCapabilities(): void
            {
                $this->checkCapabilities();
            }

            public function loginWasRequired(): bool
            {
                return $this->requiredLogin;
            }
        };

        $controller->container = $container;

        return $controller;
    }
}

/**
 * Rich capability definition double for the definition-class-only skip path.
 *
 * @internal
 */
final class InteractsWithAuthFakeDefinition implements CapabilityDefinitionInterface
{
    public function capabilityReference(): CapabilityReference
    {
        return new CapabilityReference('def/cap', host: 'moodle');
    }

    public function capabilityOptions(): array
    {
        return ['contextlevel' => 'module'];
    }
}
