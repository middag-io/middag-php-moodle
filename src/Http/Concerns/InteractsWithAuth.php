<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Http\Concerns;

use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Moodle\Domain\Context\ContextLevel;
use Middag\Moodle\Security\Contract\AuthenticationInterface;
use Middag\Moodle\Security\Contract\CapabilityInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trait handling authentication and authorization logic for controllers.
 *
 * Uses `AuthenticationInterface` and `CapabilityInterface` via DI
 * instead of calling support facades directly.
 *
 * @property Request $request
 * @property mixed   $course
 * @property mixed   $cm
 *
 * @internal
 */
trait InteractsWithAuth
{
    protected bool $requireLogin = false;

    protected bool $requireSesskey = false;

    protected bool $requiredLogin = false;

    protected array $capabilities = [];

    protected ?ContextLevel $capabilityContextLevel = null;

    protected int $capabilityInstanceId = 0;

    /**
     * Set if login is required and set related options.
     */
    public function setRequireLogin(mixed $course = null, mixed $cm = null): void
    {
        $this->requireLogin = true;
        $this->course = $course;
        $this->cm = $cm;
    }

    /**
     * Define the requirement of sesskey validation for non-idempotent requests.
     */
    public function setRequireSesskey(bool $require = true): void
    {
        $this->requireSesskey = $require;
    }

    /**
     * Define the capabilities that the user must have.
     *
     * @param array<string>            $capabilities required capability names
     * @param null|ContextLevel|string $context      Moodle context level when relevant. A
     *                                               {@see ContextLevel} is kept as-is; a name string
     *                                               (the framework's platform-agnostic `context`) is
     *                                               resolved via {@see ContextLevel::fromString()} so
     *                                               `#[Auth(context: 'course')]` reaches the check
     *                                               instead of degrading to SYSTEM. Unknown name → null.
     */
    public function setRequireCapabilities(array $capabilities, mixed $context = null, int $instanceid = 0): void
    {
        $this->capabilities = $capabilities;
        $this->capabilityContextLevel = match (true) {
            $context instanceof ContextLevel => $context,
            is_string($context) => ContextLevel::fromString($context),
            default => null,
        };
        $this->capabilityInstanceId = $instanceid;
    }

    /**
     * Ensure the user is logged in if required.
     */
    protected function requireLogin(): void
    {
        if ($this->requireLogin) {
            $this->authentication()->requireLogin(
                $this->course?->get_id(),
                true,
            );
            $this->requiredLogin = true;
        }

        // Protects non-idempotent methods with sesskey when required.
        if ($this->requireSesskey && isset($this->request)) {
            $method = strtoupper($this->request->getMethod());
            if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                $this->authentication()->requireSesskey();
            }
        }
    }

    /**
     * Check if the user has the required capabilities.
     *
     * @throws MiddagAuthorizationException
     */
    protected function checkCapabilities(): void
    {
        $contextlevel = $this->capabilityContextLevel ?? ContextLevel::SYSTEM;

        foreach ($this->capabilities as $capability) {
            $this->capability()->authorize($capability, $contextlevel, $this->capabilityInstanceId);
        }
    }

    /**
     * Resolve the authentication adapter from the container.
     */
    private function authentication(): AuthenticationInterface
    {
        return $this->container->get(AuthenticationInterface::class);
    }

    /**
     * Resolve the capability adapter from the container.
     */
    private function capability(): CapabilityInterface
    {
        return $this->container->get(CapabilityInterface::class);
    }
}
