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

use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Definition\HookDefinition;
use Middag\Moodle\Definition\ServiceDefinition;
use Middag\Moodle\Definition\WebServiceDefinition;
use Middag\Moodle\Statics\StaticsRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Line-coverage complement for StaticsRenderer. The sibling StaticsRendererTest
 * only exercises renderServices(); this class drives every remaining renderer —
 * caches, access, services (class resolution + mobile constant), web-services,
 * messages, hooks, events — plus the private constant-mapping helpers, using
 * lightweight DefinitionInterface doubles so each int→constant branch is hit
 * deterministically without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(StaticsRenderer::class)]
final class StaticsRendererCoverageTest extends TestCase
{
    #[Test]
    public function renderCachesEmitsEveryModeAndFlagCombination(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderCaches([
            $this->definition('cache_a', ['mode' => 1, 'simplekeys' => true, 'simpledata' => true]),
            $this->definition('cache_b', ['mode' => 2]),
            $this->definition('cache_c', ['mode' => 4, 'simplekeys' => true]),
            $this->definition('cache_d', ['mode' => 99]),
        ]);

        // Header (target) + array preamble.
        self::assertStringStartsWith('<?php', $output);
        self::assertStringContainsString("defined('MOODLE_INTERNAL') || exit;", $output);
        self::assertStringContainsString('derived from extension declarations (caches).', $output);
        self::assertStringContainsString('// AUTO-GENERATED FILE', $output);
        self::assertStringContainsString('$definitions = [', $output);

        // renderCacheMode: mapped arms 1/2/4 + numeric default.
        self::assertStringContainsString("'mode' => cache_store::MODE_APPLICATION,", $output);
        self::assertStringContainsString("'mode' => cache_store::MODE_SESSION,", $output);
        self::assertStringContainsString("'mode' => cache_store::MODE_REQUEST,", $output);
        self::assertStringContainsString("'mode' => 99,", $output);

        // simplekeys + simpledata both present (cache_a).
        self::assertStringContainsString("'simplekeys' => true,", $output);
        self::assertStringContainsString("'simpledata' => true,", $output);

        // cache_b: neither flag — contiguous block proves both were omitted.
        self::assertStringContainsString(
            "    'cache_b' => [\n        'mode' => cache_store::MODE_SESSION,\n    ],",
            $output,
        );

        // cache_c: simplekeys present, simpledata omitted.
        self::assertStringContainsString(
            "    'cache_c' => [\n        'mode' => cache_store::MODE_REQUEST,\n        'simplekeys' => true,\n    ],",
            $output,
        );
    }

    #[Test]
    public function renderCachesHonorsCustomMarker(): void
    {
        $renderer = new StaticsRenderer('// BESPOKE MARKER');

        $output = $renderer->renderCaches([$this->definition('c', ['mode' => 1])]);

        self::assertStringContainsString('// BESPOKE MARKER', $output);
        self::assertStringNotContainsString('AUTO-GENERATED', $output);
        self::assertStringContainsString('declare(strict_types=1);', $output);
    }

