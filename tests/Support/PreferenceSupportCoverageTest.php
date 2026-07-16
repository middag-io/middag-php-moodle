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

use Middag\Moodle\Support\PreferenceSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(PreferenceSupport::class)]
final class PreferenceSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__middag_test_preferences'] = [];

        unset(
            $GLOBALS['__middag_test_preferences_all'],
            $GLOBALS['__middag_test_throw_get_user_preferences'],
            $GLOBALS['__middag_test_throw_set_user_preference'],
            $GLOBALS['__middag_test_throw_set_user_preferences'],
            $GLOBALS['__middag_test_throw_unset_user_preference'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__middag_test_preferences'],
            $GLOBALS['__middag_test_preferences_all'],
            $GLOBALS['__middag_test_throw_get_user_preferences'],
            $GLOBALS['__middag_test_throw_set_user_preference'],
            $GLOBALS['__middag_test_throw_set_user_preferences'],
            $GLOBALS['__middag_test_throw_unset_user_preference'],
        );
    }

    #[Test]
    public function testGetReturnsTheStoredValue(): void
    {
        $GLOBALS['__middag_test_preferences']['theme'] = 'dark';

        self::assertSame('dark', PreferenceSupport::get('theme'));
    }

    #[Test]
    public function testGetReturnsTheDefaultWhenAbsentForAnExplicitUser(): void
    {
        self::assertSame('light', PreferenceSupport::get('theme', 'light', 7));
    }

    #[Test]
    public function testGetReturnsTheDefaultWhenMoodleThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_user_preferences'] = true;

        self::assertSame('fallback', PreferenceSupport::get('theme', 'fallback'));
    }

    #[Test]
    public function testGetAllReturnsThePreferencesAsAnObject(): void
    {
        // Real Moodle returns a plain array (with an internal _lastloaded
        // cache-freshness key), never a stdClass.
        $GLOBALS['__middag_test_preferences_all'] = ['theme' => 'dark', 'lang' => 'en', '_lastloaded' => 1737000000];

        $result = PreferenceSupport::getAll();

        self::assertInstanceOf(stdClass::class, $result);
        self::assertSame('dark', $result->theme);
        self::assertSame('en', $result->lang);
        // The internal cache key must not leak as a fake preference.
        self::assertObjectNotHasProperty('_lastloaded', $result);
    }

    #[Test]
    public function testGetAllReturnsNullWhenTheResultIsNeitherArrayNorObject(): void
    {
        $GLOBALS['__middag_test_preferences_all'] = false;

        self::assertNull(PreferenceSupport::getAll());
    }

    #[Test]
    public function testGetAllReturnsNullWhenMoodleThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_user_preferences'] = true;

        self::assertNull(PreferenceSupport::getAll());
    }

    #[Test]
    public function testSetStoresTheValueAndReturnsTrue(): void
    {
        self::assertTrue(PreferenceSupport::set('theme', 'dark'));
        self::assertSame('dark', $GLOBALS['__middag_test_preferences']['theme']);
    }

    #[Test]
    public function testSetReturnsFalseWhenMoodleThrows(): void
    {
        $GLOBALS['__middag_test_throw_set_user_preference'] = true;

        self::assertFalse(PreferenceSupport::set('theme', 'dark'));
    }

    #[Test]
    public function testSetWithNullDeletesThePreference(): void
    {
        // Moodle's set_user_preference() treats null as "delete current
        // value" — a meaningfully different side effect from storing. Pinned
        // here so the behaviour is intentional, not incidental.
        $GLOBALS['__middag_test_preferences']['theme'] = 'dark';

        self::assertTrue(PreferenceSupport::set('theme', null));
        self::assertArrayNotHasKey('theme', $GLOBALS['__middag_test_preferences']);
    }

    #[Test]
    public function testSetManyStoresEveryValueAndReturnsTrue(): void
    {
        self::assertTrue(PreferenceSupport::setMany(['theme' => 'dark', 'lang' => 'en'], 7));
        self::assertSame('en', $GLOBALS['__middag_test_preferences']['lang']);
    }

    #[Test]
    public function testSetManyReturnsFalseWhenMoodleThrows(): void
    {
        $GLOBALS['__middag_test_throw_set_user_preferences'] = true;

        self::assertFalse(PreferenceSupport::setMany(['theme' => 'dark']));
    }

    #[Test]
    public function testRemoveDeletesTheValueAndReturnsTrue(): void
    {
        $GLOBALS['__middag_test_preferences']['theme'] = 'dark';

        self::assertTrue(PreferenceSupport::remove('theme'));
        self::assertArrayNotHasKey('theme', $GLOBALS['__middag_test_preferences']);
    }

    #[Test]
    public function testRemoveReturnsFalseWhenMoodleThrows(): void
    {
        $GLOBALS['__middag_test_throw_unset_user_preference'] = true;

        self::assertFalse(PreferenceSupport::remove('theme'));
    }
}
