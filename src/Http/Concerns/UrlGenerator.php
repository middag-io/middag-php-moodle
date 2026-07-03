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

use Middag\Moodle\Kernel\Kernel as kernel;
use Middag\Moodle\Support\UrlSupport as url_support;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Trait url_generator.
 *
 * Provides STATIC helper methods to generate URLs via the Kernel.
 * Useful for Facades, Utils, or classes that are not instantiated via Container.
 */
trait UrlGenerator
{
    /**
     * Generate a URL based on the Symfony route name and parameters.
     *
     * @param string $route
     * @param array  $parameters
     * @param int    $referenceType
     *
     * @return string
     */
    public static function urlGenerator(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return kernel::routing()->generateUrl($route, $parameters, $referenceType);
    }

    /**
     * Generate a webhook URL.
     *
     * @param string $route
     * @param array  $parameters
     * @param int    $referenceType
     *
     * @return string
     *
     * @throws moodle_exception
     */
    public static function webhookUrlGenerator(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $url = self::urlGenerator($route, $parameters, $referenceType);

        return url_support::get(str_replace('index.php', 'webhook.php', $url));
    }
}
