<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Routing;

use Middag\Moodle\Http\Routing\PluginAwareUrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * PluginAwareUrlGenerator overrides Symfony's UrlGenerator::generate() to pick a
 * per-route base URL. Every test drives a genuine RouteCollection + a recording
 * RequestContext (an anonymous subclass that logs each setBaseUrl() call), so the
 * chosen base URL, the restore-on-return contract, and the direct/swap branch
 * split are all observable from the generated string and the recorded call log.
 *
 * For ABSOLUTE_PATH (the default reference type) Symfony renders
 * `context.baseUrl . routePath`, so the base URL in effect at generation time is
 * directly visible in the returned URL — no Moodle runtime involved.
 *
 * @internal
 */
#[CoversClass(PluginAwareUrlGenerator::class)]
final class PluginAwareUrlGeneratorCoverageTest extends TestCase
{
    private const DEFAULT_BASE = '/local/example/index.php';

    #[Test]
    public function testRouteWithoutPluginBaseSwapsToDefaultBaseThenRestores(): void
    {
        // Route carries no _plugin_base, and the context base differs from the
        // default: the else branch picks the default base, swaps it in, generates,
        // and the finally clause restores the original context base.
        $routes = new RouteCollection();
        $routes->add('plain', new Route('/plain'));

        $context = $this->recordingContext('/some/other/base');
        $generator = new PluginAwareUrlGenerator($routes, $context);

        $url = $generator->generate('plain');

        self::assertSame('/local/example/index.php/plain', $url);
        self::assertSame('/some/other/base', $context->getBaseUrl(), 'original base must be restored');
        self::assertSame([self::DEFAULT_BASE, '/some/other/base'], $context->setCalls, 'swap then restore');
    }

    #[Test]
    public function testRouteWithPluginBaseSwapsToDeclaredEntryPointThenRestores(): void
    {
        // Route declares _plugin_base: the generator honours that entry point
        // instead of the default MIDDAG base, then restores the original context.
        $routes = new RouteCollection();
        $routes->add('plugin', new Route('/dashboard', ['_plugin_base' => '/local/yourplugin/index.php']));

        $context = $this->recordingContext(self::DEFAULT_BASE);
        $generator = new PluginAwareUrlGenerator($routes, $context);

        $url = $generator->generate('plugin');

        self::assertSame('/local/yourplugin/index.php/dashboard', $url);
        self::assertSame(self::DEFAULT_BASE, $context->getBaseUrl(), 'original base must be restored');
        self::assertSame(['/local/yourplugin/index.php', self::DEFAULT_BASE], $context->setCalls, 'swap then restore');
    }

    #[Test]
    public function testRouteWithPluginBaseEqualToContextBaseGeneratesDirectlyWithoutSwapping(): void
    {
        // _plugin_base already equals the context base: target === original, so the
        // generator returns parent::generate() directly and never touches setBaseUrl.
        $routes = new RouteCollection();
        $routes->add('plugin', new Route('/dashboard', ['_plugin_base' => '/local/yourplugin/index.php']));

        $context = $this->recordingContext('/local/yourplugin/index.php');
        $generator = new PluginAwareUrlGenerator($routes, $context);

        $url = $generator->generate('plugin');

        self::assertSame('/local/yourplugin/index.php/dashboard', $url);
        self::assertSame([], $context->setCalls, 'no swap when target already equals current base');
    }

    #[Test]
    public function testRouteWithoutPluginBaseEqualToDefaultGeneratesDirectlyWithoutSwapping(): void
    {
        // No _plugin_base and the context base already equals the default base:
        // else branch + target === original → direct generate, no setBaseUrl call.
        $routes = new RouteCollection();
        $routes->add('plain', new Route('/plain'));

        $context = $this->recordingContext(self::DEFAULT_BASE);
        $generator = new PluginAwareUrlGenerator($routes, $context);

        $url = $generator->generate('plain');

        self::assertSame('/local/example/index.php/plain', $url);
        self::assertSame([], $context->setCalls, 'no swap when default already equals current base');
    }

    #[Test]
    public function testCustomDefaultBaseUrlIsHonouredForRoutesWithoutPluginBase(): void
    {
        // The third constructor arg overrides the fallback base for plugin-less routes.
        $routes = new RouteCollection();
        $routes->add('plain', new Route('/plain'));

        $context = $this->recordingContext('/some/other/base');
        $generator = new PluginAwareUrlGenerator($routes, $context, '/mod/unidade/view.php');

        $url = $generator->generate('plain');

        self::assertSame('/mod/unidade/view.php/plain', $url);
        self::assertSame('/some/other/base', $context->getBaseUrl(), 'original base must be restored');
        self::assertSame(['/mod/unidade/view.php', '/some/other/base'], $context->setCalls);
    }

    #[Test]
    public function testReferenceTypeIsForwardedToParentWhileBaseIsSwapped(): void
    {
        // ABSOLUTE_URL must pass through to the parent generator: the scheme/host
        // authority is prepended in front of the swapped-in plugin base.
        $routes = new RouteCollection();
        $routes->add('plugin', new Route('/dashboard', ['_plugin_base' => '/local/yourplugin/index.php']));

        $context = $this->recordingContext(self::DEFAULT_BASE);
        $generator = new PluginAwareUrlGenerator($routes, $context);

        $url = $generator->generate('plugin', [], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertSame('http://localhost/local/yourplugin/index.php/dashboard', $url);
        self::assertSame(self::DEFAULT_BASE, $context->getBaseUrl(), 'original base must be restored');
    }

    #[Test]
    public function testFinallyRestoresBaseWhenParentGenerateThrows(): void
    {
        // Unknown route name after the base has been swapped: parent::generate()
        // throws inside the try block; the finally clause must still restore the
        // original context base (and the swap+restore pair is recorded).
        $routes = new RouteCollection();
        $routes->add('plain', new Route('/plain'));

        $context = $this->recordingContext('/some/other/base');
        $generator = new PluginAwareUrlGenerator($routes, $context);

        try {
            $generator->generate('ghost');
            self::fail('expected RouteNotFoundException for an unregistered route');
        } catch (RouteNotFoundException) {
            // expected
        }

        self::assertSame('/some/other/base', $context->getBaseUrl(), 'base restored even when generation throws');
        self::assertSame([self::DEFAULT_BASE, '/some/other/base'], $context->setCalls, 'swap then restore on exception');
    }

    /**
     * A RequestContext that records every setBaseUrl() argument. The single call
     * made by the parent constructor is discarded so setCalls reflects only what
     * the generator does during generate().
     */
    private function recordingContext(string $baseUrl): RequestContext
    {
        $context = new class($baseUrl) extends RequestContext {
            /** @var list<string> */
            public array $setCalls = [];

            public function setBaseUrl(string $baseUrl): static
            {
                $this->setCalls[] = $baseUrl;

                return parent::setBaseUrl($baseUrl);
            }
        };

        $context->setCalls = [];

        return $context;
    }
}
