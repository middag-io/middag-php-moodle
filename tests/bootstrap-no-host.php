<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

use Middag\Framework\Bus\Contract\UserContextResolverInterface;
use Middag\Moodle\Config\ComponentContext;

/*
 * No-host PHPUnit bootstrap (tests/NoHost suite, phpunit.no-host.xml).
 *
 * Unlike tests/bootstrap.php this defines NO Moodle stand-ins at all: no
 * constants, no function stubs, no eval'd core classes. That makes the
 * adapter's host-absence branches — the class_exists()/function_exists()
 * guards that keep the library loadable outside a Moodle runtime — actually
 * reachable, where the stubbed bootstrap satisfies every guard and leaves
 * them dead. Only the Composer autoloader and the component seam are set up.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Mirror the product composition root (same seam as tests/bootstrap.php).
ComponentContext::configure('local_example', 'local_example_autoload');

// Framework interface stubs if not resolved by the autoloader.
if (!interface_exists(UserContextResolverInterface::class, false)) {
    require_once __DIR__ . '/stubs/framework-stubs.php';
}
