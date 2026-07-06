<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Shared\Util;

use Middag\Moodle\Shared\Util\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

/**
 * The Moodle-flavour Environment only overrides the protected template hook
 * detectHostEnvironment(); every other resolution step lives in the framework
 * base. The hook is exercised directly (it is a protected static seam whose
 * realistic caller, getEnvironment(), short-circuits on the ambient
 * MIDDAG_ENV/APP_ENV process env) so each branch of the Moodle-native signal
 * chain — legacy MOODLE_ENV, $CFG->middag_env, DEBUG_DEVELOPER inference,
 * null fall-through — is covered against its exact return value.
 *
 * @internal
 */
#[CoversClass(Environment::class)]
final class EnvironmentCoverageTest extends TestCase
{
    private mixed $prevMoodleEnv;

    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevMoodleEnv = getenv('MOODLE_ENV');
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        // Clean slate: no legacy env var, and a bare $CFG carrying no signals.
        putenv('MOODLE_ENV');
        $GLOBALS['CFG'] = new stdClass();
    }

    protected function tearDown(): void
    {
        if ($this->prevMoodleEnv === false) {
            putenv('MOODLE_ENV');
        } else {
            putenv('MOODLE_ENV=' . $this->prevMoodleEnv);
        }

        if ($this->prevCfg === null) {
            unset($GLOBALS['CFG']);
        } else {
            $GLOBALS['CFG'] = $this->prevCfg;
        }
    }

    #[Test]
    public function legacyMoodleEnvVarTakesPrecedenceOverEveryOtherSignal(): void
    {
        // Legacy env var set AND a conflicting config value: the env var wins
        // and is returned verbatim (no normalization at this layer).
        putenv('MOODLE_ENV=development');
        $GLOBALS['CFG']->middag_env = 'production';
        $GLOBALS['CFG']->debug = DEBUG_DEVELOPER;

        self::assertSame('development', $this->detectHostEnvironment());
    }

    #[Test]
    public function emptyLegacyMoodleEnvVarIsIgnoredAndConfigWins(): void
    {
        // An empty-string MOODLE_ENV must NOT satisfy the guard ($v !== '')
        // and must fall through to the $CFG->middag_env branch.
        putenv('MOODLE_ENV=');
        $GLOBALS['CFG']->middag_env = 'testing';

        self::assertSame('testing', $this->detectHostEnvironment());
    }

    #[Test]
    public function configMiddagEnvResolvedWhenLegacyEnvUnset(): void
    {
        // MOODLE_ENV unset (getenv() === false) → guard skipped → config used.
        $GLOBALS['CFG']->middag_env = 'staging';

        self::assertSame('staging', $this->detectHostEnvironment());
    }

    #[Test]
    public function debugDeveloperInferredAsDevelopmentWhenNoEnvOrConfig(): void
    {
        // No legacy env, no middag_env config, but $CFG->debug === DEBUG_DEVELOPER
        // → inferred as the development environment.
        $GLOBALS['CFG']->debug = DEBUG_DEVELOPER;

        self::assertSame(Environment::ENV_DEVELOPMENT, $this->detectHostEnvironment());
    }

    #[Test]
    public function returnsNullWhenDebugIsSetButNotDeveloper(): void
    {
        // $CFG->debug is present (isset true) but does not equal DEBUG_DEVELOPER,
        // so the inference branch is skipped and the method yields null.
        $GLOBALS['CFG']->debug = DEBUG_NONE;

        self::assertNull($this->detectHostEnvironment());
    }

    #[Test]
    public function returnsNullWhenNoSignalsPresent(): void
    {
        // Bare $CFG (no middag_env, no debug) and no legacy env var → the
        // framework base is left to default to production, signalled by null.
        self::assertNull($this->detectHostEnvironment());
    }

    private function detectHostEnvironment(): ?string
    {
        $method = new ReflectionMethod(Environment::class, 'detectHostEnvironment');

        return $method->invoke(null);
    }
}
