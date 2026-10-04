# LinkedIn Draft — APRMS (DO NOT PUBLISH — Ahmed publishes manually)

---

Just shipped a complete property & rental management system — built from scratch, verified end-to-end.

**APRMS — Ahmed Property & Rental Management System**
"Own Every Square Foot."

A full-stack platform for property agencies, owners, and managers handling the entire rental lifecycle:

**What it does:**
- Property → Building → Unit portfolio management with archive/restore
- Tenant onboarding, KYC, documents, and lease lifecycle (draft → activate → renew → terminate)
- Rent invoicing with idempotent monthly generation (INV-YYYY-NNNNNN)
- Payment processing with waterfall allocation: late fees → utilities → current rent → oldest arrears
- Immutable tenant ledger, receipts (RCPT-YYYY-NNNNNN), arrears aging
- Security deposits: hold → inspection → evidence-gated deductions → settlement → refund
- Maintenance: ticket → triage → assign → quote → approval → completion
- Utility meters with monotonic reading validation, bill splitting
- Property expenses with approval workflow
- Owner statements: income − management fee − costs = payout, with period locking

**Engineering:**
- Laravel 11 API + React 18 SPA (Vite + Tailwind)
- 9 roles, permission-gated routes, agency isolation at the service layer
- 142 API routes, all authz-gated
- Financial correctness: DB transactions, row locking, idempotency keys, exact decimal arithmetic
- 211 backend tests (1,349 assertions) + 78 frontend tests — all passing on a fresh database
- Zero fake data: every dashboard number is query-backed

**Verification:**
Full forensic pass before release — fresh DB, all tests green, browser QA across every module, financial integrity checks (zero duplicates, zero over-allocations), secret scan clean.

GitHub: https://github.com/mrahmed121/ahmed-property-rental-management-aprms

#Laravel #React #FullStack #PropertyManagement #FinTech #SoftwareEngineering #PHP #JavaScript
