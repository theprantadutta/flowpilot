<p align="center">
  <img src="public/brand/logo.svg" alt="FlowPilot" height="48">
</p>

<p align="center"><strong>Move work forward. Automatically.</strong></p>

<p align="center">
FlowPilot is a multi-tenant SaaS for business operations. It turns repetitive processes into clear, connected workflows, so every request knows where it goes next and who it is waiting on.
</p>

---

## What it does

- **Workflows.** A visual builder where a trigger starts a run and steps carry it along: conditions, branches, approvals, assigning work, creating and updating records, notifications, delays and signed webhooks. Workflows are versioned, published deliberately and recorded step by step on every run. Definitions are data from a fixed vocabulary; nothing user-supplied is ever executed.
- **Triggers.** A task created or completed, an issue reported, stock running low, a purchase request submitted, or a person starting a run by hand.
- **Approvals.** Ask a named member or everyone in a role, with comments, requested changes, due times, reminders and automatic rejection when overdue.
- **Projects, tasks and issues.** Boards, timelines, checklists, dependencies, comments and attachments.
- **Inventory and purchasing.** Items, locations, suppliers, stock movements, low-stock alerts and purchase requests that flow into approvals.
- **Reports.** Charts with table views, filters, an attention dashboard and CSV exports generated in the background.
- **AI operations brief.** A short brief of what needs attention, written from records the reader is allowed to see, with every point linked back to its records.
- **Plans and billing.** Free, Starter, Business and Enterprise plans with limits and features enforced in one place, a trial for new organizations, and upgrade requests the FlowPilot team settles.
- **Platform administration.** Organizations, people, plan changes, trials, suspensions, system health, failed jobs and a cross-organization audit log, for the team that runs FlowPilot.
- **Public site.** Landing page, pricing, FAQ, terms of service and privacy policy, with search and social metadata.

## Tech stack

| Area                    | Choice                                                                                          |
| ----------------------- | ----------------------------------------------------------------------------------------------- |
| Backend                 | PHP 8.5, Laravel 13                                                                             |
| Frontend                | Vue 3, TypeScript, Inertia 3, Tailwind CSS 4, reka-ui (shadcn-vue)                              |
| Build                   | Vite 8 via `vite-plus`, Laravel Wayfinder for typed routes                                      |
| Data                    | PostgreSQL 16 in production, SQLite (in memory) for the test suite                              |
| Queues, cache, sessions | Redis 8                                                                                         |
| AI                      | Freeway gateway (any OpenAI-style chat completions provider behind `App\Support\Ai\AiProvider`) |
| Quality                 | Pest 5, Larastan level 7, Pint, `vp check` (format and lint) and vue-tsc                        |

Two versions are pinned on purpose: `typescript` stays on 6.x (vue-tsc needs the JavaScript compiler that TypeScript 7 no longer ships), and `vite-plus` stays on exactly `0.3.0`, the version the Laravel starter kit targets.

## Requirements

- PHP 8.5 with `pdo_pgsql`, `pdo_sqlite`, `intl` and `mbstring` (Redis is reached through Predis, so no extension is needed)
- Composer 2
- Node.js 24 and npm
- PostgreSQL 16
- Redis 8

## Getting started

```bash
git clone <repository-url> flowpilot
cd flowpilot
composer run setup
```

`composer run setup` installs PHP and Node dependencies, creates `.env` from `.env.example`, generates the application key, runs the migrations and builds the frontend. Fill in `.env` before running it (or run `php artisan migrate` again afterwards):

1. **PostgreSQL**: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
2. **Redis**: `REDIS_HOST`, `REDIS_PORT` and, if set, `REDIS_PASSWORD`.
3. **AI**: `FREEWAY_API_KEY`. Without it the AI brief stays switched off and everything else works.

Then start everything FlowPilot needs locally:

```bash
composer run dev
```

This runs the web server, the default queue worker, the long-running queue worker (report exports and AI briefs), the scheduler and Vite. Open the address in `APP_URL`, sign up, and create your first organization.

### Demo data (development only)

`php artisan db:seed` creates **Northstar Manufacturing**, a manufacturer with a month of realistic history built through FlowPilot's own workflows: three projects, purchase requests at every stage, approvals waiting on people, stock use that has triggered an automatic reorder, issues, and a critical stoppage being escalated. It refuses to run in production, needs `DEMO_PASSWORD` in `.env`, and does nothing if Northstar already exists.

Every demo account signs in with `DEMO_PASSWORD`. They are for development and demos only.

