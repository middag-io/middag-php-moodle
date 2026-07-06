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

use Middag\Moodle\Settings\framework_config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test framework_config.
 *
 * Backed enum of the adapter's host-level config keys; each case's value maps to
 * the canonical mdg_core_{value} config key via SettingsSupport.
 *
 * @internal
 */
#[CoversClass(framework_config::class)]
final class FrameworkConfigCoverageTest extends TestCase
{
    #[Test]
    public function casesExposeTheirBackingKeys(): void
    {
        $this->assertSame('authtype', framework_config::authtype->value);
        $this->assertSame('authsecretkey', framework_config::authsecretkey->value);
        $this->assertSame('api_enabled', framework_config::api_enabled->value);
        $this->assertSame('debugmode', framework_config::debugmode->value);
    }

    #[Test]
    public function coversTheWholeHostConfigVocabulary(): void
    {
        $values = array_map(static fn (framework_config $c): string => $c->value, framework_config::cases());

        $this->assertSame(
            ['authtype', 'authsecretkey', 'authvarname', 'authprofilefield', 'usersupport', 'api_enabled', 'debugmode'],
            $values
        );
    }
}
