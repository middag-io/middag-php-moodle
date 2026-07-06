<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Settings;

use admin_settingpage;
use Middag\Moodle\Settings\Page;
use Middag\Moodle\Settings\SettingsResolver;
use Middag\Moodle\Settings\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test SettingsResolver.
 *
 * Canonicalises config keys (mdg_{ext}_{name}) and turns the typed Page/Setting
 * DSL into Moodle admin_settingpage objects (stubbed in tests/bootstrap.php).
 *
 * @internal
 */
#[CoversClass(SettingsResolver::class)]
final class SettingsResolverCoverageTest extends TestCase
{
    #[Test]
    public function resolveConfigKeyPrependsCanonicalPrefix(): void
    {
        $this->assertSame('mdg_core_apikey', SettingsResolver::resolveConfigKey('apikey', 'core'));
    }

    #[Test]
    public function resolveConfigKeyIsIdempotentForAlreadyPrefixedNames(): void
    {
        $this->assertSame('mdg_core_apikey', SettingsResolver::resolveConfigKey('mdg_core_apikey', 'core'));
    }

    #[Test]
    public function resolveExtensionPagesBuildsAdminPagesWithChildSettings(): void
    {
        $resolver = new SettingsResolver();

        $page = new Page('general', settings: [
            new Text('apikey'),
            'not-a-setting', // ignored — not a Setting instance
        ]);

        $pages = $resolver->resolveExtensionPages('core', [$page, 'not-a-page'], 'local_example');

        $this->assertCount(1, $pages);
        $this->assertInstanceOf(admin_settingpage::class, $pages[0]);
        // Only the Text setting was added; the string child was skipped.
        $this->assertCount(1, $pages[0]->settings);
    }

    #[Test]
    public function resolveExtensionPagesReturnsEmptyWhenNoPages(): void
    {
        $resolver = new SettingsResolver();

        $this->assertSame([], $resolver->resolveExtensionPages('core', ['x', new Text('y')], 'local_example'));
    }
}