| Person            | Email                           | Role                   |
| ----------------- | ------------------------------- | ---------------------- |
| Daniel Okoro      | `daniel.okoro@northstar.test`   | Owner                  |
| Maya Chen         | `maya.chen@northstar.test`      | Admin                  |
| Marcus Reyes      | `marcus.reyes@northstar.test`   | Manager                |
| Priya Nair        | `priya.nair@northstar.test`     | Finance                |
| Tom Becker        | `tom.becker@northstar.test`     | Procurement            |
| Sam Rivera        | `sam.rivera@northstar.test`     | Operations             |
| Dana Whitfield    | `dana.whitfield@northstar.test` | Employee               |
| FlowPilot Support | `support@flowpilot.test`        | Platform administrator |

The five-minute demo: sign in as Dana and submit a purchase request over $5,000; switch to Marcus and approve it; switch to Priya and approve it; then, as Tom, see the request to order it. Along the way, show the workflow run step by step, the activity log, the reports, the workflow builder and the AI operations brief.

### Platform administration

Platform administrators are granted from the command line only, so nobody can promote themselves through the web app:

```bash
php artisan platform:admin you@example.com           # grant
php artisan platform:admin you@example.com --revoke  # revoke
```

The account must have a verified email address. Administration lives at `/platform` and asks for the password again before opening.

## Configuration

Everything is configured through `.env`; `.env.example` documents each setting. The ones most worth knowing:

| Setting                                                     | Purpose                                                                                                                            |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `APP_URL`                                                   | Public address, used in links, emails, canonical URLs and the sitemap.                                                             |
| `QUEUE_CONNECTION`                                          | `redis` in production. Long jobs use the matching `-long` connection (`redis-long`).                                               |
| `LONG_QUEUE_RETRY_AFTER`                                    | Seconds before a long job is retried; keep it above the longest job timeout.                                                       |
| `FREEWAY_URL`, `FREEWAY_API_KEY`, `FREEWAY_MODEL`           | The AI gateway and model used for the operations brief.                                                                            |
| `FREEWAY_TIMEOUT`                                           | Seconds to wait for the AI provider. 180 or more is recommended.                                                                   |
| `MAIL_MAILER` and `MAIL_*`                                  | How invitations, password resets and notifications are delivered. `log` writes them to the log instead.                            |
| `BILLING_TRIAL_DAYS`, `BILLING_SALES_EMAIL`                 | Length of the trial for new organizations and where Enterprise enquiries go. Plans themselves are defined in `config/billing.php`. |
| `WEBHOOKS_ALLOW_HTTP`                                       | Allow webhook steps to call plain `http://` addresses. Leave `false` in production.                                                |
| `LEGAL_ENTITY`, `LEGAL_CONTACT_EMAIL`, `LEGAL_JURISDICTION` | Who operates the service, as named in the terms of service and privacy policy.                                                     |
| `FLOWPILOT_VERSION`                                         | The release being run, shown under platform administration.                                                                        |
| `TRUSTED_PROXIES`                                           | Reverse proxies whose forwarded headers give the visitor's address and scheme. `*` behind Traefik (set by `compose.yml`).          |
| `SESSION_SECURE_COOKIE`                                     | Secure session cookies. Defaults to on whenever `APP_URL` starts with `https://`.                                                  |
| `CONTENT_SECURITY_POLICY`                                   | The content security policy that only runs FlowPilot's own scripts. On by default; skipped while the Vite dev server runs.         |
| `DEMO_PASSWORD`                                             | Password for the Northstar demo accounts. Development only; leave empty in production.                                             |

## Deploying with Docker

FlowPilot runs in production at **https://flowpilot.pranta.dev**, behind the same Traefik as the other services on the server (Freeway uses the same setup). `compose.yml` starts:

| Service       | What it runs                                                                          |
| ------------- | ------------------------------------------------------------------------------------- |
| `migrate`     | `php artisan migrate --force` once per deploy; everything else waits for it           |
| `app`         | PHP-FPM with the application                                                          |
| `web`         | nginx serving the built assets and passing PHP requests to `app`; Traefik routes here |
| `worker`      | The default queue: notifications, mail, workflow steps and webhooks                   |
| `worker-long` | The `long` queue: report exports and AI briefs                                        |
| `scheduler`   | `schedule:work`: resumes waiting workflow runs, reminders, trial checks, pruning      |

The `app` image (PHP 8.5 with OPcache) also runs the workers, the scheduler and migrations. The `web` image is nginx with the compiled frontend. Uploads, exports, logos and avatars live in the `storage` volume, shared by every container.

### What the server needs

- **Traefik** on the external `proxy` network, with a `websecure` entrypoint and a `letsencrypt` certificate resolver.
- **DNS**: an A record for `flowpilot.pranta.dev` pointing at the server.
- **PostgreSQL and Redis** reachable from the containers. Containers on the `proxy` network are reached by container name (for example `DB_HOST=postgres`, `REDIS_HOST=redis`). Services installed directly on the host are reached as `host.docker.internal`.
- **Freeway** at `https://freeway.pranta.dev`, or by its container name on the `proxy` network.

