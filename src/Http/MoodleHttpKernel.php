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
use Middag\Moodle\Support\SessionSupport;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moodle-flavored HTTP Kernel.
 *
 * Extends the framework's host-agnostic `HttpKernel` to apply
 * Moodle-specific attributes (#[Sesskey]) after the framework `Auth`
 * attribute has been processed, and to publish the CSRF token as a
 * JS-readable cookie.
 *
 * @internal
 */
final class MoodleHttpKernel extends HttpKernel
{
    /**
     * Publish the CSRF token to the browser alongside every response.
     *
     * The cookie is deliberately NOT httpOnly: the client has to read it. The
     * axios instance behind the Inertia client echoes it back as
     * `X-XSRF-TOKEN`, which {@see AbstractController::csrfTokenFromRequest()}
     * accepts — so Inertia visits satisfy the gate with no client change.
     * `SameSite=Lax` keeps it off cross-site requests.
     *
     * No secret is exposed: Moodle already puts the same sesskey in page URLs,
     * in form fields and in `M.cfg.sesskey`.
     */
    protected function handleSymfony(Request $symfonyRequest): Response
    {
        $response = parent::handleSymfony($symfonyRequest);

        $sesskey = SessionSupport::sesskey();

        if ($sesskey !== '') {
            $response->headers->setCookie(
                new Cookie('XSRF-TOKEN', $sesskey, 0, '/', null, false, false, false, Cookie::SAMESITE_LAX),
            );
        }

        return $response;
    }

    /**
     * Reads the `#[Sesskey]` attribute (method > class) and calls
     * `setRequireSesskey()` on the controller.
     *
     * With CSRF on by default, the attribute's job is the opt-out: a route or
     * class marked `#[Sesskey(require: false)]` reaches
     * `setRequireSesskey(false)` and skips the gate. `#[Sesskey]` with no
     * argument is now redundant but harmless.
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

        if (!$controller instanceof MoodleControllerInterface) {
            return;
        }

        $controller->setRequireSesskey($sesskey->require);
    }
}
