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

use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Moodle\Contract\Attributes\Sesskey;
use Middag\Moodle\Contract\MoodleControllerInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * HTTP Kernel Moodle-flavor.
 *
 * Estende o `HttpKernel` agnóstico do framework para aplicar atributos
 * Moodle-specific (#[Sesskey]) após o framework `Auth` ser processado.
 *
 * @internal
 */
final class MoodleHttpKernel extends HttpKernel
{
    /**
     * Lê o atributo `#[Sesskey]` (método > classe) e chama
     * `set_require_sesskey()` no controller quando exigido.
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
