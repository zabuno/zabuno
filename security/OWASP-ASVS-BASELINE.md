# OWASP ASVS Baseline Checklist — S1-WP01A Foundation

**Status: foundational baseline only, not an audit result.** This checklist is
pinned to **OWASP Application Security Verification Standard (ASVS) 5.0.0**
(`v5.0.0_release`, released 2025-05-30 — the initial 5.x release; canonical
standard reference: `docs/15-SECURITY-PERFORMANCE-SHARED-HOST-MOBILE.md` §6,
`docs/28-SOURCE-REGISTER.md`). It records which ASVS Level 1 control
*categories* have a foundation-stage answer today and which are explicitly
open — it is **not** a certification, a completed audit, or a claim of full
ASVS compliance. No control below is marked "met" without a concrete,
inspectable artifact in this repository.

This document belongs to S1-WP01A (`docs/26` S1-WP01 "OWASP ASVS temel
checklist bağlanır"). Later work packages (S1-WP07 security/exit evidence,
`docs/26`) extend this into an actual verification pass with test evidence —
that pass has not happened yet.

## How to read this table

- **Foundation status**: what exists in this repository *right now* that is
  relevant to the control area.
- **Evidence**: the concrete artifact (file/config) backing the status, or
  "none yet" if the control area has no implementation yet.
- **Open**: what is explicitly deferred, and to which work package.

| ASVS 5.0.0 chapter (control area) | Foundation status | Evidence | Open / deferred to |
|---|---|---|---|
| V1 Encoding & Sanitization | Not yet applicable — no user input surface exists in this WP (status screen only) | none yet | S1-WP02+ (first form/input surface) |
| V2 Validation & Business Logic | Not yet applicable — no business logic exists in this WP | none yet | S1-WP02+ |
| V3 Web Frontend Security | Baseline CSP/security-header posture not configured yet | none yet | S1-WP07 |
| V4 API & Web Service | Not yet applicable — no API surface exists in this WP | none yet | S1-WP02+ |
| V5 File Handling | Not yet applicable — no upload surface exists in this WP | none yet | S1-WP03 (CORE-13 File/Media) |
| V6 Authentication | Not yet applicable — CORE-01 Identity/Sessions is out of scope for this WP | none yet | S1-WP02 |
| V7 Session Management | Framework default (Laravel database session driver) present, not yet hardened/reviewed | `config/session.php`, `.env.example` `SESSION_DRIVER=database` | S1-WP02 |
| V8 Authorization | Not yet applicable — CORE-03 Authorization is out of scope for this WP | none yet | S1-WP02 |
| V9 Self-contained Tokens | Not yet applicable | none yet | S1-WP02+ |
| V10 OAuth & OIDC | Not yet applicable — no external identity provider in Stage 1 scope | none yet | Stage 6 (`docs/23` S6-WP01 SSO/SCIM) |
| V11 Cryptography | Framework defaults present (bcrypt rounds, app key), not yet reviewed | `.env.example` `BCRYPT_ROUNDS=12`, Laravel `APP_KEY` generation | S1-WP07 |
| V12 Secure Communication | Not configured at this layer — TLS termination is a deployment/host concern | none yet | S1-WP07, deployment runbook |
| V13 Configuration | Environment layering (dev/staging/prod) foundation exists; secrets kept blank in examples | `config/environments.php`, `.env.example`, `.env.staging.example`, `.env.production.example` | S1-WP02 (CORE-06 Settings/Secrets) |
| V14 Data Protection | Not yet applicable — no persisted tenant/user data model in this WP beyond the framework default `users` table | `database/migrations/0001_01_01_000000_create_users_table.php` (framework default, unused by this WP's screen) | S1-WP02+ |

## Explicit non-claims

- This checklist does **not** assert ASVS Level 1, 2, or 3 compliance for any
  chapter.
- No penetration test or general/ASVS security audit has been run against
  this codebase as part of producing this file. Local dependency
  vulnerability audits have been run — `composer audit --locked` (no
  advisories) and `npm audit --audit-level=high` (0 high+ vulnerabilities) —
  but that is dependency vulnerability scanning only, not an ASVS assessment
  or a general security audit.
- "Not yet applicable" rows are honest about scope, not a security judgment —
  they will be revisited once the corresponding module (CORE-01/03/06/13 etc.)
  is implemented in its own work package (`docs/26`).

## Canonical ownership

The single source of truth for the security/performance/shared-host
standards this checklist is pinned to is `docs/15-SECURITY-PERFORMANCE-SHARED-HOST-MOBILE.md`
§6. This file only tracks the *baseline checklist artifact* required by
`docs/26` S1-WP01; it does not redefine the standards themselves.
