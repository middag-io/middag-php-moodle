<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel\Http\Trait;

use Middag\Framework\Exception\MiddagAuthorizationException as middag_authorization_exception;
use Middag\Moodle\Contract\AuthenticationInterface as authentication_interface;
use Middag\Moodle\Contract\CapabilityInterface as capability_interface;
use Middag\Moodle\Enum\ContextLevel as context_level;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trait handling authentication and authorization logic for controllers.
 *
 * Uses `authentication_interface` and `capability_interface` via DI
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

    protected ?context_level $capabilityContextLevel = null;

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
     * @param array<string>      $capabilities required capability names
     * @param null|context_level $context      Moodle context level when relevant; widened
     *                                         to `mixed` to satisfy the framework contract
     *                                         which is platform-agnostic. Non-ContextLevel
     *                                         values are stored as null.
     */
    public function setRequireCapabilities(array $capabilities, mixed $context = null, int $instanceid = 0): void
    {
        $this->capabilities = $capabilities;
        $this->capabilityContextLevel = $context instanceof context_level ? $context : null;
        $this->capabilityInstanceId = $instanceid;
    }

    /**
     * Ensure the user is logged in if required.
     */
    protected function requireLogin(): void
    {
        if ($this->requireLogin) {
            $this->authentication()->require_login(
                $this->course?->get_id(),
                true,
            );
            $this->requiredLogin = true;
        }

        // Protects non-idempotent methods with sesskey when required.
        if ($this->requireSesskey && isset($this->request)) {
            $method = strtoupper($this->request->getMethod());
            if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                $this->authentication()->require_sesskey();
            }
        }
    }

    /**
     * Check if the user has the required capabilities.
     *
     * @throws middag_authorization_exception
     */
    protected function checkCapabilities(): void
    {
        $contextlevel = $this->capabilityContextLevel ?? context_level::SYSTEM;

        foreach ($this->capabilities as $capability) {
            $this->capability()->authorize($capability, $contextlevel, $this->capabilityInstanceId);
        }
    }

    /**
     * Resolve the authentication adapter from the container.
     */
    private function authentication(): authentication_interface
    {
        return $this->container->get(authentication_interface::class);
    }

    /**
     * Resolve the capability adapter from the container.
     */
    private function capability(): capability_interface
    {
        return $this->container->get(capability_interface::class);
    }
}
