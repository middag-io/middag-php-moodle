<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Privacy;

use ArrayObject;
use core\context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use Middag\Moodle\Kernel\Kernel;
use Middag\Moodle\Privacy\Contract\PrivacyProviderInterface;
use Middag\Moodle\Privacy\Contract\PrivacyRepositoryInterface;
use Middag\Moodle\Privacy\PrivacyProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;

/**
 * Test PrivacyProvider.
 *
 * The adapter is a static Moodle Privacy Subsystem entry point. It resolves a
 * PrivacyRepositoryInterface and tagged PrivacyProviderInterface extensions
 * through the Kernel service locator; all delegation is wrapped in non-fatal
 * try/catch. Tests inject a booted Kernel (fake container + runtime instances)
 * by reflection, and reset the static registry between cases.
 *
 * @internal
 */
#[CoversClass(PrivacyProvider::class)]
final class PrivacyProviderCoverageTest extends TestCase
{
    private const TAG = 'middag.privacy_provider';

    private ArrayObject $log;

    protected function setUp(): void
    {
        $this->log = new ArrayObject();
        (new ReflectionProperty(PrivacyProvider::class, 'dataTables'))->setValue(null, []);
    }

    protected function tearDown(): void
    {
        Kernel::shutdown();
        (new ReflectionProperty(PrivacyProvider::class, 'dataTables'))->setValue(null, []);
    }

    // --- get_metadata ---------------------------------------------------

    #[Test]
    public function getMetadataAddsRegisteredTablesToTheCollection(): void
    {
        PrivacyProvider::registerDataTable('mdl_items', ['name' => 'privacy:name'], 'privacy:items');
        $collection = new collection('local_example');

        $result = PrivacyProvider::get_metadata($collection);

        $this->assertSame($collection, $result);
        $this->assertArrayHasKey('mdl_items', $collection->tables);
        $this->assertSame(['name' => 'privacy:name'], $collection->tables['mdl_items']['fields']);
    }

    #[Test]
    public function getMetadataWithNoRegisteredTablesLeavesCollectionUntouched(): void
    {
        $collection = new collection('local_example');

        $this->assertSame([], PrivacyProvider::get_metadata($collection)->tables);
    }

    // --- get_contexts_for_userid ---------------------------------------

    #[Test]
    public function getContextsDelegatesToRepositoryAndTaggedProviders(): void
    {
        $this->injectKernel(
            $this->taggedContainer(['ext.a' => $this->spyProvider(), 'ext.b' => new stdClass()]),
            [PrivacyRepositoryInterface::class => $this->spyRepository()]
        );

        $result = PrivacyProvider::get_contexts_for_userid(77);

        $this->assertInstanceOf(contextlist::class, $result);
        $this->assertSame(77, $this->log['repo.add']);
        // Only the real provider ran; the stdClass tagged service was filtered.
        $this->assertSame(1, $this->log['ext.add']);
    }

    #[Test]
    public function getContextsSwallowsResolutionFailure(): void
    {
        $this->injectKernel(null); // booted but container null → Kernel::get throws

        $result = PrivacyProvider::get_contexts_for_userid(1);

        $this->assertInstanceOf(contextlist::class, $result);
        $this->assertArrayNotHasKey('repo.add', $this->log);
    }

    // --- export_user_data ----------------------------------------------

    #[Test]
    public function exportDelegatesToRepositoryAndProviders(): void
    {
        $this->injectKernel(
            $this->taggedContainer(['ext.a' => $this->spyProvider()]),
            [PrivacyRepositoryInterface::class => $this->spyRepository()]
        );

        PrivacyProvider::export_user_data(new approved_contextlist());

        $this->assertTrue($this->log['repo.export']);
        $this->assertTrue($this->log['ext.export']);
    }

    #[Test]
    public function exportSwallowsResolutionFailure(): void
    {
        $this->injectKernel(null);

        PrivacyProvider::export_user_data(new approved_contextlist());

        $this->assertArrayNotHasKey('repo.export', $this->log);
    }

    // --- delete_data_for_all_users_in_context --------------------------

    #[Test]
    public function deleteForAllUsersDelegatesToRepositoryAndProviders(): void
    {
        $this->injectKernel(
            $this->taggedContainer(['ext.a' => $this->spyProvider()]),
            [PrivacyRepositoryInterface::class => $this->spyRepository()]
        );

        PrivacyProvider::delete_data_for_all_users_in_context(new context(5));

        $this->assertTrue($this->log['repo.delAll']);
        $this->assertTrue($this->log['ext.delAll']);
    }

