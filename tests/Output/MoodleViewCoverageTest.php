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

use core\url as moodle_url;
use Middag\Moodle\Output\Contract\ViewAdapterInterface;
use Middag\Moodle\Output\MoodleView;
use navigation_node;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test MoodleView.
 *
 * Delegates to Moodle's global $PAGE and $OUTPUT; both are replaced here with
 * recording doubles so each adapter method's effect is observable.
 *
 * @internal
 */
#[CoversClass(MoodleView::class)]
final class MoodleViewCoverageTest extends TestCase
{
    private MoodleView $view;

    private object $page;

    private object $output;

    private mixed $prevPage;

    private mixed $prevOutput;

    protected function setUp(): void
    {
        $this->view = new MoodleView();

        $this->page = new class {
            public array $calls = [];

            public object $navbar;

            public function __construct()
            {
                $this->navbar = new class {
                    /** @var array<int, array{0: string, 1: mixed, 2: mixed}> */
                    public array $added = [];

                    public function add(string $text, mixed $url = null, mixed $type = null): void
                    {
                        $this->added[] = [$text, $url, $type];
                    }
                };
            }

            public function set_title(string $title): void
            {
                $this->calls['title'] = $title;
            }

            public function set_heading(string $heading): void
            {
                $this->calls['heading'] = $heading;
            }

            public function set_pagelayout(string $layout): void
            {
                $this->calls['layout'] = $layout;
            }
        };

        $this->output = new class {
            /** @var array<int, array{0: string, 1: array}> */
            public array $rendered = [];

            public function render_from_template(string $name, array $data): string
            {
                $this->rendered[] = [$name, $data];

                return 'TPL:' . $name;
            }

            public function header(): string
            {
                return 'HEADER|';
            }

            public function footer(): string
            {
                return '|FOOTER';
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
    public function setTitleForwardsToPage(): void
    {
        $this->view->setTitle('My title');

        $this->assertSame('My title', $this->page->calls['title']);
    }

    #[Test]
    public function setHeadingForwardsToPage(): void
    {
        $this->view->setHeading('My heading');

        $this->assertSame('My heading', $this->page->calls['heading']);
    }

    #[Test]
    public function setLayoutForwardsToPage(): void
    {
        $this->view->setLayout('standard');

        $this->assertSame('standard', $this->page->calls['layout']);
    }

    #[Test]
    public function addBreadcrumbWithStringUrlWrapsItInAMoodleUrl(): void
    {
        $this->view->addBreadcrumb('Home', '/local/example/index.php');

        [$text, $url, $type] = $this->page->navbar->added[0];
        $this->assertSame('Home', $text);
        $this->assertInstanceOf(moodle_url::class, $url);
        $this->assertSame('/local/example/index.php', (string) $url);
        $this->assertSame(navigation_node::TYPE_CUSTOM, $type);
    }

    #[Test]
    public function addBreadcrumbWithMoodleUrlKeepsTheInstance(): void
    {
        $moodleUrl = new moodle_url('/dashboard');

        $this->view->addBreadcrumb('Dash', $moodleUrl);

        $this->assertSame($moodleUrl, $this->page->navbar->added[0][1]);
    }

    #[Test]
    public function addBreadcrumbWithNullUrlPassesNull(): void
    {
        $this->view->addBreadcrumb('Plain');

        $this->assertNull($this->page->navbar->added[0][1]);
    }

    #[Test]
    public function renderTemplateDelegatesToOutput(): void
    {
        $result = $this->view->renderTemplate('local_example/page', ['x' => 1]);

        $this->assertSame('TPL:local_example/page', $result);
        $this->assertSame(['local_example/page', ['x' => 1]], $this->output->rendered[0]);
    }

    #[Test]
    public function renderPageWrapsContentBetweenHeaderAndFooter(): void
    {
        $this->assertSame('HEADER|BODY|FOOTER', $this->view->renderPage('BODY'));
    }

    #[Test]
    public function implementsViewAdapterInterface(): void
    {
        $this->assertInstanceOf(ViewAdapterInterface::class, $this->view);
    }
}
