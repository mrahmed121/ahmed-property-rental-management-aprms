# APRMS Security Model

## Authentication
- JWT via `tymon/jwt-auth`. Tokens expire per `JWT_TTL`.
- Login validates credentials with bcrypt. Invalid credentials return 401 (no user enumeration via timing differences is not specifically mitigated — noted).
- Logout invalidates the token. Refresh issues a new token.

## Authorization
- 9 roles, 100+ granular permissions. Every API route (except login/health/logout/refresh/me) requires a permission.
- Permission checks are enforced in middleware AND in domain services (defense in depth).

## Isolation
- **Agency:** `BelongsToAgency` global scope on all domain models. Cross-agency access returns 404 (not 403) to avoid leaking existence.
- **Owner:** sees only properties where `owner_id` matches, and only their own statements.
- **Tenant:** sees only their own lease, unit meters, and ledger.
- **Auditor:** read-only across all domains.

## Financial Integrity
- Posted financial records are never hard-deleted. Corrections use reversal/adjustment workflows with audit entries.
- All money math uses deterministic rounding (2 decimals for money, 4 for rates).
- No second ledgers: utilities and statements consume the P4 ledger.

## Input & Files
- All inputs validated via FormRequests / `$request->validate()`.
- File uploads: 10MB max, allowlist of types (PDF/JPG/PNG/WEBP/DOC/DOCX/TXT), stored in private agency-scoped paths, served via authenticated streaming (no direct public URLs).
- Mass assignment protected via `$fillable` on all models.

## Secrets
- No secrets in the repository. `.env` is gitignored; `.env.example` contains placeholders only.
- Demo credentials (`password123`) exist only in seeders, tests, and docs — clearly labeled for local/demo use.

## Audit
- Append-only `audit_logs` table records actor, action, subject, and context for all mutations.

## Known Limitations (honest)
- True parallel concurrency verified for logic on SQLite; production row-lock behavior on MySQL/PostgreSQL not live-tested.
- Rate limiting is not configured by default — add per your infrastructure.
