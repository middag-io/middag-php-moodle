<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use core\context;
use core\exception\coding_exception;
use core\output\renderer_base;
use core\url as moodle_url;
use Middag\Moodle\Support\PageSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * PageSupport delegates to the global $PAGE / $OUTPUT / $CFG. Each is replaced
 * with a recording double so the wrapper's effect is observable without a Moodle
 * runtime.
 *
 * @internal
 */
#[CoversClass(PageSupport::class)]
final class PageSupportCoverageTest extends TestCase
{
    private object $page;

    private object $output;

    private mixed $prevPage;

    private mixed $prevOutput;

    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->page = $this->makePage();
        $this->output = new class {
            public function header(): string
            {
                return '[header]';
            }

            public function footer(): string
            {
                return '[footer]';
            }
        };

        // adminExternalpageSetup / adminLoadNavigation require_once $CFG->libdir.'/adminlib.php';
        // point libdir at a temp directory holding an empty adminlib so the include resolves.
        $libdir = sys_get_temp_dir() . '/middag_page_support_test';
        if (!is_dir($libdir)) {
            mkdir($libdir, 0o777, true);
        }
        file_put_contents($libdir . '/adminlib.php', "<?php\n");

        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $this->prevOutput = $GLOBALS['OUTPUT'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        $GLOBALS['PAGE'] = $this->page;
        $GLOBALS['OUTPUT'] = $this->output;
        $GLOBALS['CFG'] = (object) ['libdir' => $libdir];

        unset($GLOBALS['__middag_test_admin_externalpage']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['PAGE'] = $this->prevPage;
        $GLOBALS['OUTPUT'] = $this->prevOutput;
        $GLOBALS['CFG'] = $this->prevCfg;
    }

    #[Test]
    public function testSetContextForwardsToPage(): void
    {
        $ctx = new context(5);

        PageSupport::setContext($ctx);

        self::assertSame($ctx, $this->page->calls['context']);
    }

    #[Test]
    public function testSetPagelayoutForwardsToPage(): void
    {
        PageSupport::setPagelayout('admin');

        self::assertSame('admin', $this->page->calls['layout']);
    }

    #[Test]
    public function testSetTitleForwardsToPage(): void
    {
        PageSupport::setTitle('My title');

        self::assertSame('My title', $this->page->calls['title']);
    }

    #[Test]
    public function testSetHeadingForwardsToPage(): void
    {
        PageSupport::setHeading('My heading');

        self::assertSame('My heading', $this->page->calls['heading']);
    }

    #[Test]
    public function testSetSecondaryNavigationForwardsToPage(): void
    {
        PageSupport::setSecondaryNavigation(false);

        self::assertFalse($this->page->calls['secondary']);
    }

    #[Test]
    public function testSetUrlForwardsToPage(): void
    {
        $url = new moodle_url('https://moodle.test/admin');

        PageSupport::setUrl($url);

        self::assertSame($url, $this->page->calls['url']);
    }

    #[Test]
    public function testNavbarAddForwardsToTheNavbar(): void
    {
        PageSupport::navbarAdd('Home', 'https://moodle.test');

        self::assertSame([['Home', 'https://moodle.test']], $this->page->navbar->added);
    }

    #[Test]
    public function testGetRendererReturnsARendererBase(): void
    {
        self::assertInstanceOf(renderer_base::class, PageSupport::getRenderer('core'));
    }

    #[Test]
    public function testAdminExternalpageSetupIncludesAdminlibAndSetsUp(): void
    {
        PageSupport::adminExternalpageSetup('mysection');

        self::assertSame('mysection', $GLOBALS['__middag_test_admin_externalpage']);
    }

    #[Test]
    public function testPageMarkdownRendersContentWithAProvidedContext(): void
    {
        $ctx = new context(1);

        ob_start();
        PageSupport::pageMarkdown('# Hello', new moodle_url('https://moodle.test'), 'Docs', $ctx);
        $rendered = ob_get_clean();

        self::assertStringContainsString('<markdown># Hello</markdown>', (string) $rendered);
        self::assertStringContainsString('[header]', (string) $rendered);
        self::assertStringContainsString('[footer]', (string) $rendered);
        self::assertSame($ctx, $this->page->calls['context']);
        self::assertSame('Docs', $this->page->calls['title']);
    }

    #[Test]
    public function testPageMarkdownDefaultsToTheSystemContext(): void
    {
        ob_start();
        PageSupport::pageMarkdown('body', 'https://moodle.test', 'Title');
        $rendered = ob_get_clean();

        self::assertStringContainsString('[header]', (string) $rendered);
        self::assertInstanceOf(context::class, $this->page->calls['context']);
    }

    #[Test]
    public function testPageMarkdownTracesACodingExceptionFromSetUrl(): void
    {
        $this->page->throwOnSetUrl = true;

        ob_start();
        PageSupport::pageMarkdown('body', 'https://moodle.test', 'Title', new context(1));
        $rendered = ob_get_clean();

        // set_url threw a coding_exception; the wrapper traces it and keeps rendering.
        self::assertStringContainsString('[footer]', (string) $rendered);
        self::assertSame('Title', $this->page->calls['title']);
    }

    #[Test]
    public function testAdminLoadNavigationWalksTheSettingsTreeAndBuildsTheNavbar(): void
    {
        // Drive the full admin-tree traversal: admin_get_root()->locate() yields a
        // path, and $PAGE->settingsnav->get() is walked, adding nodes beyond $jump.
        $GLOBALS['__middag_test_admin_root'] = new class {
            public function locate(string $section, bool $strict = false): stdClass
            {
                return (object) ['path' => ['a', 'b', 'c', 'd']];
            }
        };
        $this->page->settingsnav = new class {
            public string $text = 'Node';

            public mixed $action = 'https://moodle.test/node';

            public function get(mixed $key): object
            {
                return $this;
            }
        };

        PageSupport::adminLoadNavigation('mysection');

        self::assertSame('admin', $this->page->calls['layout']);
        // path has 4 elements, jump defaults to 2 → nodes 3 and 4 are added.
        self::assertCount(2, $this->page->navbar->added);
        self::assertSame([true], $this->page->navbar->ignored);

        unset($GLOBALS['__middag_test_admin_root']);
    }

    #[Test]
    public function testAdminLoadNavigationSkipsTheNavbarWhenThereIsNoSettingsTree(): void
    {
        // settingsnav is null → the while loop is skipped; the active node is
        // still ignored per the default flag.
        $GLOBALS['__middag_test_admin_root'] = new class {
            public function locate(string $section, bool $strict = false): stdClass
            {
                return (object) ['path' => ['a', 'b']];
            }
        };
        $this->page->settingsnav = null;

        PageSupport::adminLoadNavigation('mysection', 2, false);

        self::assertCount(0, $this->page->navbar->added);
        // $ignoreactive is false → the guard is exercised but ignore_active() is skipped.
        self::assertSame([], $this->page->navbar->ignored);

        unset($GLOBALS['__middag_test_admin_root']);
    }

    private function makePage(): object
    {
        return new class {
            /** @var array<string, mixed> */
            public array $calls = [];

            public bool $throwOnSetUrl = false;

            public object $navbar;

            public object $requires;

            public mixed $settingsnav = null;

            public function __construct()
            {
                $this->navbar = new class {
                    /** @var array<int, array{0: string, 1: mixed}> */
                    public array $added = [];

                    /** @var array<int, mixed> */
                    public array $ignored = [];

                    public function add(string $text, mixed $action = null): void
                    {
                        $this->added[] = [$text, $action];
                    }

                    public function ignore_active(mixed $value = true): void
                    {
                        $this->ignored[] = $value;
                    }
                };

                $this->requires = new class {
                    /** @var array<int, string> */
                    public array $js = [];

                    public function js_amd_inline(string $code): void
                    {
                        $this->js[] = $code;
                    }
                };
            }

            public function set_context(object $context): void
            {
                $this->calls['context'] = $context;
            }

            public function set_pagelayout(string $layout): void
            {
                $this->calls['layout'] = $layout;
            }

            public function set_title(string $title): void
            {
                $this->calls['title'] = $title;
            }

            public function set_heading(string $heading): void
            {
                $this->calls['heading'] = $heading;
            }

            public function set_secondary_navigation(bool $enabled): void
            {
                $this->calls['secondary'] = $enabled;
            }

            public function set_url(mixed $url): void
            {
                if ($this->throwOnSetUrl) {
                    throw new coding_exception('bad url');
                }

                $this->calls['url'] = $url;
            }

            public function get_renderer(string $component): renderer_base
            {
                $this->calls['renderer'] = $component;

                return new renderer_base();
            }
        };
    }
}
