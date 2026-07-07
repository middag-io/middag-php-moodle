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

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Output\NavbarService;
use Middag\Moodle\Support\VersionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Test NavbarService.
 *
 * Builds the MIDDAG navbar dropdown context and renders it through the global
 * $OUTPUT. Capability gating (has_capability) and the Bootstrap 4/5 split
 * (VersionSupport → $CFG->branch) are stubbed in tests/bootstrap.php; the
 * version cache is reset per case so the branch can vary.
 *
 * @internal
 */
#[CoversClass(NavbarService::class)]
final class NavbarServiceCoverageTest extends TestCase
{
    private const TEMPLATE = 'navbar-usernavigation';

    private object $output;

    private mixed $prevOutput;

    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->output = new class {
            /** @var array<int, array{0: string, 1: array}> */
            public array $rendered = [];

            public function render_from_template(string $name, array $context): string
            {
                $this->rendered[] = [$name, $context];

                return 'NAV:' . $name;
            }
        };

        $this->prevOutput = $GLOBALS['OUTPUT'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $GLOBALS['OUTPUT'] = $this->output;
        $GLOBALS['__middag_test_has_capability'] = true;

        $this->setMoodleBranch(500);
    }

    protected function tearDown(): void
    {
        $GLOBALS['OUTPUT'] = $this->prevOutput;
        $GLOBALS['CFG'] = $this->prevCfg;
        unset($GLOBALS['__middag_test_has_capability']);
        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
    }

    #[Test]
    public function returnsEmptyWhenUserLacksSiteConfig(): void
    {
        $GLOBALS['__middag_test_has_capability'] = false;

        $this->assertSame('', NavbarService::render());
        $this->assertSame([], $this->output->rendered);
    }

    #[Test]
    public function rendersDropdownForModernMoodle(): void
    {
        $this->setMoodleBranch(500);

        $result = NavbarService::render();

        $this->assertSame('NAV:' . ComponentContext::name() . '/' . self::TEMPLATE, $result);
        $context = $this->lastContext();
        $this->assertGreaterThan(0, $context['hasitems']);
        $this->assertSame('data-bs-toggle="dropdown"', $context['dropdown_toggle_attr']);
        $this->assertSame('dropdown-menu-end', $context['dropdown_menu_align']);
    }

    #[Test]
    public function rendersLegacyAttributesForOldMoodle(): void
    {
        $this->setMoodleBranch(404);

        NavbarService::render();

        $context = $this->lastContext();
        $this->assertSame('data-toggle="dropdown"', $context['dropdown_toggle_attr']);
        $this->assertSame('dropdown-menu-right', $context['dropdown_menu_align']);
    }

    #[Test]
    public function passesHealthBadgeIntoTheContext(): void
    {
        $badge = ['health_score' => 92, 'health_color' => 'success'];

        NavbarService::render([], $badge);

        $this->assertSame($badge, $this->lastContext()['health_badge']);
    }

    #[Test]
    public function mergesCallerSuppliedExtraItems(): void
    {
        $extra = [['url' => '/custom', 'name' => 'Custom', 'icon' => 'star']];

        NavbarService::render($extra);

        $names = array_column($this->lastContext()['actionitems'], 'name');
        $this->assertContains('Custom', $names);
    }

    #[Test]
    public function usesCustomUrlGeneratorForDefaultItems(): void
    {
        $generator = static fn (string $route): string => 'https://host/r/' . $route;

        NavbarService::render([], null, $generator);

        $urls = array_column($this->lastContext()['actionitems'], 'url');
        $this->assertContains('https://host/r/admin_home', $urls);
    }

    private function setMoodleBranch(int $branch): void
    {
        $GLOBALS['CFG'] = (object) ['branch' => $branch, 'release' => '', 'version' => 2025000000];
        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
    }

    /**
     * @return array<string, mixed> the template context passed to $OUTPUT
     */
    private function lastContext(): array
    {
        return $this->output->rendered[0][1];
    }
}
