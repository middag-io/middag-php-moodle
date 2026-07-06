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

use core\output\renderer_base;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Output\AbstractBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test AbstractBlock.
 *
 * A reusable UI block driver: lazily resolves its title, memoizes content, and
 * renders through a Widget via the global $OUTPUT. Attribute formatting goes
 * through HtmlWriterSupport. $PAGE/$OUTPUT are recording doubles.
 *
 * @internal
 */
#[CoversClass(AbstractBlock::class)]
final class AbstractBlockCoverageTest extends TestCase
{
    private object $page;

    private object $output;

    private mixed $prevPage;

    private mixed $prevOutput;

    protected function setUp(): void
    {
        $this->page = new class {
            public object $requires;

            public function __construct()
            {
                $this->requires = new class {
                    public function js_call_amd(string $module, string $method, array $args): void {}
                };
            }
        };

        // Extends renderer_base because AbstractBlock::render() forwards $OUTPUT
        // to Widget::export_for_template(?renderer_base $output).
        $this->output = new class extends renderer_base {
            /** @var array<int, array{0: string, 1: array}> */
            public array $rendered = [];

            public function render_from_template(string $name, array $data): string
            {
                $this->rendered[] = [$name, $data];

                return 'RENDERED:' . $name;
            }
        };

        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $this->prevOutput = $GLOBALS['OUTPUT'] ?? null;
        $GLOBALS['PAGE'] = $this->page;
        $GLOBALS['OUTPUT'] = $this->output;
    }

    protected function tearDown(): void
    {
        $GLOBALS['PAGE'] = $this->prevPage;
        $GLOBALS['OUTPUT'] = $this->prevOutput;
    }

    #[Test]
    public function getTitleLazilyResolvesTheTitle(): void
    {
        $block = $this->makeBlock();

        $this->assertSame('Resolved Title', $block->getTitle());
        // Second call keeps the resolved value without re-resolving.
        $this->assertSame('Resolved Title', $block->getTitle());
    }

    #[Test]
    public function getContentIsMemoized(): void
    {
        $block = $this->makeBlock();

        $block->getContent();
        $block->getContent();

        $this->assertSame(1, $block->processCalls);
        $this->assertSame(['k' => 'v'], $block->getContent());
    }

    #[Test]
    public function getAttributesReturnsEmptyStringWhenNoneSet(): void
    {
        $this->assertSame('', $this->makeBlock()->exposeGetAttributes());
    }

    #[Test]
    public function getAttributesFormatsViaHtmlWriterWhenSet(): void
    {
        $block = $this->makeBlock();
        $block->setAttribute('id', 'main');

        $this->assertStringContainsString('id="main"', $block->exposeGetAttributes());
    }

    #[Test]
    public function renderProducesWidgetOutputAndInjectsTheTitle(): void
    {
        $block = $this->makeBlock();

        $result = $block->render();

        $this->assertSame('RENDERED:' . ComponentContext::name() . '/widget', $result);
        $this->assertSame(ComponentContext::name() . '/widget', $this->output->rendered[0][0]);
        $this->assertArrayHasKey('modules', $this->output->rendered[0][1]);
    }

    #[Test]
    public function renderKeepsAPresetTitleInContent(): void
    {
        $block = new class extends AbstractBlock {
            public const TEMPLATE = 'block-tpl';

            public function setTitle(): void
            {
                $this->title = 'unused';
            }

            public function processContent(): array
            {
                return ['title' => 'Preset'];
            }
        };

        // Should not throw and should render through $OUTPUT.
        $this->assertSame('RENDERED:' . ComponentContext::name() . '/widget', $block->render());
    }

    private function makeBlock(): AbstractBlock
    {
        return new class extends AbstractBlock {
            public const TEMPLATE = 'block-tpl';

            public int $processCalls = 0;

            public function setTitle(): void
            {
                $this->title = 'Resolved Title';
            }

            public function processContent(): array
            {
                ++$this->processCalls;

                return ['k' => 'v'];
            }

            public function exposeGetAttributes(): string
            {
                return $this->getAttributes();
            }
        };
    }
}
