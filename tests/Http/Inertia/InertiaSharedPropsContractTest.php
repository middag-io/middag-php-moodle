<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Inertia;

use Composer\InstalledVersions;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Moodle\Http\Inertia\InertiaSharedProps;
use Middag\Moodle\Runtime\ContainerFactory;
use Middag\Moodle\Runtime\Kernel;
use Middag\Ui\Navigation\Contract\NavigationRegistryInterface;
use Middag\Ui\Navigation\NavigationTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contract test: the default shared props match the ui shared-props schema.
 *
 * Renders a real X-Inertia JSON response with only the props register() shares
 * (no theme string overrides, no validation errors, no scope data) and checks
 * the encoded page props against the SharedProps definition shipped by
 * middag-io/ui. Guards the empty-map shape: theme.strings, errors and scope
 * must go out as JSON objects (`{}`), never as arrays (`[]`).
 *
 * No JSON-schema validator is a dependency of this package, so the slots are
 * checked structurally against the `type` the schema declares for them.
 *
 * @internal
 */
#[CoversClass(InertiaSharedProps::class)]
final class InertiaSharedPropsContractTest extends TestCase
{
    private const string SCHEMA_FILE = '/schema/shared-props.json';

    private mixed $prevUser = null;

    private mixed $prevPage = null;

    /** @var array<string, mixed> */
    private array $prevServer = [];

    protected function setUp(): void
    {
        $this->prevUser = $GLOBALS['USER'] ?? null;
        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $this->prevServer = $_SERVER;

        $this->resetKernelWorld();

        $_SERVER['HTTP_HOST'] = 'moodle.test';
        $_SERVER['SERVER_NAME'] = 'moodle.test';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $GLOBALS['USER'] = (object) ['id' => 2];
        $GLOBALS['PAGE'] = new stdClass();
    }

    protected function tearDown(): void
    {
        $this->resetKernelWorld();

        $GLOBALS['USER'] = $this->prevUser;
        $GLOBALS['PAGE'] = $this->prevPage;
        $_SERVER = $this->prevServer;
    }

    #[Test]
    public function emptyDefaultMapsAreEncodedAsJsonObjects(): void
    {
        $json = $this->renderDefaultPageJson();

        self::assertStringContainsString('"strings":{}', $json);
        self::assertStringContainsString('"errors":{}', $json);
        self::assertStringContainsString('"scope":{}', $json);
    }

    #[Test]
    public function defaultSharedPropsMatchTheUiSchemaObjectSlots(): void
    {
        $definitions = $this->schemaDefinitions();
        $sharedProps = $definitions['SharedProps'];
        $props = $this->decodedProps($this->renderDefaultPageJson());

        self::assertSame('object', $sharedProps['type']);
        self::assertInstanceOf(stdClass::class, $props);

        // scope: inline `type: object`.
        self::assertObjectHasProperty('scope', $props);
        self::assertSame('object', $sharedProps['properties']['scope']['type']);
        self::assertInstanceOf(stdClass::class, $props->scope, '/scope must be object');

        // theme: SharedTheme, whose strings is `type: object`.
        $theme = $this->resolve($definitions, $sharedProps['properties']['theme']);
        self::assertSame('object', $theme['type']);
        self::assertObjectHasProperty('theme', $props);
        self::assertInstanceOf(stdClass::class, $props->theme, '/theme must be object');
        self::assertSame([], array_diff(array_keys(get_object_vars($props->theme)), array_keys($theme['properties'])));
        self::assertSame('object', $theme['properties']['strings']['type']);
        self::assertObjectHasProperty('strings', $props->theme);
        self::assertInstanceOf(stdClass::class, $props->theme->strings, '/theme/strings must be object');

        // errors: anyOf FormErrors | FormErrorBags, both `type: object`.
        $branches = $sharedProps['properties']['errors']['anyOf'];
        self::assertNotEmpty($branches);
        foreach ($branches as $branch) {
            self::assertSame('object', $this->resolve($definitions, $branch)['type']);
        }
        self::assertObjectHasProperty('errors', $props);
        self::assertInstanceOf(stdClass::class, $props->errors, '/errors must be object');
    }

    /**
     * Register the default shared props and render an X-Inertia JSON page.
     */
    private function renderDefaultPageJson(): string
    {
        $this->injectKernel();

        $registry = self::createStub(NavigationRegistryInterface::class);
        $registry->method('build')->willReturn(new NavigationTree([], ''));
        Kernel::instance(NavigationRegistryInterface::class, $registry);

        InertiaSharedProps::register();

        $request = Request::create('/', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
        $json = InertiaFactory::render('Page', [], $request)->toResponse()->getContent();
        self::assertIsString($json);

        return $json;
    }

    private function decodedProps(string $json): mixed
    {
        $page = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $page);
        self::assertObjectHasProperty('props', $page);

        return $page->props;
    }

    /**
     * The `$defs` of the ui shared-props schema, with the root `$ref` checked.
     *
     * @return array<string, array<string, mixed>>
     */
    private function schemaDefinitions(): array
    {
        $path = InstalledVersions::getInstallPath('middag-io/ui') . self::SCHEMA_FILE;
        self::assertFileExists($path);

        $schema = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($schema);
        self::assertSame('#/$defs/SharedProps', $schema['$ref']);

        return $schema['$defs'];
    }

    /**
     * Follow a local `#/$defs/<Name>` reference.
     *
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, mixed>                $node
     *
     * @return array<string, mixed>
     */
    private function resolve(array $definitions, array $node): array
    {
        if (!isset($node['$ref'])) {
            return $node;
        }

        $name = substr((string) $node['$ref'], \strlen('#/$defs/'));
        self::assertArrayHasKey($name, $definitions);

        return $definitions[$name];
    }

    private function injectKernel(): void
    {
        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);
        $reflection->getProperty('instance')->setValue(null, $kernel);
    }

    private function resetKernelWorld(): void
    {
        Kernel::shutdown();

        $factory = new ReflectionClass(ContainerFactory::class);
        $factory->setStaticPropertyValue('container', null);
        $factory->setStaticPropertyValue('builder', null);
        $factory->setStaticPropertyValue('resetCallbacks', []);

        InertiaManager::flush();
    }
}
