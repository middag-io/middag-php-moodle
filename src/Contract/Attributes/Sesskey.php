<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Contract\Attributes;

use Attribute;

/**
 * Exige validação de sesskey (CSRF Moodle) em requisições não-idempotentes.
 *
 * Composição com `Middag\Framework\Http\Attribute\Auth`: o atributo `Auth`
 * declara login + capabilities (agnóstico); `Sesskey` adiciona o requisito
 * Moodle-flavor de CSRF. Aplicado em método ou classe, lido pelo
 * `MoodleHttpKernel::applyPlatformAuth()`.
 *
 * Exemplos:
 *
 *   #[Auth(capabilities: ['local/myplugin:manage'])]
 *   #[Sesskey]
 *   public function update(): JsonResponse { ... }
 *
 *   #[Sesskey]
 *   class my_mutation_controller extends api_controller { ... }
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Sesskey
{
    public function __construct(
        public bool $require = true,
    ) {}
}
