# Architecture

Horizon New Dawn is an additive Laravel package that replaces Horizon's browser interface without replacing Horizon itself. Horizon remains responsible for queue workers, Redis storage, metrics, authorization, and its existing API routes.

## Design goals

- Render Horizon data through PHP and Inertia instead of adding a duplicate browser-facing API client.
- Reuse Horizon's contracts and repositories so data semantics stay aligned with the installed Horizon version.
- Preserve Horizon's route path, authentication middleware, authorization callback, and API controllers.
- Keep the package isolated from the host application's Inertia root view and frontend build.
- Use package-owned controllers and routes for the complete browser interface while retaining Horizon's API contract.

## Request lifecycle

During application booting, `HorizonNewDawnServiceProvider` registers concrete routes under Horizon's configured path and domain:

```text
GET  /horizon/dashboard
GET  /horizon/jobs/{type}/{job?}
GET  /horizon/failed/{job?}
GET  /horizon/monitoring/{tag?}/{status?}
GET  /horizon/metrics -> /horizon/metrics/jobs
GET  /horizon/metrics/{type}/{slug?}
GET  /horizon/batches/{batch?}
POST and DELETE mutation routes
```

The route group uses Horizon's middleware group and `Authenticate` middleware, followed by the package's Inertia middleware. It is registered before Horizon's browser catch-all, so concrete New Dawn routes own supported screens without shadowing Horizon API routes.

Horizon still registers its catch-all home route. The provider binds Horizon's home controller to a package controller that returns `404`, preventing unsupported paths from falling through to the bundled Vue interface. Horizon API routes do not receive New Dawn's Inertia middleware. The monitoring API controller is intentionally wrapped so its mutation endpoints share New Dawn's reserved-key and currently-monitored tag guard while retaining Horizon's existing read responses and route contract.

`HandleInertiaRequests` selects the package root view and shares the Horizon base URL, runtime status, host maintenance state, polling interval, and flash messages with every New Dawn page.

## Backend data flow

Thin controllers delegate Horizon reads and mutations to feature services and actions. Those classes depend on Horizon contracts where Horizon already exposes the required behavior:

```text
Horizon repositories and calculators
    -> Dashboard, Jobs, FailedJobs, Monitoring, Metrics, and Batches services
    -> Spatie Laravel Data objects
    -> Inertia page props
    -> React pages
```

Large job collections use Inertia scroll props and Horizon cursors. Batch search and pagination use Laravel's configured `BatchRepository`, with literal case-insensitive matching and a descending batch identifier cursor. Dashboard and navigation batch totals share a repository overview cached for one interface polling interval, avoiding duplicate full scans while retaining DynamoDB compatibility.

Repository failures are caught at data boundaries and converted into explicit unavailable states. List previews omit sensitive payload and exception data. Detail pages expose normalized fields deliberately, never raw repository objects or Redis internals.

## Frontend isolation

`HandleInertiaRequests` sets `horizon-new-dawn::app` as the root view only for package routes. This avoids changing the host application's own Inertia middleware or root template.

The React entry point resolves package pages from `resources/js/pages`. Every page opts into the same Inertia persistent layout, so sidebar and automatic-loading state survive client-side navigation. Theme preference is stored independently and supports light, dark, and system modes.

UI is composed from shadcn/ui primitives. Package-specific components are limited to Horizon data presentation where shadcn/ui has no domain equivalent, such as metrics charts, sortable Horizon tables, JSON payload views, and compact circular batch progress.

Wayfinder generates typed definitions from package routes. `resolveHorizonRoute` rebases those definitions against the shared Horizon base URL while preserving route methods, parameters, query strings, and hashes. This keeps all links and mutations correct for relative custom paths and absolute Horizon domain URLs. Generated files under `resources/js/generated` are never edited manually.

Lists sort loaded rows in the browser and use Inertia partial reloads or scroll merging for additional pages. Optional automatic loading is coordinated by the persistent layout. PHP remains the only layer that reads Horizon repositories.

## Asset delivery

The package ships a committed Vite production build in `dist/build`. Consumers publish it with:

```bash
php artisan horizon-new-dawn:install
```

The installer copies configuration and compiled assets into the host application. `AssetManifest` reads the published Vite manifest and generates hashed asset URLs. A consuming application does not need Node.js or changes to its own Vite configuration.

## Extension boundaries

Use Horizon contracts when a feature already exists in Horizon. Add a focused package service or action when New Dawn needs normalization, repository-backed aggregation, or a mutation Horizon does not expose through a suitable contract.

New routes must:

- remain under the configured Horizon path unless the feature has a clear reason to live elsewhere;
- use Horizon's authorization boundary;
- return structured Laravel Data objects to Inertia;
- avoid exposing raw job payloads, exception bodies, or Redis details;
- avoid modifying or shadowing Horizon API routes unless the change is intentional and documented.

## Development environment

Orchestra Testbench verifies package behavior in isolation. The Workbench application provides deterministic successful and failing jobs for live dashboard development.

The primary verification boundaries are:

- feature tests for route ownership, middleware isolation, authorization, Inertia props, actions, and asset publishing;
- unit tests for Horizon repository normalization, cursors, search escaping, and failure handling;
- React tests for route rebasing, components, sorting, persistent layout, themes, and automatic loading;
- a production Vite build whose manifest references only committed files;
- responsive light/dark browser validation through an Orbit-managed Laravel application.
