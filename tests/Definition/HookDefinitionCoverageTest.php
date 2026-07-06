<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Definition;

use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Definition\HookDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * HookDefinition is a readonly declarative value object for db/hooks.php entries.
 * It has no Moodle-runtime dependencies (isCompatible() uses PHP's native
 * version_compare), so every method and branch is exercised directly.
 *
 * @internal
 */
#[CoversClass(HookDefinition::class)]
final class HookDefinitionCoverageTest extends TestCase
{
    #[Test]
    public function testConstructorAppliesDefaults(): void
    {
        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: 'local_example\hook_callbacks::extend',
        );

        self::assertSame('core\hook\after_config', $def->hook_class);
        self::assertSame('local_example\hook_callbacks::extend', $def->callback);
        self::assertSame(0, $def->priority);
        self::assertSame('4.3', $def->min_moodle);
        self::assertNull($def->max_moodle);
    }

    #[Test]
    public function testConstructorAcceptsAllArguments(): void
    {
        $callback = ['local_example\hook_callbacks', 'extend'];

        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: $callback,
            priority: 250,
            min_moodle: '4.4',
            max_moodle: '5.0',
        );

        self::assertSame('core\hook\after_config', $def->hook_class);
        self::assertSame($callback, $def->callback);
        self::assertSame(250, $def->priority);
        self::assertSame('4.4', $def->min_moodle);
        self::assertSame('5.0', $def->max_moodle);
    }

    #[Test]
    public function testImplementsDefinitionInterface(): void
    {
        $def = new HookDefinition('core\hook\after_config', 'cb');

        self::assertInstanceOf(DefinitionInterface::class, $def);
    }

    #[Test]
    public function testGetNameReturnsHookClass(): void
    {
        $def = new HookDefinition('core\hook\output\before_footer_html_generation', 'cb');

        self::assertSame('core\hook\output\before_footer_html_generation', $def->getName());
    }

    #[Test]
    public function testToMoodleArrayOmitsPriorityWhenZero(): void
    {
        $callback = ['local_example\hook_callbacks', 'extend'];

        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: $callback,
        );

        $entry = $def->toMoodleArray('local_example');

        self::assertSame(
            [
                'hook' => 'core\hook\after_config',
                'callback' => $callback,
            ],
            $entry,
        );
        self::assertArrayNotHasKey('priority', $entry);
    }

    #[Test]
    public function testToMoodleArrayWithStringCallback(): void
    {
        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: 'local_example\hook_callbacks::extend',
        );

        $entry = $def->toMoodleArray('local_example');

        self::assertSame('core\hook\after_config', $entry['hook']);
        self::assertSame('local_example\hook_callbacks::extend', $entry['callback']);
        self::assertArrayNotHasKey('priority', $entry);
    }

    #[Test]
    public function testToMoodleArrayIncludesPositivePriority(): void
    {
        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: 'cb',
            priority: 500,
        );

        $entry = $def->toMoodleArray('local_example');

        self::assertArrayHasKey('priority', $entry);
        self::assertSame(500, $entry['priority']);
    }

    #[Test]
    public function testToMoodleArrayIncludesNegativePriority(): void
    {
        // Non-zero includes negatives — the guard is `!== 0`, not `> 0`.
        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: 'cb',
            priority: -100,
        );

        $entry = $def->toMoodleArray('local_example');

        self::assertArrayHasKey('priority', $entry);
        self::assertSame(-100, $entry['priority']);
    }

    #[Test]
    public function testToMoodleArrayIgnoresPluginNameArgument(): void
    {
        // $plugin_name is part of the contract but unused by this definition;
        // different values must produce identical output.
        $def = new HookDefinition('core\hook\after_config', 'cb', priority: 10);

        self::assertSame(
            $def->toMoodleArray('local_example'),
            $def->toMoodleArray('mod_forum'),
        );
    }

    #[Test]
    public function testIsCompatibleWithinDefaultMinBound(): void
    {
        // Default min_moodle is '4.3'; equal and above pass.
        $def = new HookDefinition('core\hook\after_config', 'cb');

        self::assertTrue($def->isCompatible('4.3'));
        self::assertTrue($def->isCompatible('5.0'));
    }

    #[Test]
    public function testIsCompatibleRejectsBelowDefaultMin(): void
    {
        $def = new HookDefinition('core\hook\after_config', 'cb');

        self::assertFalse($def->isCompatible('4.2'));
    }

    #[Test]
    public function testIsCompatibleSkipsMinCheckWhenMinMoodleNull(): void
    {
        // min_moodle null must skip the lower-bound guard entirely.
        $def = new HookDefinition('core\hook\after_config', 'cb', min_moodle: null);

        self::assertTrue($def->isCompatible('1.0'));
    }

    #[Test]
    public function testIsCompatibleRejectsAboveMax(): void
    {
        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: 'cb',
            min_moodle: null,
            max_moodle: '4.5',
        );

        self::assertFalse($def->isCompatible('4.6'));
        self::assertTrue($def->isCompatible('4.5'));
    }

    #[Test]
    public function testIsCompatibleWithinMinAndMaxRange(): void
    {
        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: 'cb',
            min_moodle: '4.3',
            max_moodle: '5.0',
        );

        self::assertTrue($def->isCompatible('4.3'));
        self::assertTrue($def->isCompatible('4.7'));
        self::assertTrue($def->isCompatible('5.0'));
        self::assertFalse($def->isCompatible('4.2'));
        self::assertFalse($def->isCompatible('5.1'));
    }

    #[Test]
    public function testIsCompatibleWhenBothBoundsNull(): void
    {
        // Both bounds null: unconditionally compatible (reaches the final return true).
        $def = new HookDefinition(
            hook_class: 'core\hook\after_config',
            callback: 'cb',
            min_moodle: null,
        );

        self::assertTrue($def->isCompatible('1.0'));
        self::assertTrue($def->isCompatible('99.0'));
    }
}
