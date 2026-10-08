# FlowPilot

FlowPilot is a multi-tenant SaaS for business operations and workflow automation: configurable workflows, approvals, projects and tasks, issues, inventory, reports, notifications, and an AI operations brief. Workflow automation is the centrepiece; everything else feeds it or is driven by it. Build it as a product that is shown to clients, not as a demo.

## Stack and versions

- PHP 8.5, Laravel 13, PostgreSQL 16 (production), Redis 8, Reverb for realtime.
- Vue 3 + TypeScript + Inertia 3, Tailwind CSS 4, Vite 8 via `vite-plus`, Wayfinder for typed routes, reka-ui (shadcn-vue) primitives.
- Pest 5, Larastan (level 7), Pint.
- Two deliberate pins, do not "upgrade" them without checking the reason still holds:
    - `typescript` stays on 6.x. TypeScript 7 is the native compiler and does not ship the JS compiler `vue-tsc` loads.
    - `vite-plus` stays on exactly `0.3.0` (what the Laravel starter kit targets). Later versions require aliasing `vite` to their fork, which conflicts with the Laravel, Inertia and Tailwind Vite plugins.

## Databases: PostgreSQL in production, SQLite in tests

- The running application always uses the PostgreSQL database configured in `.env`. The automated test suite runs on in-memory SQLite (`phpunit.xml`).
- Every migration and query must therefore work on both. Guard PostgreSQL-only DDL (check constraints, partial indexes) with `Schema::getConnection()->getDriverName() === 'pgsql'`. Do not write PostgreSQL-only SQL in application queries; use the query builder (`whereLike`, `whereJsonContains`, etc.).
- The configured database is the real one. Never run `migrate:fresh`, `migrate:refresh`, `db:wipe` or ad-hoc destructive SQL against it without the user asking for it.
- Money is stored as integer minor units (`*_amount` columns, e.g. cents) next to an ISO currency code. Never use floats for money.
- Tenant-owned tables use UUIDv7 primary keys (`HasUuids`). `users` keeps its integer key.
- Store timestamps in UTC. Convert to the organization/user timezone only when displaying.

## Multi-tenancy (the rule that must never be broken)

- Shared database, shared schema. Every tenant-owned table has a non-null `organization_id`.
- Tenant-owned models use the `App\Models\Concerns\BelongsToOrganization` trait. It applies a global scope from the current organization and fills `organization_id` on create. A tenant model queried with no current organization throws rather than returning every tenant's rows.
- The current organization comes from the URL (`/app/{organization}/…`) and is resolved by middleware that also verifies the user's active membership. Never read an organization id from request input.
- Work outside a request (jobs, commands, seeders, listeners) must run inside `Tenancy::run($organization, fn () => …)`.
- Crossing tenants on purpose (platform admin, scheduled sweeps) must be explicit: `Model::withoutOrganizationScope()`.
- Validation rules that reference other records must be tenant-scoped (`Rule::exists(...)->where('organization_id', …)`), so an id from another organization is rejected.
- Every tenant-owned feature needs a test proving a member of organization A cannot read or change organization B's records (expect 404, not 403, so existence is not leaked).

## Authorization

- Permissions are the `App\Enums\Permission` enum (`projects.view`, `approvals.approve`, …). Roles are sets of permissions; memberships point at a role. Never check a role name in code — check a permission.
- Authorization is enforced on the server with policies and `Gate`. The permissions sent to the frontend only decide what to show.
- Plan limits and features go through the entitlements service. Never write `if ($plan === 'business')`.
- Platform administration is separate from organizations (`users.is_platform_admin`), with its own routes and middleware.

## Laravel conventions

- Thin controllers: validate with a Form Request, authorize with a policy, delegate real work to an action in `app/Actions/{Domain}` (one public `handle()` method), return an Inertia response or redirect.
- Domain enums live in `app/Enums` as string-backed enums with a `label()` method. No magic strings for statuses, priorities or types.
- Record activity for meaningful changes through the activity logger from inside the action that performs the change.
- Slow or external work (mail, notifications, exports, AI calls, webhooks, workflow steps) goes on the queue. Jobs that act for a tenant carry the organization and restore the tenant context.
- Avoid N+1 queries: eager load what a page renders. The database may be a network hop away, so each query is expensive; batch and aggregate in SQL.
- Workflow definitions are data. Never evaluate user-supplied code or expressions; conditions and actions are a fixed, validated vocabulary.

## Vue and TypeScript conventions

- Pages in `resources/js/pages/{domain}`. Shared FlowPilot components in `resources/js/components`; reka-ui/shadcn primitives in `resources/js/components/ui`. Check for an existing component before writing a new one.
- Use Wayfinder route functions (`@/routes`, `@/actions`), never hard-coded URLs.
- Routes inside an organization get `{organization}` filled from the current page (`setUrlDefaults` in `app.ts`), so call them without it: `members.index()`. Never call them at module scope (e.g. in `defineOptions({ layout: … })`): that runs before the first page exists and is cached across organizations. Set breadcrumbs with `setLayoutProps()` inside `<script setup>` instead.
- Use Inertia's `<Form>` / `useForm` for forms, with labels, inline errors and a processing state.
- Type page props. Do not use `any`. Shared domain types live in `resources/js/types`.
- Server state comes from Inertia props; no global store unless a real need appears.
- Every list or detail screen needs a designed empty state, a loading state (skeletons for deferred props) and an error state.

## Design system

- Tokens live in `resources/css/app.css`. Use the semantic colours, not raw hex or Tailwind palette colours.
- Each accent has one job: `primary` actions, `flow` work in motion, `warning` waiting on someone, `success`/`danger` outcomes, `ai` anything written by the AI.
- Status colours have three tiers: solid (`bg-success`), readable text (`text-success-text`) and soft background (`bg-success-soft`). Never communicate a status by colour alone; pair it with a label or icon.
- Typefaces: Funnel Display (`font-display`) for headings and large figures, Funnel Sans for everything else. Use the `figures` utility for numbers that should align.
- Sentence case everywhere. Buttons say what they do ("Approve request", not "Submit").
- Target WCAG 2.2 AA: keyboard reachable, visible focus, labelled controls, reduced motion respected.

## Testing

- Pest feature tests for behaviour; unit tests for pure logic such as the workflow condition evaluator.
- Cover failure paths: unauthorized role, other tenant, invalid input, duplicate/idempotent submission.
- Use factories. Run the affected tests, Pint and PHPStan before calling work finished; run `npm run types:check` and `npm run check` for frontend changes.

## Dependencies

- Before adding a package: is it needed, does Laravel or an existing dependency already do it, is it first-party or well maintained, and is it compatible with the versions above? Prefer the latest stable release and read its changelog.
