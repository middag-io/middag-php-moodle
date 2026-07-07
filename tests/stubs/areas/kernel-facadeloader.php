<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Fixture facade classes for FacadeLoaderCoverageTest.
 *
 * FacadeLoader discovers facades by globbing *.php filenames and rebuilding an
 * FQCN it then feeds to class_exists() + ReflectionClass — the file contents are
 * never included. The three resolution shapes are:
 *   - core:       {component}\base\facade\{Basename}
 *   - legacy ext: {component}\extensions\{slug}\facade\{Basename}
 *   - suffix:     {component}\ + the file's path under the host root ('/'→'\\')
 * where {component} is the bootstrap-configured ComponentContext ("local_example").
 *
 * These stand-in classes make that resolution honest without a Moodle plugin on
 * disk: concrete ones must be collected, abstract ones must be filtered out. The
 * test writes matching empty *.php files into a temp host root so the globbing +
 * recursive iteration have real filenames to walk. Guarded with class_exists so
 * the file is additive and order-independent, like the other area stubs.
 */

if (!class_exists('local_example\base\facade\CovAlphaFacade', false)) {
    eval('namespace local_example\base\facade; class CovAlphaFacade {}');
}
if (!class_exists('local_example\base\facade\CovAbstractFacade', false)) {
    eval('namespace local_example\base\facade; abstract class CovAbstractFacade {}');
}
if (!class_exists('local_example\extensions\covext\facade\CovExtFacade', false)) {
    eval('namespace local_example\extensions\covext\facade; class CovExtFacade {}');
}
if (!class_exists('local_example\extensions\covext\deep\cov_suffix_facade', false)) {
    eval('namespace local_example\extensions\covext\deep; class cov_suffix_facade {}');
}
if (!class_exists('local_example\extensions\covext\deep\cov_abstract_suffix_facade', false)) {
    eval('namespace local_example\extensions\covext\deep; abstract class cov_abstract_suffix_facade {}');
}
