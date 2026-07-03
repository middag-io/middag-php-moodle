<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Statics;

use Middag\Moodle\Definition\ServiceDefinition;
use Middag\Moodle\Statics\StaticsRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StaticsRenderer::class)]
final class StaticsRendererTest extends TestCase
{
    #[Test]
    public function renderServicesEmitsBaseEntry(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderServices([
            new ServiceDefinition(name: 'do_thing', classname: 'local_example\external\do_thing', type: 'write'),
        ], 'local_example');

        $this->assertStringContainsString("'local_example_do_thing' => [", $output);
        $this->assertStringContainsString("'classname' => 'local_example\\external\\do_thing',", $output);
        $this->assertStringContainsString("'methodname' => 'do_thing',", $output);
        $this->assertStringContainsString("'type' => 'write',", $output);
        $this->assertStringContainsString('$functions = [', $output);
    }

    #[Test]
    public function renderServicesEmitsCapabilitiesWhenSet(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderServices([
            new ServiceDefinition(
                name: 'do_thing',
                classname: 'local_example\external\do_thing',
                capabilities: 'local/example:dothing, local/example:view',
            ),
        ], 'local_example');

        $this->assertStringContainsString(
            "'capabilities' => 'local/example:dothing, local/example:view',",
            $output,
        );
    }

    #[Test]
    public function renderServicesOmitsCapabilitiesWhenNull(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderServices([
            new ServiceDefinition(name: 'do_thing', classname: 'local_example\external\do_thing'),
        ], 'local_example');

        $this->assertStringNotContainsString("'capabilities'", $output);
    }

    #[Test]
    public function renderServicesEmitsCapabilitiesBeforeServices(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderServices([
            new ServiceDefinition(
                name: 'do_thing',
                classname: 'local_example\external\do_thing',
                services: ['s1', 's2'],
                capabilities: 'local/example:dothing',
            ),
        ], 'local_example');

        // Both render, and capabilities sits before the services block — the one
        // behaviour unique to this increment's insertion point. A future reorder of
        // the two blocks would otherwise stay green while changing the output.
        $capPos = strpos($output, "'capabilities' =>");
        $svcPos = strpos($output, "'services' => [");

        $this->assertNotFalse($capPos);
        $this->assertNotFalse($svcPos);
        $this->assertLessThan($svcPos, $capPos);
    }

    #[Test]
    public function renderServicesEscapesCapabilitiesValue(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderServices([
            new ServiceDefinition(
                name: 'do_thing',
                classname: 'local_example\external\do_thing',
                capabilities: "local/o'brien:do",
            ),
        ], 'local_example');

        $this->assertStringContainsString("'capabilities' => 'local/o\\'brien:do',", $output);
    }
}
