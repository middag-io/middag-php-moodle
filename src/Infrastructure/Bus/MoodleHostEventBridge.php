<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Bus;

use Middag\Framework\Kernel\Contract\HookManagerInterface;
use Middag\Framework\Kernel\Contract\HostEventBridgeInterface;

/**
 * Moodle host event bridge.
 *
 * Reference implementation of the framework's platform-agnostic, `@api`
 * {@see HostEventBridgeInterface} seam: delegates to the framework
 * {@see HookManagerInterface} so named events are broadcast as action hooks and
 * listeners are registered as action callbacks. Execution is synchronous and
 * in-process. The host wiring boundary binds this class to the interface, so
 * governed listeners depend only on the framework contract.
 *
 * This is the basic host eventing seam — distinct from the premium signal layer
 * (the SignalDispatcher/hook-bridge/outbox stack in the non-OSS MIDDAG layer),
 * which maps typed Moodle core events onto MIDDAG signals.
 */
final readonly class MoodleHostEventBridge implements HostEventBridgeInterface
{
    public function __construct(
        private HookManagerInterface $hooks,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function dispatch(string $eventName, array $payload = []): void
    {
        $this->hooks->doAction($eventName, ...$payload);
    }

    /**
     * {@inheritDoc}
     */
    public function listen(string $eventName, callable $listener, int $priority = 10): void
    {
        $this->hooks->addAction($eventName, $listener, $priority, PHP_INT_MAX);
    }
}
