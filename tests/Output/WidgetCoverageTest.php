<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Output;

use core\output\renderable;
use core\output\templatable;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Output\Widget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test Widget.
 *
 * A renderable that mounts a front-end component through the '{component}/launcher'
 * AMD module. The owning component defaults to ComponentContext::name() and the
 * DOM id comes from html_writer::random_id (both stubbed in tests/bootstrap.php).
 * export_for_template registers the AMD call on the global $PAGE.
 *
 * @internal
 */
#[CoversClass(Widget::class)]
final class WidgetCoverageTest extends TestCase
{
    private object $page;

    private mixed $prevPage;

    protected function setUp(): void
    {
        $this->page = new class {
            public object $requires;

            /** @var array<int, array{0: string, 1: string, 2: array}> */
            public array $amdCalls = [];

            public function __construct()
            {
                $parent = $this;
                $this->requires = new class($parent) {
                    public function __construct(private readonly object $parent) {}

                    public function js_call_amd(string $module, string $method, array $args): void
                    {
                        $this->parent->amdCalls[] = [$module, $method, $args];
                    }
                };
            }
        };

        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $GLOBALS['PAGE'] = $this->page;
    }

    protected function tearDown(): void
    {
        $GLOBALS['PAGE'] = $this->prevPage;
    }

    #[Test]
    public function defaultsOwningComponentToTheConfiguredComponent(): void
    {
        $widget = new Widget('MyComponent', ['a' => 1]);

        $this->assertSame(ComponentContext::name(), $widget->component);
        $this->assertSame('MyComponent', $widget->vuecomponent);
        $this->assertSame(['a' => 1], $widget->params);
    }

    #[Test]
    public function acceptsAnExplicitOwningComponent(): void
    {
        $widget = new Widget('MyComponent', [], 'mod_unidade');

        $this->assertSame('mod_unidade', $widget->component);
    }

    #[Test]
    public function generatesAPrefixedModuleId(): void
    {
        $widget = new Widget('MyComponent');

        $this->assertStringStartsWith('middag-module-', $widget->module_id);
    }

    #[Test]
    public function exportForTemplateRegistersTheLauncherAmdCall(): void
    {
        $widget = new Widget('MyComponent', ['prop' => 'v'], 'local_example');

        $data = $widget->export_for_template(null);

        $this->assertSame([['module_id' => $widget->module_id]], $data['modules']);
        $this->assertSame(
            ['local_example/launcher', 'init', ['MyComponent', $widget->module_id, ['prop' => 'v']]],
            $this->page->amdCalls[0]
        );
    }

    #[Test]
    public function isRenderableAndTemplatable(): void
    {
        $widget = new Widget('MyComponent');

        $this->assertInstanceOf(renderable::class, $widget);
        $this->assertInstanceOf(templatable::class, $widget);
    }
}