    #[Test]
    public function deleteForAllUsersSwallowsResolutionFailure(): void
    {
        $this->injectKernel(null);

        PrivacyProvider::delete_data_for_all_users_in_context(new context(5));

        $this->assertArrayNotHasKey('repo.delAll', $this->log);
    }

    // --- delete_data_for_user ------------------------------------------

    #[Test]
    public function deleteForUserDelegatesToRepositoryAndProviders(): void
    {
        $this->injectKernel(
            $this->taggedContainer(['ext.a' => $this->spyProvider()]),
            [PrivacyRepositoryInterface::class => $this->spyRepository()]
        );

        PrivacyProvider::delete_data_for_user(new approved_contextlist());

        $this->assertTrue($this->log['repo.delUser']);
        $this->assertTrue($this->log['ext.delUser']);
    }

    #[Test]
    public function deleteForUserSwallowsResolutionFailure(): void
    {
        $this->injectKernel(null);

        PrivacyProvider::delete_data_for_user(new approved_contextlist());

        $this->assertArrayNotHasKey('repo.delUser', $this->log);
    }

    // --- getExtensionProviders branches --------------------------------

    #[Test]
    public function nonTaggedContainerYieldsNoExtensionProviders(): void
    {
        // Repo resolves from runtime, but the container is not tagged-aware.
        $this->injectKernel(
            $this->createStub(ContainerInterface::class),
            [PrivacyRepositoryInterface::class => $this->spyRepository()]
        );

        PrivacyProvider::get_contexts_for_userid(9);

        $this->assertSame(9, $this->log['repo.add']);
        $this->assertArrayNotHasKey('ext.add', $this->log);
    }

    #[Test]
    public function extensionProviderLookupFailureIsSwallowed(): void
    {
        // Repo resolves from runtime; container() then throws (null container),
        // so getExtensionProviders() returns [] without breaking the flow.
        $this->injectKernel(null, [PrivacyRepositoryInterface::class => $this->spyRepository()]);

        PrivacyProvider::get_contexts_for_userid(3);

        $this->assertSame(3, $this->log['repo.add']);
        $this->assertArrayNotHasKey('ext.add', $this->log);
    }

    // --- helpers --------------------------------------------------------

    private function injectKernel(?ContainerInterface $container, array $runtime = []): void
    {
        $ref = new ReflectionClass(Kernel::class);
        $kernel = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('booted')->setValue($kernel, true);
        $ref->getProperty('container')->setValue($kernel, $container);
        $ref->getProperty('runtimeInstances')->setValue($kernel, $runtime);
        $ref->getProperty('instance')->setValue(null, $kernel);
    }

    private function spyRepository(): PrivacyRepositoryInterface
    {
        return new class($this->log) implements PrivacyRepositoryInterface {
            public function __construct(private ArrayObject $log) {}

            public function addContextsForUserid(int $userid, contextlist $contextlist): void
            {
                $this->log['repo.add'] = $userid;
            }

            public function exportUserData(approved_contextlist $contextlist): void
            {
                $this->log['repo.export'] = true;
            }

            public function deleteDataForAllUsersInContext(context $context): void
            {
                $this->log['repo.delAll'] = true;
            }

            public function deleteDataForUser(approved_contextlist $contextlist): void
            {
                $this->log['repo.delUser'] = true;
            }
        };
    }

    private function spyProvider(): PrivacyProviderInterface
    {
        return new class($this->log) implements PrivacyProviderInterface {
            public function __construct(private ArrayObject $log) {}

            public function addContextsForUserid(int $userid, contextlist $contextlist): void
            {
                $this->log['ext.add'] = ($this->log['ext.add'] ?? 0) + 1;
            }

            public function exportUserData(approved_contextlist $contextlist): void
            {
                $this->log['ext.export'] = true;
            }

            public function deleteDataForAllUsersInContext(context $context): void
            {
                $this->log['ext.delAll'] = true;
            }

            public function deleteDataForUser(approved_contextlist $contextlist): void
            {
                $this->log['ext.delUser'] = true;
            }
        };
    }

    /**
     * A container that resolves the repository (runtime) plus a tagged provider
     * set. Non-provider tagged services are filtered by the adapter.
     *
     * @param array<string, object> $tagged id => service (some may be non-providers)
     */
    private function taggedContainer(array $tagged): TaggedContainerInterface
    {
        $container = $this->createMock(TaggedContainerInterface::class);
        $container->method('findTaggedServiceIds')->with(self::TAG)
            ->willReturn(array_map(static fn (): array => [[]], $tagged));
        $container->method('get')->willReturnCallback(
            static fn (string $id): object => $tagged[$id]
        );

        return $container;
    }
}
