# Changelog

All notable changes to APRMS, organized by build phase.

## P8 — Final Hardening (2026-10-03)
- Full application audit: dead code, API, database, financial integrity, security, performance.
- Financial reconciliation checks: ledger balances, deposit integrity, statement reconciliation — all clean.
- Final README, deployment/security/demo docs, changelog.
- Real application screenshots.
- LinkedIn draft for Ahmed.
- Repository prepared for GitHub review.

## P7 — Owner Statements (2026-10-03)
- Owner statements from real P4/P5/P6 data: accrual income, management fees (agency setting, default 10%), posted expenses, owner-attributed maintenance, owner-absorbed utilities.
- Deterministic reconciliation with traceable statement lines.
- Adjustments with audit; approval/finalization workflow; period locking.
- PDF statements (dompdf, Ahmed branding); portfolio, profitability, trend reports.
- 7 new permissions; owner/agency isolation; auditor read-only.

## P6 — Utilities & Expenses (2026-10-03)
- Utility meters, monotonic readings, consumption calculation.
- Deterministic billing; shared-utility allocation; vacant-unit owner absorption as distinct line items.
- Tenant utility charges post through the P4 ledger and allocation waterfall.
- Expense workflow with segregation of duties; reuses P5 vendors and documents.
- 9 new permissions.

## P5 — Deposits & Maintenance (2026-10-03)
- Deposit lifecycle with server-enforced 3× rent cap, append-only transactions, settlements.
- Maintenance tickets with SLA, quotes, work logs, verification, vendors.
- 10 new permissions.

## P4 — Billing & Collections (2026-10-03)
- Rent invoices, payments, allocation waterfall, tenant ledger, late fees, dunning, receipts (PDF), financial periods.

## P3 — Leasing (2026-10-03)
- Tenants, applications, screening/KYC, lease lifecycle, move-out inspections.

## P2 — Property (2026-10-03)
- Properties, buildings, units, documents, archive/restore, owner scoping, dashboard metrics.

## P1 — Foundation (2026-10-02)
- JWT auth, 9 roles, agency isolation, audit logs, settings, users.
