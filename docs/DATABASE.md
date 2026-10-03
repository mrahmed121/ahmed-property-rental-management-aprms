# APRMS Database (P1 + P2 + P3)

Engine: SQLite by default (`database/database.sqlite`), MySQL/PostgreSQL compatible.
All tables below are migrated. Financial tables arrive in P4+.

## Tables

### agencies
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name / slug | string | slug UNIQUE |
| email, phone, address, city | nullable | |
| country | string | default `Pakistan` |
| logo_path | string nullable | |
| is_active | boolean | default true, indexed |
| created_at / updated_at | timestamps | |

### roles
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| agency_id | FK → agencies, NULL | NULL = system role (super-admin); `nullOnDelete` |
| name / slug | string | UNIQUE(agency_id, slug) |
| description | string nullable | |
| is_system | boolean | default false, indexed |

### permissions
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| slug | string | UNIQUE, e.g. `users.manage` |
| name / group / description | string | group indexed (`users`, `roles`, `settings`, …) |

### permission_role / role_user
Pivot tables with UNIQUE pairs + `cascadeOnDelete` on both FKs.

### users (extends default Laravel table)
Added: `agency_id` FK nullable (`nullOnDelete`), `phone`, `is_active` default true,
`deleted_at` (soft deletes). Index on (agency_id, is_active).
Passwords are bcrypt-hashed via the `hashed` cast — never plaintext.

### audit_logs (append-only)
`agency_id` FK nullable, `user_id` FK nullable (null = system/failed login),
`action` (indexed), `entity_type` + `entity_id` (indexed), `old_values`/`new_values`/`context` JSON,
`created_at` only (no updates, ever).

### settings
`agency_id` FK `cascadeOnDelete`, `group` (default `general`), `key`,
`value` text nullable, `type` (`string|integer|boolean|json`),
UNIQUE(agency_id, key), index (agency_id, group).

### properties (P2)
`agency_id` FK `cascadeOnDelete`, `owner_id` FK → users nullable (`nullOnDelete`,
the owning landlord), `name`, `property_type` (`residential|commercial|mixed-use`),
`address`, `city`, `postal_code` nullable, `description`/`notes` nullable,
`status` (`active|inactive`), timestamps + `deleted_at` (archive = soft delete).
Indexes: (agency_id, status), (agency_id, city), owner_id.

### buildings (P2)
`agency_id` FK, `property_id` FK `cascadeOnDelete`, `name`, `floors` nullable,
`description`/`notes` nullable, `status` (`active|inactive`), soft deletes.
UNIQUE(property_id, name) — no duplicate building names inside one property.
Index: (agency_id, status).

