# Jayson CRM — working notes

Laravel 13 API + Vue 3 SPA, single origin. Everything runs in Docker.

## Running commands

**There is no local PHP or Composer.** Run every PHP command inside the
container:

```sh
docker compose run --rm --no-deps app php artisan <cmd>
docker compose run --rm --no-deps app composer <cmd>
```

or use the Makefile: `make test`, `make fresh`, `make lint`, `make shell`.

Node runs on the host (`npm run build`, `npm run dev`, `npx playwright test`).

The app is at http://localhost:8089 (`APP_PORT` in `.env`).

The database defaults to **MySQL on the host** (`DB_HOST=host.docker.internal`),
not a container — so a local MySQL must be running. To use the bundled one
instead, set `DB_HOST=mysql` / `DB_USERNAME=crm` / `DB_PASSWORD=secret` and start
it with `docker compose --profile docker-db up -d`. Redis is always a container
(host port 6381).

Do **not** install Laravel Boost or a host PHP toolchain — the Docker image is
the supported environment.

## Conventions that matter here

- **Every CRUD model uses `App\Models\Concerns\Auditable`.** It bundles soft
  deletes, the `created_by`/`updated_by`/`deleted_by` stamps and the activity
  log. A new CRUD model should use it, and its migration needs `softDeletes()`
  plus the three nullable blame foreign keys.
- **Authorization goes through a policy** extending `App\Policies\CrmPolicy`,
  with permissions named `<resource>.<action>`. Never check role names in a
  controller or a Vue template — check a permission.
- **`Model::shouldBeStrict()` is on outside production.** Lazy loading and
  reads of unselected attributes throw. Two consequences:
  - eager-load what a resource renders, and
  - when a list endpoint selects narrow columns (`company:id,name`), the nested
    resource must be a `*SummaryResource`, not the full one.
- **Never cache Eloquent models.** `CrmCache` entries must be fully rendered
  arrays — use `->response()->getData(true)['data']`, not `->resolve()`, which
  leaves nested resources unresolved and mis-shapes them on the way back.
- **Any write that affects a cached read must invalidate it.** Attaching
  `#[ObservedBy(CrmCacheObserver::class)]` to the model handles this.
- **Controllers `refresh()` a model after create** so database defaults appear
  in the response.
- Validation lives in Form Requests; business logic that spans tables lives in
  a service (see `DealService::moveToStage`).

## Frontend

**Use the component kit in `resources/js/components/catalyst/`** — import from
`@/components/catalyst`. It is original Vue styled after Tailwind's Catalyst
(zinc palette, ring borders, Heroicons); do not reach for raw Tailwind classes
where a kit component exists, and keep both themes working (`dark:` on every
colour). Catalyst itself is paid and React-only — none of its source is here.

Two gotchas that already bit once:
- A dialog panel must be positioned (`relative`). The backdrop is `fixed`, and
  positioned elements paint above static ones, so a static panel renders *under*
  the blur.
- Anything inside a `<label>` becomes part of the control's accessible name.
  Keep markers like the required asterisk outside it.

Vue 3 `<script setup>`, Vue Router, TanStack Vue Query for all server state.
Query keys are hierarchical (`['contacts']`, `['contacts', id]`) so a mutation
can invalidate `['contacts']` and refresh every page and filter. List views use
`useResourceList`, which maps search/sort/filter/pagination onto the API's
spatie/query-builder contract.

Every list and form needs loading, empty and error states, and every mutation
needs a toast or inline validation.

## AI assistant

`app/Services/Ai/` — `OpenAiClient` (Responses API transport), `CrmToolkit`
(the read-only tool surface), `CrmAssistant` (the tool loop).

Non-negotiables when touching it:
- **Every tool checks permissions in PHP against the calling user.** Never rely
  on the prompt to enforce access, and never add a tool that writes without
  explicit sign-off.
- Free text from records goes through `untrusted()` so it arrives fenced. The
  system prompt treats fenced content as data.
- Tool output is a compact projection, never a model — it bounds both tokens
  and what leaves the database. Respect `ai.max_rows_per_tool`.
- The provider's error text goes to the log, never to the client.

Tests fake the API with `Http::fake()`; no key is needed to run them.

## Tests

- `make test` — Pest, against its own `crm_testing` MySQL schema.
  `tests/TestCase.php` sends an `Origin` header because Sanctum decides
  statefulness from it. Rate-limiter buckets live in the cache, so tests that
  hit auth endpoints call `Cache::flush()` in `beforeEach`.
- `make test-e2e` — Playwright against the running stack, using the installed
  Chrome (`channel: 'chrome'`), so no browser download is required.

Write the failing test first.
