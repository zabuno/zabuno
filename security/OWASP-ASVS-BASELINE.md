# OWASP ASVS Level 1 — verification pass, 2026-08-26

**Status: an actual verification pass against the code, not a certification.**
Pinned to **OWASP ASVS 5.0.0** (`v5.0.0_release`, published 2025-05-30;
canonical standard reference: `docs/15-SECURITY-PERFORMANCE-SHARED-HOST-MOBILE.md`
§6, `docs/28-SOURCE-REGISTER.md`).

This file replaces the foundation-stage checklist that preceded it. That
checklist was written when the repository had a status screen and nothing
else; every row said "not yet applicable". The application now has identity,
tenancy, authorization, a public unauthenticated surface, file upload and a
payment path, so each chapter has been examined against the code as it is.

## What this pass is, and what it is not

- It **is** a chapter-by-chapter read of this codebase against ASVS 5.0.0
  Level 1, with a named, inspectable artifact behind every claim and an
  automated test behind every claim marked *verified*.
- It is **not** a penetration test, a third-party audit, or a certification.
  No external tester has attacked this system.
- It is **not** a production proof. Several controls (TLS termination,
  backup encryption at rest, host hardening) live in the deployment
  environment, and this repository can only show the configuration it ships.

## Findings this pass produced

Three real gaps were found and closed in the same package:

1. **No security headers at all.** The application sent no CSP, no
   `X-Content-Type-Options`, no `Referrer-Policy`, no framing protection. The
   most exposed surface is the published menu — an unauthenticated page that
   renders text the restaurant typed. Closed by
   `App\Http\Middleware\SecurityHeaders` with a nonce-based CSP; the policy
   contains neither `'unsafe-inline'` nor `'unsafe-eval'`.
2. **Session cookie not bound to HTTPS in deployed environments.**
   `SESSION_SECURE_COOKIE` was unset in both deployed examples, so the
   session cookie could travel over a plain HTTP request. Closed by setting
   it, and `SESSION_ENCRYPT`, to `true` in the staging and production
   examples.
3. **Session payloads written to the database in clear text.** Same fix as
   above — `SESSION_ENCRYPT=true`.

## Chapter results

