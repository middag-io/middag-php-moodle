<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Bus;

use Middag\Framework\Kernel\Manager\HookManager;
use Middag\Moodle\Bus\MoodleHostEventBridge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(MoodleHostEventBridge::class)]
final class MoodleHostEventBridgeTest extends TestCase
{
    private HookManager $hooks;

    private MoodleHostEventBridge $bridge;

    protected function setUp(): void
    {
        $this->hooks = new HookManager();
        $this->bridge = new MoodleHostEventBridge($this->hooks);
    }

    #[Test]
    public function dispatchInvokesRegisteredListenerWithPositionalPayload(): void
    {
        $received = [];

        $this->bridge->listen('organization.created', static function (int $orgId, string $name) use (&$received): void {
            $received = [$orgId, $name];
        });

        $this->bridge->dispatch('organization.created', [42, 'Acme']);

        $this->assertSame([42, 'Acme'], $received);
    }

    #[Test]
    public function dispatchWithNoListenersIsNoop(): void
    {
        $this->bridge->dispatch('nobody.listening', ['payload']);

        $this->assertSame(0, $this->hooks->didAction('unrelated.event'));
        $this->assertSame(1, $this->hooks->didAction('nobody.listening'));
    }

    #[Test]
    public function listenRegistersActionDiscoverableOnTheHookManager(): void
    {
        $this->assertFalse($this->hooks->hasAction('user.enrolled'));

        $this->bridge->listen('user.enrolled', static fn (): null => null);

        $this->assertTrue($this->hooks->hasAction('user.enrolled'));
    }

    #[Test]
    public function listenHonoursPriorityOrdering(): void
    {
        $order = [];

        $this->bridge->listen('ordered.event', static function () use (&$order): void {
            $order[] = 'low-priority';
        }, 20);

        $this->bridge->listen('ordered.event', static function () use (&$order): void {
            $order[] = 'high-priority';
        }, 5);

        $this->bridge->dispatch('ordered.event');

        $this->assertSame(['high-priority', 'low-priority'], $order);
    }

    #[Test]
    public function dispatchWithEmptyPayloadStillFiresListener(): void
    {
        $fired = false;

        $this->bridge->listen('ping', static function () use (&$fired): void {
            $fired = true;
        });

        $this->bridge->dispatch('ping');

        $this->assertTrue($fired);
    }

    #[Test]
    public function isFinalReadonly(): void
    {
        $reflection = new ReflectionClass(MoodleHostEventBridge::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
    }
}
