<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

use Middag\Moodle\Tests\Integration\Engine\DbSupportEngineProbe;

/*
 * Runs DbSupportEngineProbe inside an installed Moodle site.
 *
 * Usage:
 *   php tests/Integration/Engine/run-db-support-probe.php \
 *       --config=/path/to/moodle/config.php \
 *       --autoload=/path/to/consumer/vendor/autoload.php
 *
 * --config   the site's config.php (bootstraps Moodle and its database)
 * --autoload the Composer autoloader of a project that installed this library
 *
 * Exit code 0 when every DbSupport sql* method is covered and every check
 * passes; 1 on any failure; 2 on a usage error.
 */

if (PHP_SAPI !== 'cli') {
    exit(2);
}

$options = getopt('', ['config:', 'autoload:']);
$config = is_string($options['config'] ?? null) ? $options['config'] : '';
$autoload = is_string($options['autoload'] ?? null) ? $options['autoload'] : '';
if (!is_file($config) || !is_file($autoload)) {
    fwrite(STDERR, "Usage: php run-db-support-probe.php --config=<moodle config.php> --autoload=<vendor/autoload.php>\n");

    exit(2);
}

define('CLI_SCRIPT', true);

require $config;

require $autoload;

require_once __DIR__ . '/DbSupportEngineProbe.php';

try {
    exit((new DbSupportEngineProbe())->run());
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Probe aborted: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL . $throwable->getTraceAsString() . PHP_EOL);

    exit(1);
}