| ASVS 5.0.0 chapter | Result | Evidence | Open |
|---|---|---|---|
| V1 Encoding & Sanitization | **Verified** — output is escaped by construction: Blade `{{ }}` everywhere, React escapes by default, and no `dangerouslySetInnerHTML`/`{!! !!}` exists anywhere in the codebase | `resources/views/public-menu.blade.php`, `tests/Feature/Security/SecurityHeadersTest.php` | Rich-text editing would reopen this; none exists in Stage 1 |
| V2 Validation & Business Logic | **Verified for the critical path** — domain invariants are enforced in the domain layer, not the controller, and proven end to end over real HTTP | `tests/Feature/CriticalJourney/RestaurantCriticalJourneyTest.php`, `tests/Unit/MenuCatalog/MenuItemAggregateInvariantsTest.php` | Business-logic abuse cases beyond the critical path (Stage 2) |
| V3 Web Frontend Security | **Verified after remediation** — nonce-based CSP, `nosniff`, `Referrer-Policy`, `frame-ancestors 'none'` + `X-Frame-Options: DENY`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, HSTS over HTTPS | `app/Http/Middleware/SecurityHeaders.php`, `tests/Feature/Security/SecurityHeadersTest.php` (ASVS-V3-CSP-01…06) | CSP violation reporting endpoint (Stage 2) |
| V4 API & Web Service | **Verified** — every workspace API route is authenticated, verified and tenant-scoped; the route surface itself is frozen so a new route cannot appear unnoticed | `tests/Feature/Api/ModularApiRouteRegistrationTest.php`, `routes/api/*.php` | Per-route rate limiting beyond auth/invitation endpoints (Stage 2) |
| V5 File Handling | **Verified** — uploads land on a tenant-private quarantine disk, require alt text and slot, and are never public before a scan verdict | `tests/Feature/Media/MediaIntakeTest.php`, `tests/Feature/Media/ClamavMalwareScannerTest.php` | ClamAV availability is a host concern; the unavailable-scanner adapter fails closed |
| V6 Authentication | **Verified** — Fortify with email verification; login, registration, password reset and verification are each rate limited by identity + IP | `app/Providers/FortifyServiceProvider.php`, `tests/Feature/Auth/IdentitySessionJourneyTest.php`, `tests/Feature/Auth/PasswordResetJourneyTest.php` | MFA is Stage 2 (`docs/19`) |
| V7 Session Management | **Verified after remediation** — database driver, `http_only`, `same_site=lax`, and in deployed environments `secure` + encrypted payloads | `config/session.php`, `.env.production.example`, `tests/Feature/Security/SecureConfigurationTest.php` (ASVS-V7-COOKIE-07) | — |
| V8 Authorization | **Verified** — permissions resolve through `AuthorizationPort`; a non-member gets 404, not 403, so the existence of another tenant's resource never leaks | `tests/Feature/MenuCatalog/MenuApiTenantEscapeTest.php` (ESCAPE-LOCATION-01, ESCAPE-MENU-01 — "404, 403 değil"), `tests/Feature/Authorization/WorkspaceAuthorizationJourneyTest.php` (the decision point itself) | — |
| V9 Self-contained Tokens | **Not applicable** — Sanctum stateful cookies; no JWT or self-contained token is issued | `bootstrap/app.php` (`statefulApi`) | Revisit if a public API with bearer tokens ships |
| V10 OAuth & OIDC | **Not applicable** — no external identity provider in Stage 1 | — | Stage 6 SSO/SCIM (`docs/23`) |
| V11 Cryptography | **Verified** — bcrypt at cost 12 in every deployed example; `APP_KEY` generated per environment and blank in every example file | `tests/Feature/Security/SecureConfigurationTest.php` (ASVS-V11-HASH-09), `.env.production.example` `BCRYPT_ROUNDS=12` | Backup encryption at rest is a host concern (`docs/16` DR-01) |
| V12 Secure Communication | **Partially verified** — every deployed example declares an `https://` `APP_URL` and HSTS is sent over HTTPS, but TLS termination itself belongs to the host | `tests/Feature/Security/SecureConfigurationTest.php` (ASVS-V12-TLS-10) | Host TLS configuration — deployment runbook, not this repository |
| V13 Configuration | **Verified** — three-way environment layering, `APP_DEBUG=false` in both deployed examples, no example file carries a real secret | `config/environments.php`, `tests/Feature/EnvironmentLayeringTest.php`, `tests/Feature/Security/SecureConfigurationTest.php` (ASVS-V13-DEBUG-08) | — |
| V14 Data Protection | **Partially verified** — tenant data is workspace-scoped, and isolation is proven both by an evidence command and by tenant-escape tests over real HTTP | `tests/Feature/Security/TenantIsolationEvidenceCommandTest.php`, `tests/Feature/MenuCatalog/MenuApiTenantEscapeTest.php` | Retention/erasure workflows (KVKK/GDPR subject requests) are Stage 2 (`docs/19`) |

## Explicit non-claims

- No chapter above is claimed at ASVS Level 2 or 3.
- "Verified" means *this repository's automated tests hold the control in
  place*. It does not mean an attacker has tried and failed.
- Dependency scanning (`composer audit --locked`, `npm audit --audit-level=high`)
  is vulnerability scanning, not an ASVS assessment; it is reported separately.
- Controls marked *host concern* cannot be verified from a repository. Saying
  so is the honest answer; claiming them would be worse than leaving them open.

## Canonical ownership

The security/performance/shared-host standards this pass is pinned to are
owned by `docs/15-SECURITY-PERFORMANCE-SHARED-HOST-MOBILE.md` §6. This file
records the verification pass required by `docs/26` S1-WP07; it does not
redefine the standards.
