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

use admin_setting;
use Middag\Moodle\Settings\AbstractSetting;
use Middag\Moodle\Settings\SettingsNamingPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test the Setting base class.
 *
 * Covers label/description/config-key resolution shared by every DSL type,
 * exercised through an anonymous concrete subclass. Description auto-resolution
 * consults LangSupport::stringExists (get_string_manager stub in bootstrap).
 *
 * @internal
 */
#[CoversClass(AbstractSetting::class)]
final class AbstractSettingCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_string_exists']);
    }

    #[Test]
    public function resolveLabelUsesExplicitLabel(): void
    {
        $this->assertSame('custom_label', $this->makeSetting(label: 'custom_label')->resolveLabel('core', 'local_example'));
    }

    #[Test]
    public function resolveLabelForCoreExtension(): void
    {
        $this->assertSame('setting_apikey', $this->makeSetting()->resolveLabel('core', 'local_example'));
    }

    #[Test]
    public function resolveLabelForNonCoreExtension(): void
    {
        $this->assertSame('setting_ecommerce_apikey', $this->makeSetting()->resolveLabel('ecommerce', 'local_example'));
    }

    #[Test]
    public function resolveDescriptionUsesExplicitDescription(): void
    {
        $this->assertSame('my_desc', $this->makeSetting(description: 'my_desc')->resolveDescription('core', 'local_example'));
    }

    #[Test]
    public function resolveDescriptionAutoResolvesWhenLangStringExists(): void
    {
        $GLOBALS['__middag_test_string_exists'] = static fn (): bool => true;

        $this->assertSame('setting_apikey_desc', $this->makeSetting()->resolveDescription('core', 'local_example'));
    }

    #[Test]
    public function resolveDescriptionReturnsEmptyWhenAutoKeyMissing(): void
    {
        $GLOBALS['__middag_test_string_exists'] = static fn (): bool => false;

        $this->assertSame('', $this->makeSetting()->resolveDescription('core', 'local_example'));
    }

    #[Test]
    public function resolveConfigNameDelegatesToResolver(): void
    {
        $this->assertSame('mdg_ecommerce_apikey', $this->makeSetting()->resolveConfigName('ecommerce'));
    }

    #[Test]
    public function useNamingPolicyOverridesTheDefaultPolicyUsedByResolveConfigName(): void
    {
        $setting = $this->makeSetting();
        $setting->useNamingPolicy(new SettingsNamingPolicy('acme_'));

        $this->assertSame('acme_ecommerce_apikey', $setting->resolveConfigName('ecommerce'));
    }

    private function makeSetting(?string $label = null, ?string $description = null, string $name = 'apikey'): AbstractSetting
    {
        return new class($name, null, $label, $description) extends AbstractSetting {
            public function toMoodleSetting(string $extension, string $plugin): admin_setting
            {
                return new admin_setting($this->name);
            }
        };
    }
}
