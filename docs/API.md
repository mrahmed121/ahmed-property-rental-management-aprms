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
| GET | `/dashboard/stats` | `dashboard.view` | Real counts: properties, buildings, units, vacant/occupied, units_by_status |

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

Portfolio scoping (P2): owners see only properties where they are `owner_id`; tenants see no property data yet (P3 leases will link them). All other roles with view permissions see the full agency portfolio. Cross-agency access always returns 404.

## Conventions
- `POST` → `201` with `{message, data}`; `PUT` → `200`; `DELETE` → `200` with message.
- Pagination: `{data, meta: {current_page, per_page, total}}`.
- Tokens: JWT (tymon/jwt-auth), TTL 120 min (configurable via `JWT_TTL`).
