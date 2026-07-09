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

use Middag\Moodle\Settings\SettingsNamingPolicy;
use Middag\Moodle\Settings\SettingsResolver;
use Middag\Moodle\Settings\Type\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The naming policy is the host-extensibility seam of LB-HOST-EXT-01: the
 * MIDDAG default keeps producing mdg_* keys, while a client plugin on the
 * same Moodle host injects its own prefix and gets non-colliding keys —
 * without the default being affected.
 *
 * @internal
 */
#[CoversClass(SettingsNamingPolicy::class)]
#[CoversClass(SettingsResolver::class)]
final class SettingsNamingPolicyTest extends TestCase
{
    #[Test]
    public function defaultPolicyProducesMiddagKeys(): void
    {
        $policy = new SettingsNamingPolicy();

        self::assertSame('mdg_core_apikey', $policy->configKey('apikey', 'core'));
    }

    #[Test]
    public function clientPolicyProducesClientKeysWithoutAffectingTheDefault(): void
    {
        $client = new SettingsNamingPolicy('clientx_');

        self::assertSame('clientx_core_apikey', $client->configKey('apikey', 'core'));
        // The default stays MIDDAG's — policies are independent value objects.
        self::assertSame('mdg_core_apikey', (new SettingsNamingPolicy())->configKey('apikey', 'core'));
    }

    #[Test]
    public function alreadyPrefixedNamesPassThroughUnchanged(): void
    {
        $client = new SettingsNamingPolicy('clientx_');

        self::assertSame('clientx_core_apikey', $client->configKey('clientx_core_apikey', 'core'));
    }

    #[Test]
    public function resolverInstanceResolvesKeysUnderItsInjectedPolicy(): void
    {
        $resolver = new SettingsResolver(new SettingsNamingPolicy('clientx_'));

        self::assertSame('clientx_core_apikey', $resolver->configKey('apikey', 'core'));
        // Default-constructed resolver keeps the MIDDAG prefix.
        self::assertSame('mdg_core_apikey', (new SettingsResolver())->configKey('apikey', 'core'));
    }

    #[Test]
    public function settingAdoptsTheInjectedPolicyForItsConfigName(): void
    {
        $setting = new Text('apikey');

        // Before adoption: MIDDAG default.
        self::assertSame('mdg_core_apikey', $setting->resolveConfigName('core'));

        // After adoption (what SettingsResolver::resolveExtensionPages does).
        $setting->useNamingPolicy(new SettingsNamingPolicy('clientx_'));

        self::assertSame('clientx_core_apikey', $setting->resolveConfigName('core'));
    }
}
