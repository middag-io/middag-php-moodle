<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Statics;

use Middag\Moodle\Definition\DefinitionInterface;
use Middag\Moodle\Definition\WebService;
use ReflectionClass;

/**
 * Renders Moodle db/*.php files from collected definition objects.
 *
 * One public method per target. All return self-contained PHP source ready
 * to write to disk. Pure functions — no filesystem access.
 *
 * Method names mirror the file paths they generate (e.g. `renderAccess`
 * produces `db/access.php`).
 *
 * @api
 */
final readonly class StaticsRenderer
{
    public const DEFAULT_MARKER = '// AUTO-GENERATED FILE';

    public function __construct(
        private string $marker = self::DEFAULT_MARKER,
    ) {}

    /**
     * @param list<DefinitionInterface> $definitions
     */
    public function renderCaches(array $definitions): string
    {
        usort($definitions, static fn ($a, $b): int => $a->getName() <=> $b->getName());

        $entries = [];
        foreach ($definitions as $def) {
            $array = $def->toMoodleArray('');
            $parts = [];
            $parts[] = sprintf("        'mode' => %s,", $this->renderCacheMode((int) $array['mode']));

            if (!empty($array['simplekeys'])) {
                $parts[] = "        'simplekeys' => true,";
            }
            if (!empty($array['simpledata'])) {
                $parts[] = "        'simpledata' => true,";
            }

            $entries[] = sprintf("    '%s' => [\n%s\n    ],", $def->getName(), implode("\n", $parts));
        }

        return $this->header('caches') . "\$definitions = [\n" . implode("\n", $entries) . "\n];\n";
    }

    /**
     * @param list<array{definition: DefinitionInterface, extension: string}> $pairs
     */
    public function renderAccess(array $pairs, string $pluginName): string
    {
        usort(
            $pairs,
            static fn (array $a, array $b): int => $a['definition']->get_qualified_name($pluginName, $a['extension'])
                <=> $b['definition']->get_qualified_name($pluginName, $b['extension']),
        );

        $entries = [];
        foreach ($pairs as $pair) {
            $def = $pair['definition'];
            $array = $def->toMoodleArray($pluginName);
            $qualified = $def->get_qualified_name($pluginName, $pair['extension']);

            $parts = [];
            $parts[] = sprintf("        'riskbitmask' => %s,", $this->renderRiskConstant((int) $array['riskbitmask']));
            $parts[] = sprintf("        'captype' => '%s',", $array['captype']);
            $parts[] = sprintf("        'contextlevel' => %s,", $this->renderContextConstant((int) $array['contextlevel']));

            $archetypesLines = [];
            foreach (array_keys($array['archetypes']) as $role) {
                $archetypesLines[] = sprintf("            '%s' => CAP_ALLOW,", $role);
            }
            $parts[] = "        'archetypes' => [\n" . implode("\n", $archetypesLines) . "\n        ],";

            if (isset($array['clonepermissionsfrom'])) {
                $parts[] = sprintf("        'clonepermissionsfrom' => '%s',", $array['clonepermissionsfrom']);
            }

            $entries[] = sprintf("    '%s' => [\n%s\n    ],", $qualified, implode("\n", $parts));
        }

        return $this->header('access') . "\$capabilities = [\n" . implode("\n\n", $entries) . "\n];\n";
    }

    /**
     * @param list<DefinitionInterface> $definitions
     */
    public function renderServices(array $definitions, string $pluginName): string
    {
        usort(
            $definitions,
            static fn ($a, $b): int => $a->get_qualified_name($pluginName) <=> $b->get_qualified_name($pluginName),
        );

        $useStatements = [];
        foreach ($definitions as $def) {
            $array = $def->toMoodleArray($pluginName);
            $classname = (string) $array['classname'];
            if (class_exists($classname)) {
                $useStatements[$classname] = $classname;
            }
        }

        $useBlock = $this->renderUseBlock($useStatements);

        $entries = [];
        foreach ($definitions as $def) {
            $array = $def->toMoodleArray($pluginName);
            $qualified = $def->get_qualified_name($pluginName);
            $classname = (string) $array['classname'];

            $classnameRef = "'" . $classname . "'";
            if (class_exists($classname)) {
                $short = (new ReflectionClass($classname))->getShortName();
                $classnameRef = $short . '::class';
            }

            $parts = [];
            $parts[] = sprintf("        'classname' => %s,", $classnameRef);
            $parts[] = sprintf("        'methodname' => '%s',", $array['methodname']);
            $parts[] = sprintf("        'description' => '%s',", addslashes((string) $array['description']));
            $parts[] = sprintf("        'type' => '%s',", $array['type']);
            $parts[] = sprintf("        'ajax' => %s,", empty($array['ajax']) ? 'false' : 'true');

            if (!empty($array['capabilities'])) {
                $parts[] = sprintf("        'capabilities' => '%s',", addslashes((string) $array['capabilities']));
            }

            if (!empty($array['services'])) {
                $servicesRendered = [];
                foreach ($array['services'] as $svc) {
                    $servicesRendered[] = $this->renderServiceConstant((string) $svc);
                }
                $parts[] = "        'services' => [" . implode(', ', $servicesRendered) . '],';
            }

            $entries[] = sprintf("    '%s' => [\n%s\n    ],", $qualified, implode("\n", $parts));
        }

        return $this->header('services') . $useBlock . "\n\$functions = [\n" . implode("\n", $entries) . "\n];\n";
    }

    /**
     * @param list<WebService> $definitions
     */
    public function renderWebServicesBlock(array $definitions): string
    {
        if ($definitions === []) {
            return '';
        }

        usort($definitions, static fn ($a, $b): int => $a->name <=> $b->name);

        $entries = [];
        foreach ($definitions as $def) {
            $array = $def->toMoodleArray('');
            $parts = [];
            $parts[] = sprintf("        'shortname' => '%s',", $array['shortname']);
            $parts[] = sprintf("        'enabled' => %d,", (int) $array['enabled']);
            $parts[] = sprintf("        'restrictedusers' => %d,", (int) $array['restrictedusers']);

            $funcItems = [];
            foreach ($array['functions'] as $fn) {
                $funcItems[] = sprintf("            '%s',", $fn);
            }
            $parts[] = "        'functions' => [\n" . implode("\n", $funcItems) . "\n        ],";

            $entries[] = sprintf("    '%s' => [\n%s\n    ],", $def->name, implode("\n", $parts));
        }

        return "\n\$services = [\n" . implode("\n", $entries) . "\n];";
    }

    /**
     * @param list<DefinitionInterface> $definitions
     */
    public function renderMessages(array $definitions): string
    {
        usort($definitions, static fn ($a, $b): int => $a->getName() <=> $b->getName());

        $entries = [];
        foreach ($definitions as $def) {
            $array = $def->toMoodleArray('');
            $parts = [];

            if (isset($array['defaults']) && is_array($array['defaults'])) {
                $defaultsLines = [];
                foreach ($array['defaults'] as $channel => $permission) {
                    $defaultsLines[] = sprintf(
                        "            '%s' => %s,",
                        (string) $channel,
                        $this->renderMessagePermission((int) $permission),
                    );
                }
                $parts[] = "        'defaults' => [\n" . implode("\n", $defaultsLines) . "\n        ],";
            }

            $entries[] = sprintf("    '%s' => [\n%s\n    ],", $def->getName(), implode("\n", $parts));
        }

        return $this->header('messages') . "\$messageproviders = [\n" . implode("\n", $entries) . "\n];\n";
    }

    /**
     * @param list<DefinitionInterface> $definitions
     */
    public function renderHooks(array $definitions): string
    {
        usort(
            $definitions,
            static fn ($a, $b): int => (string) $a->toMoodleArray('')['hook']
                <=> (string) $b->toMoodleArray('')['hook'],
        );

        $useStatements = [];
        foreach ($definitions as $def) {
            $array = $def->toMoodleArray('');
            $hookClass = $array['hook'] ?? null;
            if (is_string($hookClass) && class_exists($hookClass)) {
                $useStatements[$hookClass] = $hookClass;
            }
            $callback = $array['callback'] ?? null;
            if (is_array($callback) && isset($callback[0]) && is_string($callback[0]) && class_exists($callback[0])) {
                $useStatements[$callback[0]] = $callback[0];
            }
        }

        $useBlock = $this->renderUseBlock($useStatements);

        $entries = [];
        foreach ($definitions as $def) {
            $array = $def->toMoodleArray('');
            $parts = [];

            $hookClass = (string) ($array['hook'] ?? '');
            if (class_exists($hookClass)) {
                $short = (new ReflectionClass($hookClass))->getShortName();
                $parts[] = sprintf("        'hook' => %s::class,", $short);
            } else {
                $parts[] = sprintf("        'hook' => '%s',", $hookClass);
            }

            $callback = $array['callback'] ?? null;
            if (is_array($callback) && count($callback) === 2) {
                $cls = (string) $callback[0];
                $method = (string) $callback[1];
                if (class_exists($cls)) {
                    $short = (new ReflectionClass($cls))->getShortName();
                    $parts[] = sprintf("        'callback' => [%s::class, '%s'],", $short, $method);
                } else {
                    $parts[] = sprintf("        'callback' => ['%s', '%s'],", $cls, $method);
                }
            } elseif (is_string($callback)) {
                $parts[] = sprintf("        'callback' => '%s',", $callback);
            }

            if (isset($array['priority']) && (int) $array['priority'] !== 0) {
                $parts[] = sprintf("        'priority' => %d,", (int) $array['priority']);
            }

            $entries[] = "    [\n" . implode("\n", $parts) . "\n    ],";
        }

        return $this->header('hooks') . $useBlock . "\n\$callbacks = [\n" . implode("\n", $entries) . "\n];\n";
    }

    /**
     * @param list<string> $events        sorted, normalized event class names
     * @param class-string $observerClass FQCN of the observer that handles dispatched events
     */
    public function renderEvents(array $events, string $observerClass): string
    {
        sort($events);

        $observerShort = (new ReflectionClass($observerClass))->getShortName();
        $useBlock = sprintf("\nuse %s;\n", $observerClass);

        $entries = [];
        $entries[] = <<<PHP
    [
        'eventname' => '*',
        'callback' => {$observerShort}::class . '::catch_all',
        'internal' => false,
    ],
PHP;

        foreach ($events as $event) {
            $entries[] = <<<PHP
    [
        'eventname' => '{$event}',
        'callback' => {$observerShort}::class . '::observe_registered',
        'internal' => false,
    ],
PHP;
        }

        return $this->header('events') . $useBlock . "\n\$observers = [\n" . implode("\n", $entries) . "\n];\n";
    }

    private function header(string $target): string
    {
        $marker = $this->marker;

        return <<<PHP
            <?php

            declare(strict_types=1);

            defined('MOODLE_INTERNAL') || exit;

            {$marker} — derived from extension declarations ({$target}).
            // Changes here will be overwritten. Edit the source extension instead.

            PHP;
    }

    /**
     * @param array<string, string> $useStatements
     */
    private function renderUseBlock(array $useStatements): string
    {
        if ($useStatements === []) {
            return '';
        }

        $lines = [];
        foreach ($useStatements as $fqcn) {
            $lines[] = sprintf('use %s;', $fqcn);
        }
        sort($lines);

        return "\n" . implode("\n", $lines) . "\n";
    }

    private function renderCacheMode(int $mode): string
    {
        return match ($mode) {
            1 => 'cache_store::MODE_APPLICATION',
            2 => 'cache_store::MODE_SESSION',
            4 => 'cache_store::MODE_REQUEST',
            default => (string) $mode,
        };
    }

    private function renderRiskConstant(int $risk): string
    {
        return [
            1 => 'RISK_SPAM',
            2 => 'RISK_PERSONAL',
            4 => 'RISK_XSS',
            8 => 'RISK_CONFIG',
            16 => 'RISK_DATALOSS',
        ][$risk] ?? (string) $risk;
    }

    private function renderContextConstant(int $context): string
    {
        return [
            10 => 'CONTEXT_SYSTEM',
            30 => 'CONTEXT_USER',
            40 => 'CONTEXT_COURSECAT',
            50 => 'CONTEXT_COURSE',
            70 => 'CONTEXT_MODULE',
            80 => 'CONTEXT_BLOCK',
        ][$context] ?? (string) $context;
    }

    private function renderServiceConstant(string $service): string
    {
        if ($service === 'MOODLE_OFFICIAL_MOBILE_SERVICE') {
            return 'MOODLE_OFFICIAL_MOBILE_SERVICE';
        }

        return "'" . addslashes($service) . "'";
    }

    private function renderMessagePermission(int $permission): string
    {
        if (defined('MESSAGE_FORCED') && $permission === MESSAGE_FORCED) {
            return 'MESSAGE_FORCED';
        }
        if (defined('MESSAGE_PERMITTED') && $permission === MESSAGE_PERMITTED) {
            return 'MESSAGE_PERMITTED';
        }
        if (defined('MESSAGE_DISALLOWED') && $permission === MESSAGE_DISALLOWED) {
            return 'MESSAGE_DISALLOWED';
        }
        if ($permission === 0) {
            return 'MESSAGE_DISALLOWED';
        }

        return (string) $permission;
    }
}
