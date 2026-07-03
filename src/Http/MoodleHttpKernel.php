<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Http;

use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Moodle\Http\Contract\MoodleControllerInterface;
use Middag\Moodle\Security\Attribute\Sesskey;
use ReflectionClass;
use ReflectionMethod;

/**
 * Moodle-flavored HTTP Kernel.
 *
 * Extends the framework's host-agnostic `HttpKernel` to apply
 * Moodle-specific attributes (#[Sesskey]) after the framework `Auth`
 * attribute has been processed.
 *
 * @internal
 */
final class MoodleHttpKernel extends HttpKernel
{
    /**
     * Reads the `#[Sesskey]` attribute (method > class) and calls
     * `setRequireSesskey()` on the controller when required.
     */
    protected function applyPlatformAuth(ControllerInterface $controller, string $method): void
    {
        $attrs = (new ReflectionMethod($controller, $method))->getAttributes(Sesskey::class);

        if ($attrs === []) {
            $attrs = (new ReflectionClass($controller))->getAttributes(Sesskey::class);
        }

        if ($attrs === []) {
            return;
        }

        /** @var Sesskey $sesskey */
        $sesskey = $attrs[0]->newInstance();

        if (!$sesskey->require) {
            return;
        }

        if (!$controller instanceof MoodleControllerInterface) {
            return;
        }

        $controller->setRequireSesskey();
    }
}
