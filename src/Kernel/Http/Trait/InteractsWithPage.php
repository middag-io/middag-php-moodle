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

use core\context;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\url as moodle_url;
use Exception;
use Middag\Moodle\Support\ContextSupport as context_support;
use Middag\Moodle\Support\PageSupport as page_support;
use Middag\Moodle\Support\UrlSupport as url_support;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Trait handling Moodle $PAGE, Context, Output and Layouts.
 *
 * @property mixed $course
 * @property mixed $cm
 *
 * @internal
 */
trait InteractsWithPage
{
    /** @var null|context Stores the Moodle context */
    protected ?context $context = null;

    protected string $pageLayout = 'standard';

    protected string $pageTitle = 'Default title';

    protected string $pageHeading = 'Default heading';

    protected moodle_url|string|null $pageUrl = null;

    protected array $pageNavbar = [];

    protected string $adminSection = '';

    /**
     * Set the context for the controller.
     */
    public function setContext(?context $context = null): void
    {
        $this->context = $context ?? context_support::system();
    }

    /**
     * Get the resolved context (defaults to system).
     */
    public function getContext(): context
    {
        return $this->context ?? context_support::system();
    }

    /**
     * Set the page URL.
     */
    public function setPageUrl(moodle_url|string $url): void
    {
        $this->pageUrl = $url;
    }

    /**
     * Set Moodle page layout.
     */
    public function setPageLayout(string $layout): void
    {
        $this->pageLayout = $layout;
    }

    /**
     * Set page title.
     */
    public function setPageTitle(string $title): void
    {
        $this->pageTitle = $title;
    }

    /**
     * Set page heading.
     */
    public function setPageHeading(string $heading): void
    {
        $this->pageHeading = $heading;
    }

    /**
     * Add an item to the page navbar trail.
     */
    public function addPageNavbar(array|string $item): void
    {
        $this->pageNavbar[] = $item;
    }

    /**
     * Helper to set URL from route name.
     */
    public function setUrlFromRoute(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): void
    {
        if (method_exists($this, 'url_generator')) {
            try {
                $url = $this->url_generator($route, $parameters, $referenceType);
                $this->set_page_url($url);
            } catch (Exception) {
                $this->page_url = null;
            }
        }
    }

    /**
     * Resolves the context if null, based on course/cm properties.
     */
    protected function resolveContext(): void
    {
        if (is_null($this->context)) {
            if (!empty($this->cm)) {
                $this->setContext(context_support::module((int) $this->cm->id));
            } elseif (!empty($this->course)) {
                $this->setContext(context_support::course($this->course->get_id()));
            } else {
                $this->setContext(context_support::system());
            }
        }
    }

    /**
     * Apply all settings to the global $PAGE object.
     *
     * @throws coding_exception|moodle_exception
     */
    protected function setupMoodlePage(): void
    {
        $this->resolveContext();

        if ($this->adminSection !== '' && $this->adminSection !== '0') {
            page_support::adminExternalpageSetup($this->adminSection);
        }

        page_support::setContext($this->getContext());
        page_support::setPagelayout($this->pageLayout);
        page_support::setTitle($this->pageTitle);
        page_support::setHeading($this->pageHeading);
        page_support::setUrl($this->getPageUrl());

        foreach ($this->pageNavbar as $item) {
            if (is_array($item)) {
                page_support::navbarAdd($item[0] ?? '', $item[1] ?? null);
            } else {
                page_support::navbarAdd($item);
            }
        }

        if ($this->adminSection !== '' && $this->adminSection !== '0') {
            page_support::adminLoadNavigation($this->adminSection);
        }
    }

    /**
     * Get the Moodle page URL, resolving defaults when needed.
     *
     * @throws moodle_exception
     */
    protected function getPageUrl(): moodle_url
    {
        if ($this->pageUrl === null && method_exists($this, 'set_url_from_route')) {
            try {
                $this->set_url_from_route('index');
            } catch (Exception) {
                // Intentionally suppressed: 'index' route may not exist; falls through to other URL resolution.
            }
        }

        if (is_string($this->page_url) && ($this->page_url !== '' && $this->page_url !== '0')) {
            return url_support::get($this->page_url);
        }

        if ($this->page_url instanceof moodle_url) {
            return $this->page_url;
        }

        return url_support::home();
    }
}
