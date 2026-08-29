# Jayson CRM

A CRM built on Laravel 13 and Vue 3, running entirely in Docker.

Contacts, companies, deals across configurable pipelines, and an activity
timeline — with role-based access control, a full audit trail, and soft deletes
on every record.

## Requirements

Docker, Node, and a **MySQL server on your host**. No local PHP or Composer
needed — every PHP command runs inside the `app` container.

## Getting started

```bash
make setup      # build images, install deps, migrate, seed, build assets
```

Then open **http://localhost:8089**.

### Database

By default the app connects to **MySQL on your host machine**
(`DB_HOST=host.docker.internal` — not `127.0.0.1`, which from inside a container
points at the container itself). Create the two schemas first:

```sql
CREATE DATABASE crm         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE crm_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Prefer a throwaway container? Set `DB_HOST=mysql`, `DB_USERNAME=crm`,
`DB_PASSWORD=secret` in `.env`, then:

```bash
docker compose --profile docker-db up -d
```

Host ports are `8089` (web) and `6381` (Redis), plus `3307` if you run the
bundled MySQL. Change them in `.env`.

### Demo accounts

Seeded by `DemoDataSeeder`. Password for all: `password`

| Email | Role | What they can do |
|---|---|---|
| `admin@crm.test` | admin | Everything, including user management |
| `manager@crm.test` | manager | Edit any record, read the audit log |
| `rep@crm.test` | sales_rep | See everything, edit only what they own |
| `viewer@crm.test` | viewer | Read-only |

The dataset seeds 6 users, 18 companies, ~50 contacts, ~35 deals across two
pipelines, and ~140 activities — plus the audit-log rows their creation
generated, because the seeder runs through the same model events as the app.

## Common tasks

```bash
make up / down / restart / logs / ps
make fresh        # rebuild the database from scratch and reseed
make test         # Pest suite (own crm_testing schema)
make test-e2e     # Playwright browser tests against the running stack
make lint         # Pint
make dev          # Vite with hot reload
```

## Architecture

```
app/Models          Eloquent models; every CRUD model uses the Auditable trait
app/Policies        Authorization, one policy per resource over a shared base
app/Http/Requests   Form Request validation
app/Http/Resources  API shapes (full + slim "summary" variants for nesting)
app/Services        Business logic (DealService owns stage transitions)
app/Support         CrmCache — tagged cache-aside helper
resources/js        Vue 3 SPA (Composition API, Vue Router, TanStack Vue Query)
```

### Auditing

Every CRUD model uses `App\Models\Concerns\Auditable`, which combines three
things so they can never drift apart:

- **Soft deletes** — `deleted_at`; nothing is ever hard-deleted through the API.
- **Blame stamps** — `created_by`, `updated_by`, `deleted_by`, set automatically
  from the authenticated user. Restoring clears the delete stamp.
- **Activity log** — a `spatie/laravel-activitylog` entry for every create,
  update, delete and restore, recording who did it and the before/after values.
  Passwords are explicitly excluded.

All of it is exposed on the API under each record's `audit` key, and browsable
at **/audit-log**.

### Authorization

Spatie permissions named `<resource>.<action>` (e.g. `contacts.update`), granted
to four roles. Two rules decide access, both in `App\Policies\CrmPolicy`:

1. the caller holds the permission, **and**
2. the record is in scope — they own it, or they hold `records.manage-any`.

Reading is org-wide; only writes narrow to owned records. The UI reflects this
by asking `can('contacts.update')` rather than checking role names.

### Caching and invalidation

Redis backs cache, sessions, queues and rate limiting. Expensive reads
(dashboard aggregates, pipelines, tags) go through `CrmCache`, which writes
under a tag. `CrmCacheObserver` is attached to every cached-into model, so any
write flushes the affected tags — a mutation can never leave a stale read
behind.

Cached payloads are always fully rendered arrays, never Eloquent models.

### Rate limiting

- `auth` — 5/min per email (blocks password grinding), 20/min per IP (loose
  enough for a shared office address)
- `mutations` — 60/min per user
- `api` — 120/min per user

Throttled requests return `429` with `Retry-After` and a generic message; the
frontend surfaces it as a toast rather than retrying in a loop.

### Security

Security headers (CSP, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`, HSTS under TLS) are set by `SecurityHeaders` middleware so
they hold behind any proxy. CORS origins are explicit. Auth is Sanctum's SPA
cookie + CSRF flow. Validation is Form-Request-only, with `$fillable` and
policies guarding mass assignment and IDOR.

`APP_DEBUG=false` in production; clients get generic errors, details stay in the
log.

## Testing

- **Pest** (`make test`) — 63 tests covering auth, the audit trail, soft-delete
  and blame behaviour, CRUD and validation, authorization for all four roles,
  deal stage transitions, cache invalidation, and security headers. Runs against
  its own `crm_testing` MySQL schema.
- **Playwright** (`make test-e2e`) — 8 browser tests over the critical flows:
  sign-in, dashboard, contact search and detail, create-then-soft-delete, the
  pipeline board, the audit log, and that a viewer sees no write controls. Uses
  the locally installed Chrome, so there is no browser download.

## Notes

- The SPA and API share an origin; `routes/web.php` serves the shell for any
  non-API path so deep links work.
- `Model::shouldBeStrict()` is on outside production, so N+1 queries and
  missing-attribute reads fail loudly in development and tests.