### units (P2)
`agency_id` FK, `building_id` FK `cascadeOnDelete`, `property_id` FK
(denormalized, always equals building's property), `unit_number`,
`floor` nullable, `unit_type` (`apartment|office|shop|room|studio|warehouse|other`),
`area_sqft` nullable, `bedrooms`/`bathrooms` nullable,
`status` (`vacant|occupied|reserved|maintenance|inactive`, default `vacant`),
`market_rent` nullable (asking rent — P2 field only, no lease/billing logic),
`notes` nullable, soft deletes.
UNIQUE(building_id, unit_number) — unit identifiers unique within their building.
Indexes: (agency_id, status), (property_id, status).

### property_documents (P2, extended P3)
`agency_id` FK `cascadeOnDelete`, polymorphic parent
(`documentable_type` = Property|Building|Unit|Tenant|TenantApplication|Lease|MoveOutInspection,
`documentable_id`),
`name`, `document_type` (`deed|noc|floor_plan|photo|agreement|other`),
`file_path` (relative, inside `storage/app/private/documents/{agency_id}/` —
never a URL, never web-accessible), `mime_type`, `file_size`,
`description` nullable, `uploaded_by` FK → users nullable, timestamps.
Index: (agency_id, documentable_type, documentable_id).

### tenants (P3)
`agency_id` FK `cascadeOnDelete`, `user_id` FK → users nullable (portal link),
`first_name`, `last_name`, `email` nullable, `phone` nullable, `national_id`
nullable (write-only via API; returned masked), `address`/`city` nullable,
`emergency_contact_name`/`phone` nullable, `kyc_status`
(`pending|verified|rejected`, default `pending`), `status`
(`prospective|active|inactive`, default `prospective`), `notes` nullable, soft deletes.
Indexes: (agency_id, status), (agency_id, last_name, first_name).

### tenant_applications (P3)
`agency_id` FK `cascadeOnDelete`, `tenant_id` FK `cascadeOnDelete`,
`property_id` FK `cascadeOnDelete`, `unit_id` FK nullable `cascadeOnDelete`,
`application_number` unique per agency (e.g. `APP-1-2026-000001`), `status`
(`draft|submitted|under_review|screening|approved|rejected`, default `draft`),
`screening_status` (`pending|in_progress|clear|flagged`, default `pending`),
`screening_notes` nullable, `screened_by` FK → users nullable, `screened_at` nullable,
`decision_notes` nullable, `reviewed_by` FK → users nullable, `reviewed_at` nullable,
timestamps.
Indexes: (agency_id, status), (tenant_id, status).

### leases (P3)
`agency_id` FK `cascadeOnDelete`, `tenant_id` FK `cascadeOnDelete`,
`unit_id` FK `cascadeOnDelete`, `property_id`/`building_id` FK `cascadeOnDelete`
(denormalized from the unit at creation), `application_id` FK nullable,
`previous_lease_id` FK nullable → leases (renewal chain),
`lease_number` unique per agency (e.g. `LSE-1-2026-000001`),
`start_date`/`end_date` (end after start), `monthly_rent` decimal,
`deposit_amount` decimal nullable, `status`
(`draft|active|renewed|terminated|expired`, default `draft`),
`terms` nullable, `notes` nullable, `created_by` FK → users nullable,
`activated_at`/`terminated_at` nullable, `termination_reason` nullable,
timestamps.
Indexes: (agency_id, status), (unit_id, status), (tenant_id, status).

### move_out_inspections (P3)
`agency_id` FK `cascadeOnDelete`, `lease_id` FK `cascadeOnDelete` unique
(one inspection per lease), `inspection_date`, `condition`
(`excellent|good|fair|poor|damaged`), `damage_observations` nullable,
`notes` nullable, `inspector_id` FK → users nullable,
`review_status` (`pending|reviewed`, default `pending`), `reviewed_at` nullable,
timestamps.

## Integrity rules enforced
- One role slug per agency (partial uniqueness via nullable FK in unique key).
- No hard deletes on users (soft deletes); audit rows are never updated/deleted by the app.
- P2 uses archive (soft delete) everywhere — no hard-delete endpoints for
  properties, buildings or units. Archiving a property cascades to its buildings
  and units; documents are preserved as evidence. Restoring reverses the cascade.
- `unit_number` is unique per building (DB constraint + request validation);
  building `name` is unique per property.
- A building's `property_id` and a unit's `building_id` are immutable after
  creation — children never move between parents.
- P3: an application moves `draft → submitted → under_review → screening →
  approved/rejected`; approval requires clear screening + verified tenant KYC.
- P3: a lease moves `draft → active → renewed/terminated/expired`. Activation
  runs inside a row-locked transaction (`lockForUpdate` on the unit), rejects
  overlapping active leases for the same unit, and sets the unit `occupied`.
  Termination/expiry restore `vacant` when no replacement active lease exists.
- P3: renewal creates a successor lease (`previous_lease_id`); the original
  flips to `renewed` only when the successor activates — history is never
  overwritten.
- P3: archiving a property/building/unit with active leases is blocked (422).
- P3: one move-out inspection per lease; inspections require a
  terminated/expired lease.
- No child records under archived parents (service-level guard).
- Cross-agency FKs are impossible by construction: child rows carry `agency_id`
  and the `AgencyScope` filters every query.

## P4 tables

| Table | Purpose | Key constraints |
|-------|---------|-----------------|
| `rent_invoices` | Rent bills per lease period | `UNIQUE(agency_id, invoice_number)`; `UNIQUE(agency_id, lease_id, period_start)` (idempotent cycle) |
| `payments` | Recorded tenant payments | `UNIQUE(agency_id, receipt_number)`; `UNIQUE(agency_id, idempotency_key)` |
| `payment_allocations` | Waterfall lines (polymorphic charge) | `UNIQUE(payment_id, allocatable_type, allocatable_id, bucket)` |
| `tenant_ledger_entries` | Derived running-balance ledger | indexed `(agency_id, tenant_id, entry_date)` |
| `late_fees` | Accrued fees (rule snapshot) | `UNIQUE(agency_id, invoice_id)` (one fee per invoice) |
| `dunning_reminders` | Reminder cadence state | `UNIQUE(agency_id, invoice_id, stage)` |
| `financial_periods` | Period open/locked | `UNIQUE(agency_id, period)` |

### Integrity rules

- `payments.amount > 0`; `sum(allocations) ≤ payment.amount`.
- Ledger `balance_after` is derived, never hand-set; `lockForUpdate()`
  serializes balance computation per tenant.
- Posted financial rows are never hard-deleted: payments are
  `reversed`, invoices `void`ed, each with a compensating ledger entry.
- Invoice creation, payment posting, allocation, and late-fee accrual
  all run inside transactions with row locks.

### P5 tables

| Table | Purpose | Key constraints |
|-------|---------|-----------------|
| `deposits` | Security deposit per lease | `UNIQUE(agency_id, lease_id)`; status in required/held/partially_released/settled/closed |
| `deposit_transactions` | Append-only history | indexed `(agency_id, deposit_id)`; type in received/increase/adjustment/deduction/refund/applied |
| `deposit_deductions` | Proposed/approved deductions | indexed `(agency_id, deposit_id, status)`; assessment wear/damage |
| `deposit_settlements` | Final settlement | `UNIQUE(agency_id, deposit_id)` (no double settlement); status draft/finalized |
| `maintenance_tickets` | Work orders (MT-…) | `UNIQUE(agency_id, ticket_number)`; indexed status/priority/assigned/sla_due_at |
| `maintenance_quotes` | Estimates awaiting approval | indexed `(agency_id, ticket_id, status)`; attribution owner/tenant |
| `maintenance_work_logs` | Technician work history | indexed `(agency_id, ticket_id)` |
| `maintenance_verifications` | Verification records | `UNIQUE(agency_id, ticket_id)` |
| `maintenance_vendors` | Agency vendors | indexed `(agency_id, status)` |

### P5 integrity rules

- `deposits.deposit_amount ≤ 3 × lease.monthly_rent` (server-enforced).
- Settlement: `refund = gross − deductions − applied`; deductions + applied ≤ gross; no negative balances.
- `deposit_transactions` are append-only; settlements are reversed, never hard-deleted.
- Ticket status transitions follow the `TRANSITIONS` map; invalid moves are rejected.
- Quotes must be approved before work proceeds; verification is required before closure.

## P6 Tables

### `utility_meters`
Agency/property/building/unit linkage, `meter_number` (unique per agency), `utility_type` (electricity/gas/water/other), `unit_of_measure`, `status` (active/inactive), `installation_date`, `opening_reading`, notes.

### `meter_readings`
`meter_id`, `reading_date`, `reading_value`, `recorded_by`, `source` (manual/import/estimate), notes. UNIQUE(`meter_id`, `reading_date`) prevents duplicates. History is append-only.

### `utility_bills`
`meter_id`, property/building/unit, `tenant_id`/`lease_id` (auto-resolved from active lease), `bill_number` (UB-YYYY-NNNNNN, unique per agency), `period_start`/`period_end`, `previous_reading`, `current_reading`, `consumption`, `rate`, `fixed_charge`, `tax_amount`, `total`, `currency` (PKR), `status` (draft/finalized/reversed), `allocation_method`, `created_by`/`finalized_by`/`finalized_at`. UNIQUE(`agency_id`, `meter_id`, `period_start`) prevents duplicate finalized bills.

### `utility_allocations`
`utility_bill_id`, `unit_id`, `tenant_id`, `allocation_type` (metered/equal_split/area_based/custom/vacant_owner), `consumption_share`, `amount`. `vacant_owner` lines = owner-absorbed, never tenant-charged.

### `expenses`
Agency/property/building/unit, `vendor_id` (→ `maintenance_vendors`), `expense_number` (EXP-YYYY-NNNNNN, unique per agency), `category` (maintenance/utilities/repairs/cleaning/security/tax_fee/insurance/management/supplies/other), `description`, `expense_date`, `amount`, `currency` (PKR), `status` (draft/submitted/approved/rejected/posted/reversed), `submitted_by`/`approved_by`/`approved_at`/`posted_at`, notes. Documents via polymorphic `expense` parent on `property_documents`.

## P7 Tables

### `statement_periods`
`agency_id`, `start_date`, `end_date`, `status` (open/review/approved/finalized/locked), `locked_by`, `locked_at`. UNIQUE(`agency_id`, `start_date`, `end_date`).

### `owner_statements`
`agency_id`, `statement_period_id`, `owner_id` (→ users), `statement_number` (STMT-YYYY-NNNNNN, unique per agency), `currency` (PKR), `status` (draft/review/approved/finalized), `gross_income`, `management_fee_percent`, `management_fee`, `owner_expenses`, `owner_maintenance`, `owner_utility_absorption`, `adjustments_total`, `net_amount`, `generated_by`/`approved_by`/`approved_at`/`finalized_by`/`finalized_at`. UNIQUE(`agency_id`, `owner_id`, `statement_period_id`).

### `statement_lines`
`owner_statement_id`, `line_type` (income/management_fee/expense/maintenance/utility/adjustment), polymorphic `source` (ledger entry, expense, quote, allocation, adjustment), `property_id`, `unit_id`, `description`, `line_date`, signed `amount`, `reference`. Every number traceable.

### `statement_adjustments`
`owner_statement_id`, signed `amount`, `reason`, `created_by`. Audited; blocked on finalized statements.
