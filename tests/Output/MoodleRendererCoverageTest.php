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

use core\output\plugin_renderer_base;
use Middag\Moodle\Output\MoodleRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test MoodleRenderer.
 *
 * An empty abstract base that extension renderers extend instead of Moodle's
 * plugin_renderer_base (runtime stand-in from tests/bootstrap.php).
 *
 * @internal
 */
#[CoversClass(MoodleRenderer::class)]
final class MoodleRendererCoverageTest extends TestCase
{
    #[Test]
    public function extendsPluginRendererBase(): void
    {
        $renderer = new class extends MoodleRenderer {};

        $this->assertInstanceOf(plugin_renderer_base::class, $renderer);
        $this->assertInstanceOf(MoodleRenderer::class, $renderer);
    }

    #[Test]
    public function isAbstract(): void
    {
        $this->assertTrue((new ReflectionClass(MoodleRenderer::class))->isAbstract());
    }
}
