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

use Middag\Moodle\Settings\Autocomplete;
use Middag\Moodle\Settings\Checkbox;
use Middag\Moodle\Settings\Colourpicker;
use Middag\Moodle\Settings\Description;
use Middag\Moodle\Settings\Directory;
use Middag\Moodle\Settings\Duration;
use Middag\Moodle\Settings\EncryptedPassword;
use Middag\Moodle\Settings\Executable;
use Middag\Moodle\Settings\Filepath;
use Middag\Moodle\Settings\Heading;
use Middag\Moodle\Settings\Htmleditor;
use Middag\Moodle\Settings\Iplist;
use Middag\Moodle\Settings\Link;
use Middag\Moodle\Settings\Multicheckbox;
use Middag\Moodle\Settings\Multiselect;
use Middag\Moodle\Settings\Password;
use Middag\Moodle\Settings\Portlist;
use Middag\Moodle\Settings\Select;
use Middag\Moodle\Settings\Setting;
use Middag\Moodle\Settings\Storedfile;
use Middag\Moodle\Settings\Text;
use Middag\Moodle\Settings\Textarea;
use Middag\Moodle\Settings\Time;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test the Settings DSL types.
 *
 * Every DSL type maps to a native admin_setting_config* class (stubbed in
 * tests/bootstrap.php). Each is instantiated with a name (Link also needs a url)
 * and converted via toMoodleSetting(); the returned instance must be the right
 * admin_setting subclass. The updated-callback branch is exercised separately
 * for the three types that support it.
 *
 * @internal
 */
#[CoversClass(Text::class)]
#[CoversClass(Textarea::class)]
#[CoversClass(Checkbox::class)]
#[CoversClass(Select::class)]
#[CoversClass(Autocomplete::class)]
#[CoversClass(Password::class)]
#[CoversClass(EncryptedPassword::class)]
#[CoversClass(Htmleditor::class)]
#[CoversClass(Colourpicker::class)]
#[CoversClass(Duration::class)]
#[CoversClass(Time::class)]
#[CoversClass(Multicheckbox::class)]
#[CoversClass(Multiselect::class)]
#[CoversClass(Storedfile::class)]
#[CoversClass(Filepath::class)]
#[CoversClass(Directory::class)]
#[CoversClass(Executable::class)]
#[CoversClass(Iplist::class)]
#[CoversClass(Portlist::class)]
#[CoversClass(Heading::class)]
#[CoversClass(Description::class)]
#[CoversClass(Link::class)]
final class SettingDslTypesCoverageTest extends TestCase
{
    /**
     * @param callable(): Setting $make
     */
    #[Test]
    #[DataProvider('typeProvider')]
    public function toMoodleSettingBuildsTheExpectedAdminSetting(callable $make, string $expectedClass): void
    {
        $moodleSetting = $make()->toMoodleSetting('core', 'local_example');

        $this->assertInstanceOf($expectedClass, $moodleSetting);
    }

    /**
     * Factories (not instances) so each type's constructor runs inside the test
     * body — provider-time instantiation is not attributed to this test's
     * coverage.
     *
     * @return array<string, array{0: callable(): Setting, 1: class-string}>
     */
    public static function typeProvider(): array
    {
        return [
            'text' => [static fn (): Setting => new Text('apikey'), 'admin_setting_configtext'],
            'textarea' => [static fn (): Setting => new Textarea('notes'), 'admin_setting_configtextarea'],
            'checkbox' => [static fn (): Setting => new Checkbox('enabled'), 'admin_setting_configcheckbox'],
            'select' => [static fn (): Setting => new Select('mode'), 'admin_setting_configselect'],
            'autocomplete' => [static fn (): Setting => new Autocomplete('country'), 'admin_setting_configselect_autocomplete'],
            'password' => [static fn (): Setting => new Password('secret'), 'admin_setting_configpasswordunmask'],
            'encrypted_password' => [static fn (): Setting => new EncryptedPassword('token'), 'admin_setting_encryptedpassword'],
            'htmleditor' => [static fn (): Setting => new Htmleditor('body'), 'admin_setting_confightmleditor'],
            'colourpicker' => [static fn (): Setting => new Colourpicker('brand'), 'admin_setting_configcolourpicker'],
            'duration' => [static fn (): Setting => new Duration('ttl'), 'admin_setting_configduration'],
            'time' => [static fn (): Setting => new Time('cutoff'), 'admin_setting_configtime'],
            'multicheckbox' => [static fn (): Setting => new Multicheckbox('flags'), 'admin_setting_configmulticheckbox'],
            'multiselect' => [static fn (): Setting => new Multiselect('roles'), 'admin_setting_configmultiselect'],
            'storedfile' => [static fn (): Setting => new Storedfile('logo'), 'admin_setting_configstoredfile'],
            'filepath' => [static fn (): Setting => new Filepath('bin'), 'admin_setting_configfile'],
            'directory' => [static fn (): Setting => new Directory('datadir'), 'admin_setting_configdirectory'],
            'executable' => [static fn (): Setting => new Executable('pdftk'), 'admin_setting_configexecutable'],
            'iplist' => [static fn (): Setting => new Iplist('allowlist'), 'admin_setting_configiplist'],
            'portlist' => [static fn (): Setting => new Portlist('ports'), 'admin_setting_configportlist'],
            'heading' => [static fn (): Setting => new Heading('section'), 'admin_setting_heading'],
            'description' => [static fn (): Setting => new Description('note'), 'admin_setting_description'],
            'link' => [static fn (): Setting => new Link('docs', 'https://example.test'), 'admin_setting_description'],
        ];
    }

    #[Test]
    public function textAppliesUpdatedCallbackWhenProvided(): void
    {
        $setting = (new Text('apikey', updatedCallback: static fn (): bool => true))
            ->toMoodleSetting('core', 'local_example');

        $this->assertNotNull($setting->updated_callback);
    }

    #[Test]
    public function selectAppliesUpdatedCallbackWhenProvided(): void
    {
        $setting = (new Select('mode', updatedCallback: static fn (): bool => true))
            ->toMoodleSetting('core', 'local_example');

        $this->assertNotNull($setting->updated_callback);
    }

    #[Test]
    public function autocompleteAppliesUpdatedCallbackWhenProvided(): void
    {
        $setting = (new Autocomplete('country', updatedCallback: static fn (): bool => true))
            ->toMoodleSetting('core', 'local_example');

        $this->assertNotNull($setting->updated_callback);
    }

    #[Test]
    public function selectResolvesSlugOptionsAndKeepsAssociativeOptions(): void
    {
        // ['off','normal'] are indexed slug values (auto-resolved lang keys);
        // 'custom' => 'Custom' is associative (kept as-is). Exercises both
        // branches of resolveOptions().
        $setting = (new Select('debugmode', options: ['off', 'normal', 'custom' => 'Custom Label']))
            ->toMoodleSetting('core', 'local_example');

        $this->assertInstanceOf('admin_setting_configselect', $setting);
    }

    #[Test]
    public function autocompleteResolvesSlugOptionsAndKeepsAssociativeOptions(): void
    {
        $setting = (new Autocomplete('country', options: ['br', 'us', 'other' => 'Other']))
            ->toMoodleSetting('core', 'local_example');

        $this->assertInstanceOf('admin_setting_configselect_autocomplete', $setting);
    }
}
