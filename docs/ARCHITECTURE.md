# APRMS Architecture

> Ahmed Property & Rental Management System — P1 Foundation + P2 Property domain + P3 Leasing domain.
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
├── Property/                                    (P2 — implemented)
│   ├── Models/        Property, Building, Unit, PropertyDocument
│   └── Services/      PropertyService, BuildingService, UnitService,
│                      DocumentService, PropertyAccess (portfolio scoping)
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
422 validation (`{message, errors}` — always JSON on `/api/*`, never a redirect),
404 for cross-agency lookups (existence is never leaked).

## P2 — Property domain (implemented)
- **Hierarchy:** Agency → Properties → Buildings → Units; documents attach
  polymorphically to property/building/unit.
- **Archive, not delete:** properties/buildings/units soft-delete with cascade;
  documents are preserved. `POST …/restore` reverses the cascade (blocked when a
  parent is still archived). No hard-delete endpoints.
- **Portfolio scoping** (`PropertyAccess`): owners see only properties where
  `owner_id` = themselves; tenants see none in P2 (P3 leases will link them);
  portfolio roles see the whole agency. Applied in every property-domain service.
- **Documents:** real multipart upload (PDF/JPG/PNG/WEBP/DOC/DOCX/TXT ≤ 10 MB),
  stored under `storage/app/private/documents/{agency_id}/` (never web-accessible);
  downloads stream through an authenticated endpoint that re-checks agency +
  portfolio. No public URLs.
- **Dashboard:** `GET /api/v1/dashboard/stats` returns real counts
  (properties, buildings, units, vacant/occupied, units_by_status) —
  agency- and portfolio-scoped. Frontend shows honest empty states at zero.
- **Frontend:** `src/modules/property/` — Properties list (search/filter/sort/
  pagination), create/edit form with inline validation, detail page with
  Buildings/Units/Documents tabs, archive confirmations, permission-gated
  actions, responsive layouts.
- P2 audit actions: `properties.create/update/archive/restore`,
  `buildings.create/update/archive/restore`, `units.create/update/archive/restore`,
  `documents.upload/delete`.

## P3 — Leasing domain (implemented)
- **Chain:** Tenant → Application → Screening/KYC → Lease → Activation →
  Renewal → Termination → Move-out inspection. Billing stays in P4.
- **Services:** `TenantService`, `ApplicationService`, `ScreeningService`,
  `LeaseService` (draft/activate), `LeaseRenewalService`, `LeaseTerminationService`,
  `MoveOutInspectionService`, `DashboardLeasingMetrics`. Controllers stay thin;
  FormRequests validate every input.
- **Concurrency:** activation runs in a DB transaction with `lockForUpdate()` on
  the unit row; the overlap check runs inside the same transaction, so two
  simultaneous activations cannot both succeed. A renewal successor may take
  over only when the occupant is its own predecessor.
- **Occupancy:** activation sets the unit `occupied` and the tenant `active`;
  termination/expiry restore `vacant` when no replacement active lease exists.
  Units in `maintenance`/`inactive` can never activate.
- **History:** renewal creates a successor (`previous_lease_id`); the original
  flips to `renewed` on successor activation — never overwritten. Termination
  requires date + reason + authorized actor; deposit settlement is P4+ (P3 only
  prepares the inspection record).
- **Scoping:** `TenantAccess` — tenants see only their own records; owners see
  leasing data for owned properties; auditors read-only. `PropertyAccess` now
  links tenants to their leased unit/property. Document parents extended to
  tenant/application/lease/inspection with matching portfolio rules.
- **Security:** `national_id` is write-only (returned masked); the actor is
  resolved lazily per call (`DomainService::actor()`) so long-lived containers
  never pin a previous request's user.
- **Dashboard:** adds `total_tenants`, `active_leases`, `leases_expiring_soon`
  (≤ 60 days), `leases_by_status` — real queries, tenant-scoped for the portal.
- **Frontend:** `src/modules/leasing/` — tenants, applications (review +
  screening workflow), leases (activate/renew/terminate + documents),
  inspections. Sidebar "Leasing" group, permission-gated.
- P3 audit actions: `tenants.create/update/archive`, `applications.create/update/
  submitted/under_review/screening_started/screening_decided/approved/rejected`,
  `leases.create/update/activate/renew/terminate/expired`,
  `inspections.create/update/reviewed`.
- Console: `php artisan leases:mark-expired` flips past-due active leases to
  `expired` and restores vacancy.

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
P2 adds 8 property-domain permissions: `properties.view/manage`,
`buildings.view/manage`, `units.view/manage`, `documents.view/manage` —
auditor stays read-only, owner/tenant get scoped view rights only.
P3 adds 10 leasing permissions: `tenants.view/manage`, `applications.view/manage`,
`screening.view/manage`, `leases.view/manage`, `inspections.view/manage`.
Agency Admin and Property Manager hold the full set; Accountant gets leasing
reads; Maintenance Supervisor gets inspection reads; Owner/Tenant get scoped
reads; Auditor gets all P3 reads; Technician gets none.

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
