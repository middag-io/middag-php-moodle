<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Runtime\Facade\Fixture;

use Middag\Moodle\Runtime\Facade\AbstractFacade;

/**
 * Stand-in for a THIRD-PARTY plugin facade: extends the OSS adapter base and
 * points its accessor at a service the plugin itself registered in its own
 * container builder. No middag-io/core anywhere in the chain.
 *
 * @method static string greet(string $name)
 *
 * @internal
 */
final class ThirdPartyGreeterFacade extends AbstractFacade
{
    public static function getFacadeAccessor(): string
    {
        return ThirdPartyGreeter::class;
    }
}
