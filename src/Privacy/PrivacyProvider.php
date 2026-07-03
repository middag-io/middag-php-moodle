<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use Middag\Moodle\Kernel\Kernel;
use Middag\Moodle\Privacy\Contract\PrivacyProviderInterface;
use Middag\Moodle\Privacy\Contract\PrivacyRepositoryInterface;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;
use Throwable;

/**
 * Thin Privacy API adapter for the MIDDAG plugin.
 *
 * Implements Moodle's Privacy Subsystem and delegates data operations to
 * PrivacyRepositoryInterface. Extensions that store personal data implement
 * PrivacyProviderInterface; this class discovers and delegates to them.
 *
 * @api
 */
class PrivacyProvider implements provider, \core_privacy\local\request\plugin\provider
{
    /**
     * @var array<string, array{fields: array<string, string>, summary: string}>
     *                                                                           Product-registered privacy table descriptors
     */
    private static array $dataTables = [];

    /**
     * Register a database table's privacy metadata, supplied by the product
     * composition root.
     *
     * The adapter ships no product schema; the consumer plugin declares which
     * tables hold personal data and their field → lang-string mappings.
     *
     * @param string                $table   table name (e.g. its items table)
     * @param array<string, string> $fields  field => lang-string key map
     * @param string                $summary lang-string key summarising the table
     */
    public static function registerDataTable(string $table, array $fields, string $summary): void
    {
        self::$dataTables[$table] = ['fields' => $fields, 'summary' => $summary];
    }

    public static function get_metadata(collection $collection): collection
    {
        foreach (self::$dataTables as $table => $meta) {
            $collection->add_database_table($table, $meta['fields'], $meta['summary']);
        }

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist
    {
        $contextlist = new contextlist();

        try {
            /** @var PrivacyRepositoryInterface $repo */
            $repo = Kernel::get(PrivacyRepositoryInterface::class);
            $repo->addContextsForUserid($userid, $contextlist);

            foreach (static::getExtensionProviders() as $provider) {
                $provider->addContextsForUserid($userid, $contextlist);
            }
        } catch (Throwable) {
            // Non-fatal — return partial contextlist rather than breaking privacy export.
        }

        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void
    {
        try {
            /** @var PrivacyRepositoryInterface $repo */
            $repo = Kernel::get(PrivacyRepositoryInterface::class);
            $repo->exportUserData($contextlist);

            foreach (static::getExtensionProviders() as $provider) {
                $provider->exportUserData($contextlist);
            }
        } catch (Throwable) {
            // Non-fatal — partial export preferred over total failure.
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void
    {
        try {
            /** @var PrivacyRepositoryInterface $repo */
            $repo = Kernel::get(PrivacyRepositoryInterface::class);
            // Moodle's privacy contract types $context as the deprecated global
            // \context, which is the core\context class at runtime that the MIDDAG
            // repository expects; phpstan's stubs treat the alias as a distinct type.
            // @phpstan-ignore argument.type
            $repo->deleteDataForAllUsersInContext($context);

            foreach (static::getExtensionProviders() as $provider) {
                $provider->deleteDataForAllUsersInContext($context);
            }
        } catch (Throwable) {
            // Non-fatal.
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void
    {
        try {
            /** @var PrivacyRepositoryInterface $repo */
            $repo = Kernel::get(PrivacyRepositoryInterface::class);
            $repo->deleteDataForUser($contextlist);

            foreach (static::getExtensionProviders() as $provider) {
                $provider->deleteDataForUser($contextlist);
            }
        } catch (Throwable) {
            // Non-fatal.
        }
    }

    /**
     * Resolve extension-provided privacy implementations.
     *
     * Extensions that store personal data register implementations of
     * PrivacyProviderInterface in the container tagged 'middag.privacy_provider'.
     *
     * @return PrivacyProviderInterface[]
     */
    protected static function getExtensionProviders(): array
    {
        try {
            $container = Kernel::container();

            if (!$container instanceof TaggedContainerInterface) {
                return [];
            }

            $providers = [];

            foreach (array_keys($container->findTaggedServiceIds('middag.privacy_provider')) as $id) {
                $provider = $container->get($id);

                if ($provider instanceof PrivacyProviderInterface) {
                    $providers[] = $provider;
                }
            }

            return $providers;
        } catch (Throwable) {
            return [];
        }
    }
}
