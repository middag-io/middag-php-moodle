<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Bus;

use core\task\adhoc_task;
use LogicException;
use Middag\Framework\Bus\Command\CommandWorker;
use Middag\Framework\Bus\Contract\CommandInterface;
use Middag\Framework\Bus\Contract\TransportInterface;
use Middag\Moodle\Domain\Task\Contract\AdhocServiceInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * Send-only Messenger transport over Moodle's adhoc task queue.
 *
 * send() persists the envelope's command as an adhoc task carrying
 * `{command_class, payload}` custom data — the shape the task class
 * deserializes back into a command in cron context. The receive side
 * (get/ack/reject) is owned by Moodle cron, which instantiates and executes
 * the queued task itself; a {@see CommandWorker}
 * drain loop is therefore unsupported and those methods throw.
 *
 * The task class is injected as a string so this OSS adapter stays free of
 * non-OSS MIDDAG imports — the non-OSS product wiring decides which adhoc task
 * re-dispatches the command.
 *
 * Envelope stamps are NOT persisted: only the command crosses the queue
 * boundary. The task re-dispatches with a fresh ReceivedStamp on execution.
 *
 * @api
 */
final readonly class MoodleAdhocTransport implements TransportInterface
{
    /**
     * @param AdhocServiceInterface    $adhocService creates and queues the adhoc task
     * @param class-string<adhoc_task> $taskClass    adhoc task that re-dispatches the command in cron
     */
    public function __construct(
        private AdhocServiceInterface $adhocService,
        private string $taskClass,
    ) {}

    public function send(Envelope $envelope): Envelope
    {
        $command = $envelope->getMessage();

        if (!$command instanceof CommandInterface) {
            throw new LogicException(sprintf(
                'MoodleAdhocTransport only carries %s messages; got %s.',
                CommandInterface::class,
                $command::class,
            ));
        }

        $task = $this->adhocService->create($this->taskClass, [
            'command_class' => $command::class,
            'payload' => $command->toPayload(),
        ]);

        $this->adhocService->queue($task);

        return $envelope;
    }

    /**
     * @return iterable<Envelope>
     */
    public function get(): iterable
    {
        throw new LogicException('Moodle cron owns the receive side; draining this transport is unsupported.');
    }

    public function ack(Envelope $envelope): void
    {
        throw new LogicException('Moodle cron owns the receive side; ack is unsupported.');
    }

    public function reject(Envelope $envelope): void
    {
        throw new LogicException('Moodle cron owns the receive side; reject is unsupported.');
    }
}
