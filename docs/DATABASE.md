# APRMS Database (P1)

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

## Integrity rules enforced
- One role slug per agency (partial uniqueness via nullable FK in unique key).
- No hard deletes on users (soft deletes); audit rows are never updated/deleted by the app.
- Cross-agency FKs are impossible by construction: child rows carry `agency_id`
  and the `AgencyScope` filters every query.
