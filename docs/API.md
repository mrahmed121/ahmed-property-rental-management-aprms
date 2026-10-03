# APRMS API v1

Base: `/api/v1`. JSON everywhere. Auth: `Authorization: Bearer <JWT>`.
Errors: `401` unauthenticated · `403` forbidden · `422` validation (`{errors}`) ·
`404` not found (also used for cross-agency lookups to avoid leaking existence).

## Public
| Method | Path | Description |
|---|---|---|
| GET | `/health` | Service status, DB latency. 503 if DB down |
| POST | `/auth/login` | `{email, password}` → `{token, token_type, expires_in, user}` |

## Authenticated (`auth:api`)
| Method | Path | Permission | Description |
|---|---|---|---|
| POST | `/auth/logout` | — | Blacklists the token |
| POST | `/auth/refresh` | — | Fresh token |
| GET | `/me` | — | Current user + agency + roles + permissions |
| GET | `/users` | `users.view` | Paginated, agency-scoped |
| POST | `/users` | `users.manage` | Create (agency forced to actor's unless Super Admin) |
| GET | `/users/{id}` | `users.view` | 404 outside own agency |
| PUT | `/users/{id}` | `users.manage` | Update + role sync |
| DELETE | `/users/{id}` | `users.manage` | Soft delete (cannot delete self) |
| GET | `/roles` | `roles.view` | System roles + own agency roles |
| POST | `/roles` | `roles.manage` | Create agency role + permissions |
| PUT | `/roles/{id}` | `roles.manage` | System roles immutable (403) |
| GET | `/permissions` | `roles.view` | Permission catalogue |
| GET | `/settings` | `settings.view` | Grouped, typed values (agency-scoped) |
| PUT | `/settings` | `settings.manage` | Bulk upsert, type-validated |
| GET | `/audit-logs` | `audit.view` | Filterable (`action`, `user_id`), agency-scoped, read-only |
| GET | `/dashboard/stats` | `dashboard.view` | Real counts: properties, buildings, units, vacant/occupied, units_by_status, tenants, active_leases, leases_expiring_soon, leases_by_status |

## P2 — Property domain
| Method | Path | Permission | Description |
|---|---|---|---|
| GET | `/properties` | `properties.view` | Search (`search`), filters (`property_type`, `status`, `city`, `owner_id`), sort, paginated |
| POST | `/properties` | `properties.manage` | Create (agency forced to actor's unless Super Admin) |
| GET | `/properties/{id}` | `properties.view` | Detail + buildings; 404 outside agency/portfolio |
| PUT | `/properties/{id}` | `properties.manage` | Update |
| DELETE | `/properties/{id}` | `properties.manage` | Archive (soft delete, cascades to buildings/units) |
| POST | `/properties/{id}/restore` | `properties.manage` | Restore with archived children |
| GET | `/buildings` | `buildings.view` | Filter by `property_id`, search, status |
| POST | `/buildings` | `buildings.manage` | Create under a property (same agency, not archived) |
| GET | `/buildings/{id}` | `buildings.view` | Detail + units |
| PUT | `/buildings/{id}` | `buildings.manage` | Update (`property_id` immutable) |
| DELETE | `/buildings/{id}` | `buildings.manage` | Archive (cascades to units) |
| POST | `/buildings/{id}/restore` | `buildings.manage` | Restore (blocked if property archived) |
| GET | `/units` | `units.view` | Filter by `building_id`/`property_id`/`status`/`unit_type`, search |
| POST | `/units` | `units.manage` | Create under a building; `unit_number` unique per building |
| GET | `/units/{id}` | `units.view` | Detail + documents |
| PUT | `/units/{id}` | `units.manage` | Update (`building_id` immutable) |
| DELETE | `/units/{id}` | `units.manage` | Archive (soft delete) |
| POST | `/units/{id}/restore` | `units.manage` | Restore (blocked if building/property archived) |
| GET | `/documents` | `documents.view` | Filter by `parent_type`+`parent_id`, `document_type` |
| POST | `/documents` | `documents.manage` | Multipart upload (`parent_type`, `parent_id`, `file`); PDF/JPG/PNG/WEBP/DOC/DOCX/TXT ≤ 10 MB |
| GET | `/documents/{id}/download` | `documents.view` | Authenticated file stream (agency + portfolio re-checked) |
| DELETE | `/documents/{id}` | `documents.manage` | Delete record + file |

Portfolio scoping: owners see only properties where they are `owner_id`; tenants see only their own leased unit/property via P3 leases. All other roles with view permissions see the full agency portfolio. Cross-agency access always returns 404.

## P3 — Leasing domain
| Method | Path | Permission | Description |
|---|---|---|---|
| GET | `/tenants` | `tenants.view` | Search, `status`/`kyc_status` filters, paginated; tenant role sees own record only |
| POST | `/tenants` | `tenants.manage` | Create (national_id write-only, returned masked) |
| GET | `/tenants/{id}` | `tenants.view` | Detail + applications + leases; 404 outside agency/scope |
| PUT | `/tenants/{id}` | `tenants.manage` | Update |
| DELETE | `/tenants/{id}` | `tenants.manage` | Archive (soft delete; history preserved) |
| GET | `/applications` | `applications.view` | Filter by `status`, `tenant_id`, search |
| POST | `/applications` | `applications.manage` | Create draft (unit must belong to property) |
| GET | `/applications/{id}` | `applications.view` | Detail + screening state |
| PUT | `/applications/{id}` | `applications.manage` | Update draft only |
| POST | `/applications/{id}/transition` | `applications.manage` | `draft→submitted→under_review→screening→approved/rejected`; approval needs clear screening + verified KYC |
| POST | `/applications/{id}/screening/start` | `screening.manage` | Begin screening |
| POST | `/applications/{id}/screening/decide` | `screening.manage` | `{clear, notes, kyc_status}` |
| GET | `/leases` | `leases.view` | Filter by `status`, `tenant_id`, `unit_id`, search |
| POST | `/leases` | `leases.manage` | Create draft (validates dates, tenant KYC, unit eligibility) |
| GET | `/leases/{id}` | `leases.view` | Detail + renewal chain + documents |
| PUT | `/leases/{id}` | `leases.manage` | Update draft only |
| POST | `/leases/{id}/activate` | `leases.manage` | Row-locked activation; sets unit occupied; rejects overlaps |
| POST | `/leases/{id}/renew` | `leases.manage` | Creates successor draft (original preserved) |
| POST | `/leases/{id}/terminate` | `leases.manage` | `{termination_date, reason}`; restores vacancy |
| GET | `/inspections` | `inspections.view` | Filter by `lease_id`, `condition` |
| POST | `/inspections` | `inspections.manage` | Record for terminated/expired lease (one per lease) |
| GET | `/inspections/{id}` | `inspections.view` | Detail |
| PUT | `/inspections/{id}` | `inspections.manage` | Update pending inspection |
| POST | `/inspections/{id}/review` | `inspections.manage` | Mark reviewed |

Leasing scoping (P3): tenants see only their own tenant record, applications, leases, and documents. Owners see leasing data for properties they own. Auditors are read-only. Lease documents use `parent_type=lease|tenant|application|inspection`. Archiving a property/building/unit with active leases returns 422.

## Conventions
- `POST` → `201` with `{message, data}`; `PUT` → `200`; `DELETE` → `200` with message.
- Pagination: `{data, meta: {current_page, per_page, total}}`.
- Tokens: JWT (tymon/jwt-auth), TTL 120 min (configurable via `JWT_TTL`).

## P4 — Billing / money-in

All financial amounts are in PKR (₨). Every number is database-backed.

### Invoices

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/invoices` | `invoices.view` | List (filters: tenant, lease, unit, property, status, overdue, date/amount range, search) |
| POST | `/invoices` | `invoices.generate` | Create for a lease period (idempotent on lease+period) |
| GET | `/invoices/{id}` | `invoices.view` | Detail with late fee + dunning |
| POST | `/invoices/{id}/void` | `invoices.generate` | Void (posts ledger reversal; blocked if paid) |
| POST | `/rent-cycle/generate` | `invoices.generate` | Monthly generation; `{period: "YYYY-MM", dry_run: bool}` |
| POST | `/late-fees/accrue` | `billing.adjust` | Accrue fees for overdue invoices (idempotent) |
| POST | `/late-fees/{id}/waive` | `billing.adjust` | Waive a fee (ledger adjustment) |

### Payments

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/payments` | `payments.view` | List (filters: tenant, method, status, date/amount range, search) |
| POST | `/payments/preview` | `payments.record` | Preview waterfall allocation (no write) |
| POST | `/payments` | `payments.record` | Record payment; idempotent via `idempotency_key` |
| GET | `/payments/{id}` | `payments.view` | Detail with allocation lines |
| POST | `/payments/{id}/reverse` | `payments.reverse` | Reverse (restores balances; keeps history) |

### Ledger / dunning / receipts / financial

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/tenants/{id}/ledger` | `ledger.view` | Statement: opening, entries, closing (derived) |
| GET | `/tenants/{id}/ledger/balance` | `ledger.view` | Current derived balance |
| GET | `/dunning` | `dunning.view` | Reminder list |
| POST | `/dunning/process` | `dunning.manage` | Schedule due reminders (idempotent) |
| POST | `/dunning/{id}/sent` | `dunning.manage` | Mark sent |
| GET | `/payments/{id}/receipt` | `receipts.view` | Receipt data |
| GET | `/payments/{id}/receipt/pdf` | `receipts.view` | Receipt PDF |
| GET | `/financial/dashboard` | `billing.view` | Billed/collected/outstanding/overdue/rate/aging |
| GET | `/financial/arrears` | `billing.view` | Unpaid invoices, oldest first |
| GET | `/financial/periods` | `billing.view` | Period list |
| POST | `/financial/periods/lock` | `periods.manage` | Lock a past period |
| POST | `/financial/periods/unlock` | `periods.manage` | Unlock |
