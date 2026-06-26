<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Infrastructure\Bus;

use core\task\adhoc_task;
use LogicException;
use Middag\Framework\Bus\Contract\CommandInterface;
use Middag\Moodle\Contract\AdhocServiceInterface;
use Middag\Moodle\Infrastructure\Bus\MoodleAdhocTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;

/**
 * send() queues the command as an adhoc task with {command_class, payload}
 * custom data; the receive side belongs to Moodle cron and throws.
 *
 * @internal
 */
#[CoversClass(MoodleAdhocTransport::class)]
final class MoodleAdhocTransportTest extends TestCase
{
    private const TASK_CLASS = FakeAsyncTask::class;

    public function testSendQueuesAdhocTaskWithCommandClassAndPayload(): void
    {
        $command = new TransportRecordedCommand(42);
        $task = new FakeAsyncTask();

        $adhoc = $this->createMock(AdhocServiceInterface::class);
        $adhoc->expects(self::once())
            ->method('create')
            ->with(self::TASK_CLASS, [
                'command_class' => TransportRecordedCommand::class,
                'payload' => ['transaction_id' => 42],
            ])
            ->willReturn($task);
        $adhoc->expects(self::once())
            ->method('queue')
            ->with($task)
            ->willReturn(true);

        $transport = new MoodleAdhocTransport($adhoc, self::TASK_CLASS);

        $envelope = new Envelope($command);

        self::assertSame($envelope, $transport->send($envelope));
    }

    public function testSendRejectsNonCommandMessages(): void
    {
        $adhoc = $this->createMock(AdhocServiceInterface::class);
        $adhoc->expects(self::never())->method('create');

        $transport = new MoodleAdhocTransport($adhoc, self::TASK_CLASS);

        $this->expectException(LogicException::class);

        $transport->send(new Envelope(new stdClass()));
    }

    public function testReceiveSideIsUnsupported(): void
    {
        $transport = new MoodleAdhocTransport(
            $this->createStub(AdhocServiceInterface::class),
            self::TASK_CLASS,
        );

        $this->expectException(LogicException::class);

        $transport->get();
    }

    public function testAckIsUnsupported(): void
    {
        $transport = new MoodleAdhocTransport(
            $this->createStub(AdhocServiceInterface::class),
            self::TASK_CLASS,
        );

        $this->expectException(LogicException::class);

        $transport->ack(new Envelope(new stdClass()));
    }

    public function testRejectIsUnsupported(): void
    {
        $transport = new MoodleAdhocTransport(
            $this->createStub(AdhocServiceInterface::class),
            self::TASK_CLASS,
        );

        $this->expectException(LogicException::class);

        $transport->reject(new Envelope(new stdClass()));
    }
}

/**
 * Serializable command fixture.
 *
 * @internal
 */
final readonly class TransportRecordedCommand implements CommandInterface
{
    public function __construct(public int $transactionId) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return ['transaction_id' => $this->transactionId];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new self((int) ($payload['transaction_id'] ?? 0));
    }
}

/**
 * Concrete adhoc task fixture (base class stubbed in tests/bootstrap.php).
 *
 * @internal
 */
final class FakeAsyncTask extends adhoc_task
{
    public function execute(): void {}
}
