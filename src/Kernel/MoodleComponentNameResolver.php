<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel;

use Middag\Framework\Kernel\Contract\ComponentNameResolverInterface;
use Middag\Moodle\Config\ComponentContext;

/**
 * Resolves the native Moodle frankenstyle component (e.g. {@code local_example})
 * for the running plugin so framework-side policies can classify
 * `{component}\extensions\{slug}\...` FQCNs as native vs third-party.
 *
 * @internal
 */
final readonly class MoodleComponentNameResolver implements ComponentNameResolverInterface
{
    public function nativeComponent(): string
    {
        return ComponentContext::name();
    }
}
