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

use core\lang_string;
use core_string_manager;
use Exception;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\LangSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LangSupport::class)]
final class LangSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        ComponentContext::configure('local_example', 'local_example_autoload');

        unset(
            $GLOBALS['__middag_test_get_string'],
            $GLOBALS['__middag_test_string_exists'],
            $GLOBALS['__middag_test_string_manager_invalid'],
            $GLOBALS['__middag_test_current_language'],
            $GLOBALS['__middag_test_throw_current_language'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__middag_test_get_string'],
            $GLOBALS['__middag_test_string_exists'],
            $GLOBALS['__middag_test_string_manager_invalid'],
            $GLOBALS['__middag_test_current_language'],
            $GLOBALS['__middag_test_throw_current_language'],
        );

        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    #[Test]
    public function testGetReturnsTheLocalizedString(): void
    {
        self::assertSame('[local_example/greeting]', LangSupport::get('greeting', null, false, 'local_example'));
    }

    #[Test]
    public function testGetResolvesTheComponentFromContextWhenNull(): void
    {
        self::assertSame('[local_example/greeting]', LangSupport::get('greeting'));
    }

    #[Test]
    public function testGetReturnsALangStringObjectWhenLazyloaded(): void
    {
        $result = LangSupport::get('greeting', null, true, 'local_example');

        self::assertInstanceOf(lang_string::class, $result);
    }

    #[Test]
    public function testGetReturnsTheFallbackMarkerWhenResolutionThrows(): void
    {
        $GLOBALS['__middag_test_get_string'] = static function (): string {
            throw new Exception('missing string');
        };

        self::assertSame('[[greeting]]', LangSupport::get('greeting', null, false, 'local_example'));
    }

    #[Test]
    public function testGetStringDelegatesToGet(): void
    {
        self::assertSame('[comp/id]', LangSupport::getString('id', 'comp'));
    }

    #[Test]
    public function testGetStringDefaultsToCoreNotThePluginWhenComponentOmitted(): void
    {
        // Intentional divergence from get(): getString() is Moodle-native, so an
        // omitted component stays '' (core), whereas get() resolves it via
        // ComponentContext to the plugin. Core-string call sites rely on this.
        self::assertSame('[/id]', LangSupport::getString('id'));
        self::assertSame('[local_example/id]', LangSupport::get('id'));
    }

    #[Test]
    public function testGetStringOrIdentifierReturnsTheStringWhenItExists(): void
    {
        $GLOBALS['__middag_test_string_exists'] = static fn (): bool => true;

        self::assertSame('[comp/id]', LangSupport::getStringOrIdentifier('id', 'comp'));
    }

    #[Test]
    public function testGetStringOrIdentifierReturnsTheIdentifierWhenAbsent(): void
    {
        $GLOBALS['__middag_test_string_exists'] = static fn (): bool => false;

        self::assertSame('id', LangSupport::getStringOrIdentifier('id', 'comp'));
    }

    #[Test]
    public function testGetStringOrIdentifierResolvesTheComponentFromContextWhenNull(): void
    {
        // Omitting the component must resolve via ComponentContext, not default
        // to core — otherwise a plugin's own string is never found and the raw
        // identifier leaks through instead of the translation.
        $GLOBALS['__middag_test_string_exists'] = static fn (): bool => true;

        self::assertSame('[local_example/id]', LangSupport::getStringOrIdentifier('id'));
    }

    #[Test]
    public function testGetStringOrIdentifierReturnsTheIdentifierWhenComponentContextThrows(): void
    {
        // stringExists() resolves an omitted $component via ComponentContext::name()
        // *before* its own try/catch, so an unconfigured adapter throws
        // MoodleConfigurationException there — this is the one path that reaches
        // getStringOrIdentifier()'s own catch (it never comes from get_string()
        // itself, since get()/getString() always swallow that internally).
        ComponentContext::reset();

        self::assertSame('id', LangSupport::getStringOrIdentifier('id'));
    }

    #[Test]
    public function testStringExistsReturnsTrueWhenTheManagerConfirmsIt(): void
    {
        $GLOBALS['__middag_test_string_exists'] = static fn (): bool => true;

        self::assertTrue(LangSupport::stringExists('id', 'comp'));
    }

    #[Test]
    public function testStringExistsResolvesTheComponentFromContextWhenNull(): void
    {
        $GLOBALS['__middag_test_string_exists'] = static fn (): bool => true;

        self::assertTrue(LangSupport::stringExists('id'));
    }

    #[Test]
    public function testStringExistsReturnsFalseWhenTheManagerThrows(): void
    {
        $GLOBALS['__middag_test_string_exists'] = static function (): bool {
            throw new Exception('lookup failed');
        };

        self::assertFalse(LangSupport::stringExists('id', 'comp'));
    }

    #[Test]
    public function testStringExistsReturnsFalseWhenTheManagerIsNotAStringManager(): void
    {
        // Covers the instanceof guard. The central get_string_manager() stub is
        // return-typed core_string_manager and always returns one, so probe and
        // skip until it can yield a non-manager. See coverage report.
        $GLOBALS['__middag_test_string_manager_invalid'] = true;

        if (get_string_manager() instanceof core_string_manager) {
            self::markTestSkipped('get_string_manager central stub always returns a core_string_manager (see coverage report).');
        }

        self::assertFalse(LangSupport::stringExists('id', 'comp'));
    }

    #[Test]
    public function testCurrentLanguageReturnsTheActiveLanguage(): void
    {
        $GLOBALS['__middag_test_current_language'] = 'pt_br';

        self::assertSame('pt_br', LangSupport::currentLanguage());
    }

    #[Test]
    public function testCurrentLanguageReturnsEnglishWhenResolutionThrows(): void
    {
        $GLOBALS['__middag_test_throw_current_language'] = true;

        self::assertSame('en', LangSupport::currentLanguage());
    }

    #[Test]
    public function testPluginnameReturnsThePluginNameString(): void
    {
        self::assertSame('[local_example/pluginname]', LangSupport::pluginname());
    }
}
