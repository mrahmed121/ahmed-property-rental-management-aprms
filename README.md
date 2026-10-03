# Ahmed Property & Rental Management System (APRMS)

> **Ahmed — Own Every Square Foot.**

[![Laravel](https://img.shields.io/badge/Laravel-11-red)](https://laravel.com)
[![React](https://img.shields.io/badge/React-18-blue)](https://react.dev)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.3-777bb4)](https://php.net)

A professional property & rental management platform for owners, agencies, and property managers. APRMS handles the full rental lifecycle — properties, tenants, leases, rent collection, deposits, maintenance, utilities, expenses, and owner statements — with strict multi-agency isolation, role-based access control, and a complete audit trail.

**Status:** All 7 phases implemented and verified. 211 backend tests pass (1,349 assertions) · 49 frontend tests pass.

---

## Executive Overview

APRMS is a multi-tenant SaaS-style platform where agencies manage properties on behalf of owners. Every financial record flows through a single ledger architecture:

- **Money in** (P4): rent invoices → payments → allocation waterfall → tenant ledger
- **Money held** (P5): deposits with 3× rent cap, deductions, settlements
- **Money out** (P6): property expenses, utility billing with owner absorption
- **Owner reporting** (P7): accrual-based statements with deterministic reconciliation

Nine roles (Super Admin → Technician) with granular permissions. Agency isolation enforced at the application layer — cross-agency access returns 404.

## Key Features

### P1 — Foundation
JWT authentication, 9 roles with granular permissions, agency isolation, append-only audit logs, settings, user management.

### P2 — Property Domain
Properties, buildings, units, private documents (agency-scoped storage), archive/restore (no hard deletes), owner portfolio scoping, real dashboard metrics.

### P3 — Leasing Domain
Tenants, applications, screening/KYC, lease lifecycle (draft → active → renewed/terminated), move-out inspections, document integration.

### P4 — Billing & Collections
Rent invoices, payment recording, allocation waterfall (late fees → utilities → current rent → oldest arrears), tenant ledger, late fees, dunning reminders, receipts (PDF), financial periods with locking.

### P5 — Deposits & Maintenance
Deposits with server-enforced 3× rent cap, append-only transactions, wear-vs-damage deductions, settlements behind reviewed inspections. Maintenance tickets (MT-YYYY-NNNN) with SLA tracking, quotes with owner/tenant attribution, work logs, verification, vendors.

### P6 — Utilities & Expenses
Utility meters with monotonic readings, deterministic billing (`total = consumption × rate + fixed + tax`), shared-utility allocation, vacant-unit owner absorption. Expense workflow (draft → submitted → approved → posted) with segregation of duties. Tenant utility charges flow through the P4 ledger.

### P7 — Owner Statements
Accrual-based statements from real P4/P5/P6 data. Management fees, traceable statement lines, deterministic reconciliation, adjustments, approval/finalization, period locking, PDF statements, portfolio/profitability/trend reports.

## Design System

- **Charcoal** `#12161d` — primary background
- **Ahmed Gold** `#d4af37` — accents, brand
- **Copper** `#b87333` — secondary accents

## Demo Credentials

All demo accounts use password `password123`. For local development and demo only.

| Role | Email | Scope |
|------|-------|-------|
| Agency Admin | `admin@ahmedestates.local` | Full agency access |
| Property Manager | `manager@ahmedestates.local` | Operational |
| Accountant | `accountant@ahmedestates.local` | Financial |
| Owner | `owner@ahmedestates.local` | Own properties only |
| Tenant | `tenant@ahmedestates.local` | Own lease only |
| Auditor | `auditor@ahmedestates.local` | Read-only |

## Architecture

```
backend/
  app/Domains/          # Domain-oriented vertical slices
    Shared/             # Users, roles, agencies, audit, settings
    Property/           # P2: properties, buildings, units, documents
    Leasing/            # P3: tenants, applications, leases, inspections
    Billing/            # P4: invoices, payments, ledger, dunning
    Deposits/           # P5: deposits, settlements
    Maintenance/        # P5: tickets, quotes, vendors
    Utilities/          # P6: meters, readings, bills
    Expenses/           # P6: expenses
    Statements/         # P7: owner statements, periods, reports
  app/Http/Controllers/Api/V1/  # Thin controllers, 142 endpoints
frontend/
  src/modules/          # Feature modules (property, leasing, billing, ...)
  src/components/       # Shared UI (DataTable, StatusBadge, PermissionGuard)
```

**Principles:** Domain services own business logic. Controllers are thin. No second ledgers — P7 consumes P4/P5/P6 records. Financial tables use restrictive deletes, never hard-delete posted history.

## Getting Started

### Prerequisites
- PHP 8.3+, Composer
- Node.js 18+, npm
- SQLite (default) or MySQL/PostgreSQL

### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8001
```

### Frontend
```bash
cd frontend
npm install
npm run dev
```

Open http://localhost:5173 and log in with a demo account.

### Windows Launcher
`RUN_APRMS.bat` starts both backend and frontend (requires PHP, Composer, and Node.js installed and on PATH).

## Environment

| Variable | Description |
|----------|-------------|
| `DB_CONNECTION` | `sqlite` (default), `mysql`, or `pgsql` |
| `JWT_SECRET` | Secret for JWT signing (generate with `php artisan jwt:secret`) |

See `backend/.env.example` and `frontend/.env.example` for the full list.

## Project Structure

```
aprms/
  backend/          # Laravel 11 API
  frontend/         # React 18 SPA (Vite + Tailwind)
  docs/             # API, architecture, database, deployment, security, demo
  docs/screenshots/ # Real application screenshots
  RUN_APRMS.bat     # Windows launcher
```

## Tech Stack

- **Backend:** Laravel 11, PHP 8.3, tymon/jwt-auth, barryvdh/laravel-dompdf, SQLite/MySQL/PostgreSQL
- **Frontend:** React 18, Vite, Tailwind CSS, React Router, Vitest
- **Testing:** PHPUnit (backend), Vitest + Testing Library (frontend)

## Testing

```bash
# Backend (211 tests, 1349 assertions)
cd backend && php artisan test

# Frontend (49 tests)
cd frontend && npm test -- --run

# Production build
cd frontend && npm run build
```

## Documentation

- [API Reference](docs/API.md) — all 142 endpoints
- [Architecture](docs/ARCHITECTURE.md) — domain design, financial model
- [Database](docs/DATABASE.md) — schema reference
- [Deployment](docs/DEPLOYMENT.md) — production setup
- [Security](docs/SECURITY.md) — security model and review
- [Demo Guide](docs/DEMO.md) — repeatable demo flow
- [Changelog](CHANGELOG.md) — phase-by-phase history

## Contributing

This is Ahmed's portfolio project. Issues and suggestions are welcome.

## License

MIT — see [LICENSE](LICENSE).

---

**Developed by Ahmed.**