    #[Test]
    public function renderAccessMapsRiskAndContextConstants(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderAccess([
            $this->accessPair('local/example:alpha', [
                'riskbitmask' => 1,
                'captype' => 'read',
                'contextlevel' => 10,
                'archetypes' => ['student' => 1, 'teacher' => 1],
                'clonepermissionsfrom' => 'moodle/site:accessallgroups',
            ]),
            $this->accessPair('local/example:bravo', [
                'riskbitmask' => 2,
                'captype' => 'write',
                'contextlevel' => 30,
                'archetypes' => ['manager' => 1],
            ]),
            $this->accessPair('local/example:charlie', [
                'riskbitmask' => 4, 'captype' => 'read', 'contextlevel' => 40, 'archetypes' => ['student' => 1],
            ]),
            $this->accessPair('local/example:delta', [
                'riskbitmask' => 8, 'captype' => 'read', 'contextlevel' => 50, 'archetypes' => ['student' => 1],
            ]),
            $this->accessPair('local/example:echo', [
                'riskbitmask' => 16, 'captype' => 'read', 'contextlevel' => 70, 'archetypes' => ['student' => 1],
            ]),
            $this->accessPair('local/example:foxtrot', [
                'riskbitmask' => 99, 'captype' => 'read', 'contextlevel' => 80, 'archetypes' => ['student' => 1],
            ]),
            $this->accessPair('local/example:golf', [
                'riskbitmask' => 1, 'captype' => 'read', 'contextlevel' => 99, 'archetypes' => ['student' => 1],
            ]),
        ], 'local_example');

        self::assertStringContainsString('derived from extension declarations (access).', $output);
        self::assertStringContainsString('$capabilities = [', $output);
        self::assertStringContainsString("'local/example:alpha' => [", $output);

        // renderRiskConstant: all mapped arms + numeric default.
        self::assertStringContainsString("'riskbitmask' => RISK_SPAM,", $output);
        self::assertStringContainsString("'riskbitmask' => RISK_PERSONAL,", $output);
        self::assertStringContainsString("'riskbitmask' => RISK_XSS,", $output);
        self::assertStringContainsString("'riskbitmask' => RISK_CONFIG,", $output);
        self::assertStringContainsString("'riskbitmask' => RISK_DATALOSS,", $output);
        self::assertStringContainsString("'riskbitmask' => 99,", $output);

        // renderContextConstant: all mapped arms + numeric default.
        self::assertStringContainsString("'contextlevel' => CONTEXT_SYSTEM,", $output);
        self::assertStringContainsString("'contextlevel' => CONTEXT_USER,", $output);
        self::assertStringContainsString("'contextlevel' => CONTEXT_COURSECAT,", $output);
        self::assertStringContainsString("'contextlevel' => CONTEXT_COURSE,", $output);
        self::assertStringContainsString("'contextlevel' => CONTEXT_MODULE,", $output);
        self::assertStringContainsString("'contextlevel' => CONTEXT_BLOCK,", $output);
        self::assertStringContainsString("'contextlevel' => 99,", $output);

        // captype forwarded verbatim; archetypes loop; clone present + absent.
        self::assertStringContainsString("'captype' => 'read',", $output);
        self::assertStringContainsString("'captype' => 'write',", $output);
        self::assertStringContainsString("            'student' => CAP_ALLOW,", $output);
        self::assertStringContainsString("            'teacher' => CAP_ALLOW,", $output);
        self::assertStringContainsString("            'manager' => CAP_ALLOW,", $output);
        self::assertStringContainsString("'clonepermissionsfrom' => 'moodle/site:accessallgroups',", $output);

        // bravo has no clone key — its block must not carry clonepermissionsfrom.
        self::assertStringContainsString(
            "        'contextlevel' => CONTEXT_USER,\n        'archetypes' => [\n            'manager' => CAP_ALLOW,\n        ],\n    ],",
            $output,
        );
    }

    #[Test]
    public function renderServicesResolvesExistingClassAndMobileConstant(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderServices([
            new ServiceDefinition(
                name: 'get_widget',
                classname: 'local_example\external\get_widget',
                type: 'read',
                ajax: false,
                services: ['MOODLE_OFFICIAL_MOBILE_SERVICE', 'other_svc'],
                capabilities: 'local/example:view',
            ),
        ], 'local_example');

        // class_exists → use block + Short::class reference.
        self::assertStringContainsString("\n" . 'use local_example\external\get_widget;' . "\n", $output);
        self::assertStringContainsString("'classname' => get_widget::class,", $output);

        // ajax:false branch.
        self::assertStringContainsString("'ajax' => false,", $output);

        // renderServiceConstant: bare MOODLE_OFFICIAL_MOBILE_SERVICE + quoted fallback.
        self::assertStringContainsString(
            "'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE, 'other_svc'],",
            $output,
        );

        self::assertStringContainsString("'capabilities' => 'local/example:view',", $output);
        self::assertStringContainsString("'local_example_get_widget' => [", $output);
        self::assertStringContainsString('$functions = [', $output);
    }

