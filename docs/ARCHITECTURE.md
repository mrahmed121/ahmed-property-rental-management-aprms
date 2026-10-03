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

## P4 — Billing domain (money-in)

### Financial model

Four core tables carry the money: `rent_invoices`, `payments`,
`payment_allocations`, `tenant_ledger_entries`. Supporting tables:
`late_fees`, `dunning_reminders`, `financial_periods`. All are
agency-scoped; posted records are never hard-deleted.

### Allocation waterfall (fixed, deterministic)

1. Late fees (oldest accrued first)
2. Utilities (oldest invoice first)
3. Current rent (current-period invoice)
4. Oldest arrears

The order is not user-configurable. Invariants: `sum(allocations) ≤
payment.amount`; no allocation exceeds its charge's remaining balance;
excess stays as unallocated credit on the payment — never silently lost.

### Rent cycle

`RentCycleService::generateForPeriod("YYYY-MM", $dryRun)` invoices all
eligible active leases (overlapping the period, unit not archived).
Idempotent via `UNIQUE(agency_id, lease_id, period_start)`.

**Proration rule:** `monthly_rent × (billable_days ÷ days_in_month)`,
rounded half-up to 2 decimals. `billable_days` = days in
`[period_start, period_end] ∩ [lease_start, lease_end]`. Full months bill
exactly `monthly_rent`.

### Late fees

Accrued from agency settings (`billing.late_fee_type/value/cap`,
`billing.grace_days`) — no invented percentages. One fee per invoice
(`UNIQUE(agency_id, invoice_id)`); the rule is snapshotted on the fee.

### Ledger discipline

`TenantLedgerService` is the single write path. `balance_after =
previous + debit − credit`, computed under a row lock on the tenant's
latest entry. Corrections use new `adjustment`/`reversal` entries.

### Period locking

`FinancialPeriodService` locks past periods (`YYYY-MM`). Invoice,
payment, and late-fee writes assert the period is open; corrections go
through adjustments/reversals.

### Security advisories

`barryvdh/laravel-dompdf` added for receipt PDFs. The pre-existing
`laravel/framework` advisories remain documented with `policy: false`
(see above); dompdf introduces no new advisories at install time.

## P5 — Deposits domain

Four tables: `deposits`, `deposit_transactions` (append-only),
`deposit_deductions`, `deposit_settlements`. One deposit per lease
(UNIQUE). Cap: `deposit_amount ≤ 3 × monthly_rent`, enforced
server-side in `DepositService`; invalid amounts are rejected, never
silently modified.

Settlement math is deterministic:
`refund = gross − approved_deductions − applied_to_balance`.
Deductions need an approved reason (no arbitrary deductions);
wear vs damage is a structured field. Final settlement requires a
reviewed move-out inspection. Applied amounts flow through the P4
`TenantLedgerService` (no second money system). Finalized settlements
are immutable; corrections use `reverse()` which restores the held
amount and reverses the ledger credit. Concurrency: row locks on the
deposit; UNIQUE(agency_id, deposit_id) on settlements prevents double
finalization.

## P5 — Maintenance domain

Tables: `maintenance_tickets` (MT-… numbering), `maintenance_quotes`,
`maintenance_work_logs`, `maintenance_verifications`,
`maintenance_vendors`. Status machine enforced via
`MaintenanceTicket::TRANSITIONS` — impossible transitions return 422.
SLA: priority → hours (urgent 4h, high 24h, normal 72h, low 7d);
`isBreached()` excludes closed/cancelled/verified. Quotes require
approval before work (no auto-approval); cost attribution is
owner|tenant with a required reason. Verification is required before
`verified`/`closed`. Technicians see only assigned tickets; tenants see
only their own; owners see owned properties. All agency-scoped.
Documents reuse `property_documents` with the `ticket` parent type.

## P6 — Utilities + Expenses Architecture

### Utilities domain (`app/Domains/Utilities/`)

- **Models:** `UtilityMeter`, `MeterReading`, `UtilityBill`, `UtilityAllocation` — all `BelongsToAgency`.
- **Services:**
  - `UtilityMeterService` — meters, readings, consumption. Enforces monotonic readings, duplicate prevention, tenant/owner scoping.
  - `UtilityBillingService` — bill generation, allocation, finalization, reversal. Tenant charges post via P4 `TenantLedgerService` (no second ledger).
- **Bill numbering:** `UB-{year}-{seq}` (agency-scoped, deterministic).
- **Concurrency:** `lockForUpdate()` on meter (reading/bill generation) and bill (finalize/reverse/allocate); UNIQUE(agency_id, meter_id, period_start); idempotent finalize.
- **Permissions:** `utilities.view/manage/bill/adjust`. Tenants see own unit meters/bills; owners see own properties; technicians see nothing; auditors read-only.

### Expenses domain (`app/Domains/Expenses/`)

- **Model:** `Expense` — `BelongsToAgency`, state machine via `TRANSITIONS`.
- **Service:** `ExpenseService` — create, transition (permission-checked per target state), reverse, summary metrics.
- **Expense numbering:** `EXP-{year}-{seq}` (agency-scoped).
- **Workflow:** `draft → submitted → approved/rejected → posted → reversed`. Segregation of duties enforced: non-admins cannot approve their own submissions.
- **Financial boundary:** expenses are property/owner-side money-out. They NEVER post to the P4 tenant ledger. Architecture prepares `property income − expenses − owner-attributable costs` inputs for P7 owner statements (not built).
- **Vendors:** reuses P5 `maintenance_vendors` (no duplicate table). **Documents:** reuses property-document system with `expense` parent.
- **Concurrency:** `lockForUpdate()` on transitions/reversals; posted expenses immutable.
- **Permissions:** `expenses.view/create/approve/post/reverse`. Tenants and technicians have no access; auditors read-only.

## P7 — Owner Statements Architecture

### Statements domain (`app/Domains/Statements/`)

- **Models:** `StatementPeriod`, `OwnerStatement`, `StatementLine`, `StatementAdjustment` — all `BelongsToAgency`.
- **Services:**
  - `StatementGenerationService` — preview/generate. Consumes P4 ledger (income), P6 expenses, P5 quotes (maintenance), P6 allocations (utility). No second ledger.
  - `StatementWorkflowService` — transitions, adjustments, recalculation, period locking. Finalized statements immutable.
  - `OwnerReportingService` — portfolio, profitability, trend. All query-backed.
- **Statement numbering:** `STMT-{year}-{seq}` (agency-scoped).
- **Workflow:** `draft → review → approved → finalized`; period `open → review → approved → finalized → locked`.
- **Concurrency:** `lockForUpdate()` on generation/finalize/adjust/lock; UNIQUE(agency, owner, period); idempotent generate.
- **Double-count guards:** income uses invoice debits only; maintenance quotes skip when matched to a posted expense (same vendor+amount±7 days); tenant-attributed costs and tenant utility charges excluded.
- **PDF:** `barryvdh/laravel-dompdf` with `resources/views/statements/owner-statement.blade.php` (Ahmed branding, no fabricated signatures).
- **Permissions:** `statements.view/generate/review/approve/finalize/adjust`, `owner-reports.view`. Owners see own only; tenants/technicians none; auditors read-only.
