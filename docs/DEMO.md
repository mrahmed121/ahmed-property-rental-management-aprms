# APRMS Demo Guide

A repeatable walkthrough using seeded demo data. Log in as `admin@ahmedestates.local` / `password123`.

## The Journey

### 1. Dashboard
Overview of properties, units, occupancy, and financial position. All metrics are query-backed.

### 2. Properties → Buildings → Units
- **Properties:** 4 seeded across 2 agencies. Try search, filters, archive/restore.
- Open **Bahria Greens Estate** → buildings → units. Note unit occupancy status.

### 3. Tenants → Leases
- **Tenants:** Ahmed Raza has an active lease on R-101 (linked to the tenant portal login).
- **Leases:** view the lifecycle — applications, screening, activation, renewal, termination.

### 4. Rent & Collections (P4)
- **Invoices:** rent invoices generated per lease.
- **Payments:** record a payment; watch the allocation waterfall (late fees → utilities → rent → arrears).
- **Ledger:** per-tenant statement with running balance.

### 5. Deposits (P5)
- Deposit with 3× rent cap enforcement. Try exceeding the cap — the server rejects it.
- Deductions with wear/damage assessment, settlement behind a reviewed move-out inspection.

### 6. Maintenance (P5)
- Tickets (MT-YYYY-NNNN) with SLA badges. Triage → assign → quote → approve → work → verify → close.
- Quotes carry owner/tenant cost attribution with required reasons.

### 7. Utilities (P6)
- **Meters:** DEMO-EL-001 (electricity, on R-101), DEMO-WA-001 (shared water).
- Record a reading — try a decreasing value to see the validation.
- Generate a bill, preview the math, finalize. Tenant charges hit the P4 ledger.
- Shared meter bills show the **vacant-unit owner absorption** line.

### 8. Expenses (P6)
- Create → submit → approve → post. Try approving your own submission as a non-admin.
- Summary shows real spend by category and property.

### 9. Owner Statements (P7)
- Log in as `owner@ahmedestates.local` to see the owner-scoped view.
- As admin: **Statements → Generate** — preview the reconciliation, then generate.
- Walk the statement through review → approved → finalized. Download the PDF.

## Cross-Cutting Demos
- **Agency isolation:** log in as `admin@secondagency.local` — Agency A's data is invisible (404).
- **Tenant portal:** `tenant@ahmedestates.local` sees only their lease, meters, and ledger.
- **Auditor:** `auditor@ahmedestates.local` — everything readable, nothing mutable.