### First deploy

```bash
git clone <repository-url> flowpilot && cd flowpilot
cp .env.example .env
```

Edit `.env` for production. Beyond the database, Redis, Freeway and mail settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://flowpilot.pranta.dev
SESSION_SECURE_COOKIE=true
DB_HOST=postgres
REDIS_HOST=redis
FLOWPILOT_VERSION=1.0.0
DEMO_PASSWORD=
```

Then generate the application key, start everything, and make yourself a platform administrator once you have signed up:

```bash
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
# put the printed key in .env as APP_KEY, then:
docker compose up -d --build
docker compose exec app php artisan platform:admin you@example.com
```

### Updating

```bash
git pull
docker compose up -d --build
```

`migrate` runs any new migrations before the new `app`, workers and scheduler start, and every container caches its configuration, routes and views on start. Set `FLOWPILOT_VERSION` to tag the images and show the release under platform administration.

### Day to day

```bash
docker compose ps                       # status and health
docker compose logs -f app worker       # logs (everything logs to stdout)
docker compose exec app php artisan …   # any artisan command
```

Back up the PostgreSQL database and the `flowpilot_storage` volume, which holds uploads and exports. The health page under platform administration checks the database, cache, queues, scheduler heartbeat, storage, mail and Freeway.

### Without Docker

Run PHP-FPM or another PHP server for the application, plus these processes:

```bash
php artisan queue:work redis                                   # notifications, mail, workflow steps, webhooks
php artisan queue:work redis-long --queue=long --timeout=960   # report exports and AI briefs
php artisan schedule:work                                      # or a cron entry running schedule:run every minute
```

Build the frontend with `npm run build`, cache configuration with `php artisan optimize`, and run `php artisan migrate --force` on each release.

## Development

### Tests and checks

```bash
php artisan test --compact        # Pest feature and unit tests (in-memory SQLite)
vendor/bin/pint                   # PHP formatting
vendor/bin/phpstan analyse        # static analysis, level 7
npm run check                     # frontend formatting and lint
npm run types:check               # TypeScript and Vue type checks
```

The test suite runs on in-memory SQLite while the application runs on PostgreSQL, so every migration and query must work on both. PostgreSQL-only DDL is guarded by the driver name, and queries go through the query builder.

### Conventions

- **Multi-tenancy.** Shared database, shared schema. Every tenant-owned table has a non-null `organization_id`, and tenant models use the `BelongsToOrganization` trait, whose global scope throws when no organization is set rather than returning every tenant's rows. The organization comes from the URL (`/app/{organization}/…`) and the member's active membership, never from request input. Jobs and commands run inside `Tenancy::run($organization, …)`; crossing tenants on purpose is spelled `withoutOrganizationScope()`.
- **Authorization.** Permissions, not role names, are checked on the server through policies. Plan features and limits go through `App\Support\Billing\Entitlements`.
- **Structure.** Thin controllers that validate with a form request, authorize with a policy and hand the work to an action in `app/Actions/{Domain}`. Domain enums live in `app/Enums`. Meaningful changes are recorded in the activity log from inside the action.
- **Money and time.** Money is stored as integer minor units next to an ISO currency code. Timestamps are stored in UTC and shown in the organization's timezone.
- **Frontend.** Pages live in `resources/js/pages/{domain}`, shared components in `resources/js/components`, and routes are called through Wayfinder (`@/routes`, `@/actions`). Colors come from the semantic tokens in `resources/css/app.css`.

### Project layout

```
app/
  Actions/          one class per operation, grouped by domain
  Enums/            statuses, roles, permissions, plans
  Http/             controllers, form requests, middleware
  Models/           Eloquent models (tenant-owned ones use BelongsToOrganization)
  Support/          tenancy, billing, reports, AI, activity, platform services
  Workflows/        triggers, step handlers, conditions and the run engine
config/billing.php  plans, prices, limits and features
database/seeders/   the Northstar Manufacturing demo
docker/             nginx, PHP and entrypoint configuration for the images
resources/js/       Vue pages, components, layouts and types
resources/legal/    terms of service and privacy policy
routes/             web, tenant (/app/{organization}), platform and console routes
tests/              Pest feature and unit tests
```

## Roadmap

- **Realtime updates.** Live notifications, approval counts, workflow run progress and AI briefs pushed to the browser with Laravel Reverb, replacing today's polling. Broadcasting is switched off until then (`BROADCAST_CONNECTION=null`).

## License

FlowPilot is open-source software licensed under the [MIT license](LICENSE). Copyright (c) 2026 Pranta Dutta.
