# APRMS Architecture

> Ahmed Property & Rental Management System — P1 Foundation.
> This document describes what is ACTUALLY implemented. Future phases extend it.

## Stack (deliberate choice)
- **Backend:** Laravel 11 (pinned `^11.57`, latest 11.x) + PHP 8.3, `tymon/jwt-auth ^2.3` for JWT.
- **Frontend:** React 18 + Vite 6 + Tailwind CSS 3 + React Router 6.
- **Database:** SQLite zero-config default; MySQL/PostgreSQL compatible (Eloquent, no raw SQL).

Laravel was chosen because rental accounting (ledgers, deposits, statements) needs
relational ACID guarantees. Mongo was explicitly rejected for the money path.

## Domain layout
```
app/Domains/
├── Shared/
│   ├── Models/        Agency, User, Role, Permission, AuditLog, Setting
│   ├── Traits/        BelongsToAgency
│   ├── Scopes/        AgencyScope (global query scope)
│   ├── Services/      DomainService (base), AuditService, SettingService
│   └── Repositories/  BaseRepository (query-boundary pattern)
├── Leasing/Services/  LeaseService      (P3 contract)
├── Billing/Services/  RentCycleService, AllocationService,
│                       DunningService, DepositService   (P4/P5 contracts)
├── Maintenance/Services/ MaintenanceService            (P5 contract)
└── Reporting/Services/   StatementService               (P7 contract)
```
Rules: controllers are thin; business rules live in domain services; future
services exist as documented contracts on the real `DomainService` base
(agency scoping + audit helpers) — no fake logic.

## Request lifecycle (API)
`routes/api.php` → versioned `/api/v1` → `auth:api` (JWT) → `permission:<slug>`
middleware → FormRequest validation → thin controller → domain service →
audited write → JSON response.

API errors are JSON: 401 unauthenticated, 403 missing permission / cross-agency,
422 validation (Laravel default `errors` shape), 404 for cross-agency lookups
(existence is never leaked).

## Multi-tenancy
- Every agency-owned model uses `BelongsToAgency` → `AgencyScope` global scope.
- `agency_id = NULL` means platform-level (Super Admin sees all; has no agency settings).
- Service-layer guard `DomainService::ensureAgencyAccess()` aborts 403 on violations.
- Covered by `AgencyIsolationTest` (list scoping, 404 on cross-agency read/update,
  settings isolation, audit-log isolation).

## RBAC
9 roles, seeded per agency (+ system `super-admin`): agency-admin, property-manager,
accountant, maintenance-supervisor, technician, owner, tenant, auditor.
Permissions are flat slugs (`users.manage`); the `permission` route middleware
checks them; the frontend hides nav items via `PermissionGuard` / `hasPermission()`.

## Audit trail
Append-only `audit_logs` (no `updated_at`). Single write path: `AuditService::log()`.
P1 records: `auth.login`, `auth.logout`, `auth.failed_login`, `users.create/update/delete`,
`roles.create/update`, `settings.update` — with actor, agency, entity, old/new values,
IP + user agent. Read-only API (`GET /api/v1/audit-logs`, permission `audit.view`).

## Settings
Agency-scoped key/value store with typed values (`string|integer|boolean|json`),
bulk upsert with type validation, grouped (`general`, `billing`, `notifications`).
P4+ services will read late-fee rules etc. from here instead of hardcoding.

## Security advisories (documented decision)
Composer 2.10 blocks packages with known advisories. All laravel/framework 11.x
carries 4 advisories with no patched 11.x available, so `composer.json` sets
`"config": {"policy": false}`. Each advisory was reviewed:
- `PKSA-d5tc-s1qs-h781` (XSS in debug page) — only when `APP_DEBUG=true`; production `.env` ships `false`.
- `PKSA-m5cs-t1y6-qpcs` (signed-URL path confusion) — P1 uses no signed URLs.
- `PKSA-3r5d-mb8f-1qw9` / `PKSA-mdq4-51ck-6kdq` (CRLF in default email rule) — we validate with `email:rfc`; framework upgrade planned with P9.
Revisit on every phase: if a patched 11.x/12.x appears, pin it and re-enable the policy.