    #[Test]
    public function renderServicesOmitsUseBlockWhenNoClassResolves(): void
    {
        $renderer = new StaticsRenderer();

        // classname is not an autoloadable class → useStatements stays empty →
        // renderUseBlock() returns '' and the classname is emitted as a quoted string.
        $output = $renderer->renderServices([
            new ServiceDefinition(name: 'do_thing', classname: 'local_example\external\unresolved'),
        ], 'local_example');

        self::assertStringNotContainsString('use ', $output);
        self::assertStringContainsString("'classname' => '" . 'local_example\external\unresolved' . "',", $output);
        // Nothing between the header comment and the $functions preamble.
        self::assertStringContainsString("Edit the source extension instead.\n\n\$functions = [", $output);
    }

    #[Test]
    public function renderWebServicesBlockReturnsEmptyStringForNoDefinitions(): void
    {
        $renderer = new StaticsRenderer();

        self::assertSame('', $renderer->renderWebServicesBlock([]));
    }

    #[Test]
    public function renderWebServicesBlockEmitsSortedServicesArray(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderWebServicesBlock([
            new WebServiceDefinition(
                name: 'Beta Service',
                shortname: 'beta_svc',
                functions: ['local_example_do', 'local_example_undo'],
                enabled: true,
                restricted_users: 0,
            ),
            new WebServiceDefinition(
                name: 'Alpha Service',
                shortname: 'alpha_svc',
                functions: ['local_example_x'],
                enabled: false,
                restricted_users: 1,
            ),
        ]);

        // No header: this block is spliced into an existing file.
        self::assertStringStartsWith("\n\$services = [", $output);
        self::assertStringNotContainsString('AUTO-GENERATED', $output);

        // Sorted by name → Alpha before Beta.
        self::assertLessThan(strpos($output, "'beta_svc'"), strpos($output, "'alpha_svc'"));

        self::assertStringContainsString("'shortname' => 'alpha_svc',", $output);
        self::assertStringContainsString("'shortname' => 'beta_svc',", $output);
        self::assertStringContainsString("'enabled' => 0,", $output);
        self::assertStringContainsString("'enabled' => 1,", $output);
        self::assertStringContainsString("'restrictedusers' => 1,", $output);
        self::assertStringContainsString("'restrictedusers' => 0,", $output);
        self::assertStringContainsString("            'local_example_do',", $output);
        self::assertStringContainsString("            'local_example_undo',", $output);
        self::assertStringContainsString("            'local_example_x',", $output);
    }

    #[Test]
    public function renderMessagesMapsPermissionConstantsAndEmptyDefaults(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderMessages([
            $this->definition('msg_full', [
                'defaults' => [
                    'forced' => MESSAGE_FORCED,
                    'permitted' => MESSAGE_PERMITTED,
                    'disallowed' => MESSAGE_DISALLOWED,
                    'zero' => 0,
                    'other' => 99,
                ],
            ]),
            $this->definition('msg_empty', []),
        ]);

        self::assertStringContainsString('derived from extension declarations (messages).', $output);
        self::assertStringContainsString('$messageproviders = [', $output);
        self::assertStringContainsString("'defaults' => [", $output);

        // renderMessagePermission: constant-name arms + zero fallback + numeric default.
        self::assertStringContainsString("'forced' => MESSAGE_FORCED,", $output);
        self::assertStringContainsString("'permitted' => MESSAGE_PERMITTED,", $output);
        self::assertStringContainsString("'disallowed' => MESSAGE_DISALLOWED,", $output);
        self::assertStringContainsString("'zero' => MESSAGE_DISALLOWED,", $output);
        self::assertStringContainsString("'other' => 99,", $output);

        // Definition without a defaults key: block has no defaults sub-array.
        self::assertStringContainsString("    'msg_empty' => [\n\n    ],", $output);
    }

