<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Shared\Concerns;

use core\url as moodle_url;
use Middag\Moodle\Runtime\Kernel;

/**
 * Trait has_url.
 *
 * Provides instance methods for entities to generate their own URLs.
 * Delegates strictly to the central Facade.
 *
 * Architecture Note:
 * While strict DDD separates routing from entities, in the Moodle/ActiveRecord
 * context, self-generating URLs facilitates template rendering and ease of use.
 *
 * @internal
 */
trait HasUrl
{
    /**
     * Get the URL for a specific action on this entity.
     *
     * Convention: Route name is "middag.{type}.{action}"
     * Example: $item->get_url('edit') -> route('middag.generic.edit', ['id' => 123])
     *
     * @param string               $action the action suffix (view, edit, delete) or full route name
     * @param array<string, mixed> $params additional parameters
     *
     * @return moodle_url
     */
    public function getUrl(string $action = 'view', array $params = []): moodle_url
    {
        // 1. Determine Type
        // Safer than trusting property existence, prefers interface contract if exists
        $type = method_exists($this, 'get_type') ? $this->get_type() : 'item';

        // 2. Resolve Route Name
        // Normalize: if contains dots, assume full route name
        $route = str_contains($action, '.') ? $action : sprintf('middag.%s.%s', $type, $action);

        // 3. Inject ID automatically if available
        if (!isset($params['id']) && method_exists($this, 'get_id')) {
            $id = $this->get_id();
            if ($id !== null) {
                $params['id'] = $id;
            }
        }

        // 4. Delegate to Kernel via Facade
        return new moodle_url(Kernel::routing()->generateUrl($route, $params));
    }

    /**
     * Shortcut for the view URL.
     */
    public function getViewUrl(): moodle_url
    {
        return $this->getUrl();
    }

    /**
     * Shortcut for the edit URL.
     */
    public function getEditUrl(): moodle_url
    {
        return $this->getUrl('edit');
    }

    /**
     * Generate a webhook URL specific to this entity.
     *
     * @param string               $action
     * @param array<string, mixed> $params
     *
     * @return moodle_url
     */
    public function getWebhookUrl(string $action, array $params = []): moodle_url
    {
        if (!isset($params['id']) && method_exists($this, 'get_id')) {
            $id = $this->get_id();
            if ($id !== null) {
                $params['id'] = $id;
            }
        }

        $route = str_contains($action, '.') ? $action : 'webhook.' . $action;

        // Note: Uses middag::url_generator as webhook_url_generator may not be applicable here
        // If not, this should be mapped in the Facade. For now, using standard generator.
        return new moodle_url(Kernel::routing()->generateUrl($route, $params));
    }
}
