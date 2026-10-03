# APRMS Database (P1 + P2)

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

### property_documents (P2)
`agency_id` FK `cascadeOnDelete`, polymorphic parent
(`documentable_type` = Property|Building|Unit, `documentable_id`),
`name`, `document_type` (`deed|noc|floor_plan|photo|agreement|other`),
`file_path` (relative, inside `storage/app/private/documents/{agency_id}/` —
never a URL, never web-accessible), `mime_type`, `file_size`,
`description` nullable, `uploaded_by` FK → users nullable, timestamps.
Index: (agency_id, documentable_type, documentable_id).

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
- No child records under archived parents (service-level guard).
- Cross-agency FKs are impossible by construction: child rows carry `agency_id`
  and the `AgencyScope` filters every query.
