<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Exception;

use Middag\Framework\Exception\MiddagInfrastructureException;

/**
 * Raised when Moodle's mailer refuses an e-mail.
 *
 * `email_to_user()` reports a refusal (a suspended or deleted recipient, an
 * address Moodle will not mail, a transport failure) by returning false, and
 * nothing else. A caller that only sees a return of nothing would report the
 * message as sent, so the refusal is raised instead.
 *
 * @api
 */
final class EmailNotSentException extends MiddagInfrastructureException {}
