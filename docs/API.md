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

## Conventions
- `POST` → `201` with `{message, data}`; `PUT` → `200`; `DELETE` → `200` with message.
- Pagination: `{data, meta: {current_page, per_page, total}}`.
- Tokens: JWT (tymon/jwt-auth), TTL 120 min (configurable via `JWT_TTL`).
