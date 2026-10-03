# Ahmed Property & Rental Management System (APRMS)

> **Ahmed — Own Every Square Foot.**

A professional property & rental management platform for owners, agencies, and
property managers: properties, leases, rent collection, deposits, maintenance,
expenses, owner statements, and reports — with strict multi-agency isolation,
role-based access, and a full audit trail.

**Status: P2 Property domain — implemented and verified.**
79 backend tests pass (323 assertions) · 14 frontend tests pass.
P3+ domain modules (Leasing, Billing, Maintenance, Reporting) are documented
contracts; their business logic lands in their own phases.

---

## Tech Stack
- **Backend:** Laravel 11 (PHP 8.3), JWT auth (`tymon/jwt-auth`), SQLite (MySQL/PG-ready)
- **Frontend:** React 18, Vite 6, Tailwind CSS 3, React Router 6
- **API:** versioned under `/api/v1`, JSON everywhere

## Quick Start

### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
touch database/database.sqlite
php artisan migrate --seed
php artisan serve          # http://127.0.0.1:8000
```

### Frontend
```bash
cd frontend
npm install
npm run dev                # http://localhost:5173 (proxies /api → :8000)
```

### Demo credentials (seeded, local development only)
| Email | Role | Agency |
|---|---|---|
| `super@aprms.local` | Super Admin | — (platform) |
| `admin@ahmedestates.local` | Agency Admin | Ahmed Estates |
| `manager@ahmedestates.local` | Property Manager | Ahmed Estates |
| `accountant@ahmedestates.local` | Accountant | Ahmed Estates |
| `auditor@ahmedestates.local` | Auditor (read-only) | Ahmed Estates |
| `admin@secondagency.local` | Agency Admin | Second Agency |

Password for all demo users: `password123`

## API Overview
| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/health` | Service + DB status |
| POST | `/api/v1/auth/login` | Issue JWT |
| POST | `/api/v1/auth/logout` | Blacklist JWT |
| GET | `/api/v1/me` | Current user, roles, permissions |
| GET/POST | `/api/v1/users` | List (scoped) / create |
| GET/PUT/DELETE | `/api/v1/users/{id}` | Scoped; cross-agency → 404 |
| GET/POST/PUT | `/api/v1/roles`, `/api/v1/permissions` | RBAC management |
| GET/PUT | `/api/v1/settings` | Agency-scoped typed settings |
| GET | `/api/v1/audit-logs` | Read-only audit trail |
| GET | `/api/v1/dashboard/stats` | Real portfolio counts (properties, buildings, units, vacant/occupied) |
| GET/POST | `/api/v1/properties` | List (search/filter/sort/paginate) / create |
| GET/PUT/DELETE | `/api/v1/properties/{id}` | Detail / update / archive (cascades) |
| POST | `/api/v1/properties/{id}/restore` | Restore with archived children |
| GET/POST | `/api/v1/buildings` | List / create under a property |
| GET/PUT/DELETE | `/api/v1/buildings/{id}` | Detail / update / archive |
| GET/POST | `/api/v1/units` | List / create under a building |
| GET/PUT/DELETE | `/api/v1/units/{id}` | Detail / update / archive |
| GET/POST | `/api/v1/documents` | List / multipart upload (≤ 10 MB) |
| GET | `/api/v1/documents/{id}/download` | Authenticated file stream |
| DELETE | `/api/v1/documents/{id}` | Delete record + file |

Full reference: [`docs/API.md`](docs/API.md) · Architecture: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) · Schema: [`docs/DATABASE.md`](docs/DATABASE.md)

## Testing
```bash
cd backend && php artisan test     # 79 passed, 323 assertions
cd frontend && npm test -- --run   # 14 passed (auth + property workflows)
```

## Security Notes
- Passwords bcrypt-hashed; JWT blacklisted on logout; permission middleware on every route.
- Agency isolation enforced at query (global scope), service (403 guard), and API (404, no existence leak) layers — all covered by tests.
- Composer advisory policy is disabled (`"config": {"policy": false}`) because all Laravel 11.x carry advisories with no patched release; each advisory's mitigation is documented in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## License
MIT — see [LICENSE](LICENSE).

---
<p align="center">Developed by Ahmed</p>
