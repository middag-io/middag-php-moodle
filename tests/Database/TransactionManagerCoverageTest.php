<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Database;

use Error;
use Middag\Moodle\Database\Contract\TransactionManagerInterface;
use Middag\Moodle\Database\TransactionManager;
use moodle_database;
use moodle_transaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test TransactionManager.
 *
 * Maps atomic/graceful operations over Moodle delegated transactions. The global
 * $DB (mocked moodle_database) supplies the transaction handle and, for graceful
 * mode, is_transaction_started(). Rollback-that-throws is emulated with a spy.
 *
 * @internal
 */
#[CoversClass(TransactionManager::class)]
final class TransactionManagerCoverageTest extends TestCase
{
    private MockObject&moodle_database $db;

    private TransactionManager $manager;

    private mixed $prevDb;

    protected function setUp(): void
    {
        $this->db = $this->createMock(moodle_database::class);
        $this->manager = new TransactionManager();
        $this->prevDb = $GLOBALS['DB'] ?? null;
        $GLOBALS['DB'] = $this->db;
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
    }

    // --- executeAtomic --------------------------------------------------

    #[Test]
    public function atomicCommitsAndReturnsResult(): void
    {
        $txn = new moodle_transaction();
        $this->db->method('start_delegated_transaction')->willReturn($txn);

        $result = $this->manager->executeAtomic(static fn (): int => 99);

        $this->assertSame(99, $result);
        $this->assertTrue($txn->committed);
    }

    #[Test]
    public function atomicRollsBackWithTheExceptionAndRethrows(): void
    {
        $txn = new moodle_transaction();
        $this->db->method('start_delegated_transaction')->willReturn($txn);
        $boom = new RuntimeException('atomic boom');

        try {
            $this->manager->executeAtomic(static function () use ($boom): never {
                throw $boom;
            });
            $this->fail('expected rethrow');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame($boom, $runtimeException);
            $this->assertSame($boom, $txn->rolledback);
        }
    }

    #[Test]
    public function atomicWrapsNonExceptionThrowableForRollbackButRethrowsOriginal(): void
    {
        $txn = new moodle_transaction();
        $this->db->method('start_delegated_transaction')->willReturn($txn);

        $this->expectException(Error::class);

        $this->manager->executeAtomic(static function (): never {
            throw new Error('fatal-ish');
        });
    }

    #[Test]
    public function atomicSuppressesRollbackFailureAndRethrowsOriginal(): void
    {
        $this->db->method('start_delegated_transaction')->willReturn($this->throwingRollbackTransaction());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('original');

        $this->manager->executeAtomic(static function (): never {
            throw new RuntimeException('original');
        });
    }

    // --- executeGraceful ------------------------------------------------

    #[Test]
    public function gracefulCommitsAndReturnsResult(): void
    {
        $txn = new moodle_transaction();
        $this->db->method('start_delegated_transaction')->willReturn($txn);

        $this->assertSame('ok', $this->manager->executeGraceful(static fn (): string => 'ok'));
        $this->assertTrue($txn->committed);
    }

    #[Test]
    public function gracefulRethrowsWhenTransactionStillOpen(): void
    {
        $this->db->method('start_delegated_transaction')->willReturn(new moodle_transaction());
        $this->db->method('is_transaction_started')->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nested');

        $this->manager->executeGraceful(static function (): never {
            throw new RuntimeException('nested');
        });
    }

    #[Test]
    public function gracefulReturnsThrowableWhenTransactionFullyUnwound(): void
    {
        $this->db->method('start_delegated_transaction')->willReturn(new moodle_transaction());
        $this->db->method('is_transaction_started')->willReturn(false);
        $boom = new RuntimeException('swallowed');

        $result = $this->manager->executeGraceful(static function () use ($boom): never {
            throw $boom;
        });

        $this->assertSame($boom, $result);
    }

    #[Test]
    public function gracefulWrapsNonExceptionThrowableAndSwallowsWhenUnwound(): void
    {
        $this->db->method('start_delegated_transaction')->willReturn($this->throwingRollbackTransaction());
        $this->db->method('is_transaction_started')->willReturn(false);

        $result = $this->manager->executeGraceful(static function (): never {
            throw new Error('fatal-ish');
        });

        $this->assertInstanceOf(Error::class, $result);
    }

    #[Test]
    public function implementsTransactionManagerInterface(): void
    {
        $this->assertInstanceOf(TransactionManagerInterface::class, $this->manager);
    }

    private function throwingRollbackTransaction(): moodle_transaction
    {
        return new class extends moodle_transaction {
            public function rollback($e): void
            {
                throw new RuntimeException('moodle forced rollback');
            }
        };
    }
}
