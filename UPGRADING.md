# Upgrading

Breaking changes land as MINOR releases while the library is on `1.x`
(pre-v2 rule, ADR-032 / middag-io/middag-php-core#251): the `2.0.0` cut only
changes the label. Each entry here matches a `Quebra pre-v2:` line in the commit
that introduced it.

---

## 1.16.0 — CSRF (sesskey) is verified by default

**What changed.** Non-idempotent requests (`POST`, `PUT`, `PATCH`, `DELETE`)
authenticated by a Moodle session must now carry a valid sesskey. Before, the
check was opt-in via `#[Sesskey]` and, in practice, never ran. See
`docs/decisions/MDL-022-csrf-default-on.md`.

**Who is affected.** Any consumer whose client sends session-authenticated
mutating requests without a token: Inertia visits, `fetch`/axios calls to
`ajax.php`, custom admin SPAs. Web-service (`wstoken`) callers and public routes
are not affected.

**Symptom.** `403` with the message of `get_string('invalidsesskey', 'error')`.

### How to migrate

Pick whichever transport fits the client — the gate accepts all four:

| Transport | Use when |
|---|---|
| `X-CSRF-Token` header | SPA / `fetch` / axios interceptor. Read the token from the `csrf_token` Inertia prop or from `M.cfg.sesskey`. |
| `X-XSRF-TOKEN` header | Automatic with axios: the adapter sets a JS-readable `XSRF-TOKEN` cookie and axios echoes it. **Inertia visits need no change.** |
| `sesskey` field (body or query) | Moodle's own transport. Every `moodleform` already posts it. |
| `_token` field (body) | The framework's host-agnostic field name. |

Example interceptor:

```ts
axios.interceptors.request.use((config) => {
  config.headers['X-CSRF-Token'] = window.M?.cfg?.sesskey;
  return config;
});
```

### How to opt a route out

```php
use Middag\Moodle\Security\Attribute\Sesskey;

#[Sesskey(require: false)]
#[Route(path: '/callback', methods: ['POST'])]
public function callback(): JsonResponse { /* ... */ }
```

Applies to a method or a whole class. Prefer it to disabling the gate globally.

### Routes that need no change

- Public routes — those overriding `requiresAuthentication(): false`. Note that
  `#[Auth(login: false)]` alone does **not** make a route public here: the kernel
  only honours it for controllers implementing `PublicRouteAwareInterface`.
- Requests authenticated by `wstoken`. The exemption now keys off the
  authentication actually performed, not off the presence of an `Authorization`
  header — a session request carrying a stray Bearer header is **no longer**
  exempt.

### Other changes in the same release

- `AuthenticationInterface::confirmSesskey(string $token): bool` is new.
  Implementers of this interface (including test doubles) must add it.
- `AbstractController::requireLogin()` and `AbstractApiController::requireLogin()`
  no longer verify the sesskey; `preHandle()` does.
- `InertiaSharedProps` now shares a `csrf_token` prop.
- Every response carries a `Set-Cookie: XSRF-TOKEN=<sesskey>; SameSite=Lax`.
