# LinkedIn Post Draft — APRMS (for Ahmed to publish himself)

---

I built APRMS — a complete Property & Rental Management System.

The problem: property agencies juggle properties, tenants, leases, rent collection, deposits, maintenance, utilities, and owner payouts across spreadsheets and disconnected tools. Money leaks through the cracks.

APRMS puts the entire rental lifecycle in one system:

🏢 Properties, buildings, units — with documents and archive/restore
📝 Tenants, applications, screening, full lease lifecycle
💰 Rent invoices, payments, and an allocation waterfall (late fees → utilities → rent → arrears)
🔐 Deposits with a server-enforced 3× rent cap and audited settlements
🔧 Maintenance tickets with SLA tracking, quotes, and owner/tenant cost attribution
⚡ Utility meters, readings, billing, and vacant-unit owner absorption
📊 Accrual-based owner statements with deterministic reconciliation — down to a PDF

Architecture: Laravel 11 API (domain-oriented services, no second ledgers) + React 18 SPA. Nine roles with granular permissions, strict multi-agency isolation, append-only audit trail.

The engineering I'm proudest of: a single tenant ledger that rent, utilities, and deposits all flow through correctly; payment allocation that respects the waterfall; owner statements where every number traces back to its source line.

Verified, not claimed: 211 backend tests (1,349 assertions) and 49 frontend tests, all passing. Live end-to-end flows tested against a real server.

GitHub: [ADD FINAL GITHUB REPOSITORY LINK AFTER PUBLISHING]

#PropertyManagement #Laravel #React #PHP #SoftwareEngineering #RealEstate #FinTech

---

*Notes for Ahmed: Replace the GitHub link after publishing. Test counts are actual (211 backend / 49 frontend as of P8). Adjust if counts change.*