    #[Test]
    public function renderHooksResolvesClassesAndEveryCallbackForm(): void
    {
        $renderer = new StaticsRenderer();

        $output = $renderer->renderHooks([
            new HookDefinition(
                hook_class: 'local_example\hook\widget_created',
                callback: ['local_example\hook\WidgetObserver', 'on_created'],
                priority: 5,
            ),
            new HookDefinition(
                hook_class: 'local_example\hook\missing_hook',
                callback: 'local_example_global_cb',
                priority: 0,
            ),
            new HookDefinition(
                hook_class: 'local_example\hook\other_missing',
                callback: ['local_example\hook\MissingObserver', 'handle'],
                priority: 0,
            ),
        ]);

        self::assertStringContainsString('derived from extension declarations (hooks).', $output);
        self::assertStringContainsString('$callbacks = [', $output);

        // Resolved hook + array callback → Short::class references + use block.
        self::assertStringContainsString("'hook' => widget_created::class,", $output);
        self::assertStringContainsString("'callback' => [WidgetObserver::class, 'on_created'],", $output);
        self::assertStringContainsString('use local_example\hook\WidgetObserver;', $output);
        self::assertStringContainsString('use local_example\hook\widget_created;', $output);

        // Non-zero priority emitted.
        self::assertStringContainsString("'priority' => 5,", $output);

        // Unresolved hook + string callback.
        self::assertStringContainsString("'hook' => 'local_example\\hook\\missing_hook',", $output);
        self::assertStringContainsString("'callback' => 'local_example_global_cb',", $output);

        // Unresolved array callback → quoted [class, method].
        self::assertStringContainsString(
            "'callback' => ['local_example\\hook\\MissingObserver', 'handle'],",
            $output,
        );

        // priority 0 defs must not emit a priority line: the missing-hook block ends
        // right after its string callback.
        self::assertStringContainsString(
            "        'callback' => 'local_example_global_cb',\n    ],",
            $output,
        );
    }

    #[Test]
    public function renderEventsEmitsCatchAllAndPerEventObservers(): void
    {
        $renderer = new StaticsRenderer();

        $courseViewed = '\core\event\course_viewed';
        $userLoggedin = '\core\event\user_loggedin';

        $output = $renderer->renderEvents([$userLoggedin, $courseViewed], 'local_example\event\observer');

        self::assertStringContainsString('derived from extension declarations (events).', $output);
        self::assertStringContainsString('$observers = [', $output);
        self::assertStringContainsString('use local_example\event\observer;', $output);

        // Catch-all wildcard entry.
        self::assertStringContainsString("'eventname' => '*',", $output);
        self::assertStringContainsString("'callback' => observer::class . '::catch_all',", $output);

        // Per-event observers (loop body), sorted ascending.
        self::assertStringContainsString("'eventname' => '" . $courseViewed . "',", $output);
        self::assertStringContainsString("'eventname' => '" . $userLoggedin . "',", $output);
        self::assertStringContainsString("'callback' => observer::class . '::observe_registered',", $output);
        self::assertLessThan(
            strpos($output, "'" . $userLoggedin . "'"),
            strpos($output, "'" . $courseViewed . "'"),
        );
    }

    /**
     * Minimal DefinitionInterface double returning a fixed toMoodleArray().
     *
     * @param array<string, mixed> $array
     */
    private function definition(string $name, array $array): DefinitionInterface
    {
        return new class($name, $array) implements DefinitionInterface {
            /** @param array<string, mixed> $moodleArray */
            public function __construct(
                private readonly string $defName,
                private readonly array $moodleArray,
            ) {}

            public function toMoodleArray(string $plugin_name): array
            {
                return $this->moodleArray;
            }

            public function isCompatible(string $moodle_version): bool
            {
                return true;
            }

            public function getName(): string
            {
                return $this->defName;
            }
        };
    }

    /**
     * Access pair whose definition also exposes get_qualified_name() (duck-typed
     * by renderAccess but absent from DefinitionInterface).
     *
     * @param array<string, mixed> $array
     *
     * @return array{definition: DefinitionInterface, extension: string}
     */
    private function accessPair(string $qualified, array $array, string $extension = 'core'): array
    {
        $definition = new class($qualified, $array) implements DefinitionInterface {
            /** @param array<string, mixed> $moodleArray */
            public function __construct(
                private readonly string $qualified,
                private readonly array $moodleArray,
            ) {}

            public function toMoodleArray(string $plugin_name): array
            {
                return $this->moodleArray;
            }

            public function isCompatible(string $moodle_version): bool
            {
                return true;
            }

            public function getName(): string
            {
                return $this->qualified;
            }

            public function get_qualified_name(string $plugin_name, ?string $extension = null): string
            {
                return $this->qualified;
            }
        };

        return ['definition' => $definition, 'extension' => $extension];
    }
}
