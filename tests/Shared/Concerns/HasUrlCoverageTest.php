<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Shared\Concerns;

use core\url as moodle_url;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Kernel\Kernel;
use Middag\Moodle\Shared\Concerns\HasUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * HasUrl gives entities self-URL generation, delegating every route to the
 * central Kernel router facade. A recording RouterInterface is injected into a
 * reflection-built Kernel singleton so each helper's route name + params are
 * asserted without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(HasUrl::class)]
final class HasUrlCoverageTest extends TestCase
{
    /** @var array<int, array{route: string, params: array<string, mixed>}> */
    private array $calls = [];

    protected function tearDown(): void
    {
        Kernel::shutdown();
    }

    #[Test]
    public function getUrlBuildsTheConventionRouteFromTypeAndAction(): void
    {
        $item = $this->makeEntity('generic', 123);

        $url = $item->getUrl('edit');

        self::assertInstanceOf(moodle_url::class, $url);
        self::assertSame('middag.generic.edit', $this->calls[0]['route']);
        self::assertSame(['id' => 123], $this->calls[0]['params']);
    }

    #[Test]
    public function getUrlDefaultsTheTypeToItemWhenGetTypeIsAbsent(): void
    {
        // A plain entity without get_type() falls back to the 'item' type.
        $item = $this->makeEntityWithoutType(7);

        $item->getUrl('view');

        self::assertSame('middag.item.view', $this->calls[0]['route']);
        self::assertSame(['id' => 7], $this->calls[0]['params']);
    }

    #[Test]
    public function getUrlTreatsADottedActionAsAFullRouteName(): void
    {
        $item = $this->makeEntity('generic', 5);

        $item->getUrl('custom.route.name');

        self::assertSame('custom.route.name', $this->calls[0]['route']);
    }

    #[Test]
    public function getUrlKeepsAnExplicitIdAndOmitsItWhenNull(): void
    {
        $withExplicit = $this->makeEntity('generic', 123);
        $withExplicit->getUrl('view', ['id' => 999]);
        self::assertSame(['id' => 999], $this->calls[0]['params']);

        $nullId = $this->makeEntity('generic', null);
        $nullId->getUrl('view');
        self::assertSame([], $this->calls[1]['params']);
    }

    #[Test]
    public function getViewUrlAndGetEditUrlDelegateToGetUrl(): void
    {
        $item = $this->makeEntity('generic', 1);

        $item->getViewUrl();
        $item->getEditUrl();

        self::assertSame('middag.generic.view', $this->calls[0]['route']);
        self::assertSame('middag.generic.edit', $this->calls[1]['route']);
    }

    #[Test]
    public function getWebhookUrlPrefixesTheRouteAndInjectsTheId(): void
    {
        $item = $this->makeEntity('generic', 42);

        $item->getWebhookUrl('sync');
        self::assertSame('webhook.sync', $this->calls[0]['route']);
        self::assertSame(['id' => 42], $this->calls[0]['params']);

        $item->getWebhookUrl('full.route', ['id' => 8]);
        self::assertSame('full.route', $this->calls[1]['route']);
        self::assertSame(['id' => 8], $this->calls[1]['params']);
    }

    /**
     * A HasUrl-consuming entity exposing get_type()/get_id(), wired to a Kernel
     * whose router records generateUrl() calls.
     */
    private function makeEntity(string $type, ?int $id): object
    {
        $this->bootRouter();

        return new class($type, $id) {
            use HasUrl;

            public function __construct(private readonly string $type, private readonly ?int $id) {}

            public function get_type(): string
            {
                return $this->type;
            }

            public function get_id(): ?int
            {
                return $this->id;
            }
        };
    }

    /** A HasUrl consumer with no get_type() hook (type -> 'item'). */
    private function makeEntityWithoutType(int $id): object
    {
        $this->bootRouter();

        return new class($id) {
            use HasUrl;

            public function __construct(public int $publicId) {}

            public function get_id(): int
            {
                return $this->publicId;
            }
        };
    }

    /**
     * Inject a Kernel singleton whose router records every generateUrl() call
     * and echoes a deterministic path, so HasUrl's delegation is observable.
     */
    private function bootRouter(): void
    {
        $calls = &$this->calls;

        $router = new class($calls) implements RouterInterface {
            /** @param array<int, array{route: string, params: array<string, mixed>}> $recorded */
            public function __construct(private array &$recorded) {}

            public function initializeContext(): void {}

            public function getRoutes(): RouteCollection
            {
                return new RouteCollection();
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            public function registerDefaultRoutes(): void {}

            public function scanAnnotations(ContainerInterface $container, ?string $specificClass = null): void {}

            public function generateUrl(string $route, array $parameters = [], int $reference_type = 1): string
            {
                $this->recorded[] = ['route' => $route, 'params' => $parameters];

                return '/r/' . $route;
            }
        };

        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);
        $reflection->getProperty('router')->setValue($kernel, $router);
        $reflection->getProperty('instance')->setValue(null, $kernel);
    }
}
