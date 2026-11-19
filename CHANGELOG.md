# Changelog

All notable changes to Horizon New Dawn will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Treat a retained-job index that another request is already synchronizing as warming instead of a generic runtime failure, and stop reporting that expected contention to the application's exception handler.

## [0.1.5] - 2026-07-30

### Fixed

- Stop tab selection from deliberately moving the document viewport, scrolling the desktop tab strip, or following queue activity anchors; route-backed tabs preserve scroll, including mobile select-close handling.
- Size each active job, queue, batch, metrics, and monitoring tab panel to its own content instead of retaining another tab's height.

## [0.1.4] - 2026-07-30

### Added

- Added page-by-page retained batch scans and queued bulk operations that walk complete history without a configurable total ceiling, with fail-closed handling for malformed repository pages and incomplete-result states for partial scans.
- Added production deployment guidance for asynchronous maintenance queues, cached configuration and routes, multi-node asset publication, long-lived process restarts, CSP nonces, Horizon snapshots, and rollback.
- Added a request-reconciled Redis job index for case-insensitive partial-class or exact retained-ID search and exact job class, queue, connection, pending-state, and failed-tag queries without a background indexing process or persistent search-specific index.
- Bound job-class catalog pipelines, intent-loaded exact-filter options, and partial-search fan-out; repair exact job, queue, connection, and pending-target partitions; and report overly broad catalogs or searches instead of exhausting a PHP worker or silently truncating results.
- Added client-side job sorting across every row currently loaded by infinite scrolling.
- Added an optional `horizon-new-dawn:warm-retained-jobs` Artisan command to prebuild retained-job Redis indexes during production adoption.
- Added database-backed full-source batch search, status and creation filters, status counts, and stable server-side sorting before 50-row pagination.
- Added an optional batch destination metadata migration and warm command for exact queue and connection attribution.
- Added real-service retained-job compatibility coverage for standalone Redis 6.2 and 7 and Valkey 8 with PhpRedis, Predis 3, and a dedicated Predis 2 run.

### Changed

- Dispatch potentially long-running bulk mutations to a configured asynchronous queue and reject synchronous or null queue drivers.
- Let queued bulk operations inherit the consuming Horizon supervisor's timeout instead of imposing a package-level timeout.
- Require serialized, resumable releases to be started manually with an explicit semantic version after the complete compatibility and test matrix passes, with third-party actions pinned to immutable revisions.
- Exclude the package's client-only route tree from a host application's Inertia SSR gateway without disabling SSR for unrelated host routes when Horizon owns the root path.
- Refresh complete but stale published frontend builds during a normal install, retain the prior asset generation for in-flight clients and rollback, prune older generations, and clean abandoned staging directories safely.
- Apply every visible paginated job search, exact filter, and database-batch query control to the complete queryable source, and omit controls that cannot be supported exactly.
- Preserve Horizon's retained pagination order while re-sorting the combined loaded job rows whenever infinite scrolling appends a page.
- Remove loaded-page-only filtering and sorting from monitored-tag job tables, retaining Horizon's repository order until exact full-source tag queries are available.
- Capture immutable batch queue and connection snapshots once, inferring omitted historical defaults from the configuration active at first discovery and labeling that provenance in the interface.
- Hide destination-dependent batch filters, summaries, and actions until the metadata migration is available while keeping source-column SQL queries enabled.
- Resolve expensive Inertia list props lazily and memoize each requested page so initial, deferred, and scroll requests do not duplicate repository work.
- Load job filter catalogs only on filter hover, focus, or open; keep them out of normal list polling; share one source snapshot across catalogs; reuse immutable metadata across lifecycle transitions; and read unfiltered counts directly from Horizon.
- Keep stale retained-job cleanup off serving requests while exact queries intersect an atomic Horizon source snapshot; expose the warm command for periodic storage hygiene.
- Document retained-job indexing's Redis 6.2+ standalone requirement because reconciliation uses `ZDIFFSTORE`.

### Fixed

- Allow first boot with cached host configuration when the package configuration has not been published yet.
- Preserve package polling, server-side scan-cache, and recent-failure defaults when a cached host configuration predates the package.
- Apply queued bulk-operation safeguards to Horizon's preserved batch retry API.
- Boot safely with custom Inertia SSR gateways that do not support path exclusions.
- Bundle toast styles instead of injecting an unnonced runtime stylesheet under strict content security policies.
- Keep forward-only infinite-scroll continuation cursors out of reloadable browser URLs.
- Make automatic refresh authoritative at the first page so removed or expired Horizon records disappear instead of accumulating through additive merges.
- Follow Horizon's page-one refresh model after infinite scrolling loads history: poll only freshness and summary props, keep the loaded window stable, and return to the current first page through the “new entries” action.
- Remove package-owned infinite-scroll loading and row-reconciliation fallbacks while Inertia owns the rendered collection, boundary detection, reset, and loading lifecycle.
- Keep overlapping native infinite-scroll requests cancellable instead of rejecting them after Inertia has entered its loading state.
- Keep the Jobs filter affordance stable while its optional catalog loads or is unavailable, including a path to clear active filters.
- Keep intent-loaded job-filter requests isolated from same-page searches and polling while cancelling them when their page unmounts.
- Start retained-job catalog scans with the client-specific cursor required by PhpRedis and Predis.
- Reconcile source growth incrementally without deleting valid indexed work, and fail closed under sustained churn without rebuilding on ordinary drift.
- Let fresh requests reuse an exact retained-job index without taking its synchronization lock or rotating its read-fencing revision.
- Make forced retained-job maintenance fail honestly on lock contention and prune empty facet catalogs even when stale job metadata has already expired.
- Keep debounced job searches and rapid multi-filter changes in one synchronized draft so a delayed request cannot undo a newer selection.
- Keep sidebar navigation counters semantically muted across normal, active, and hover states.
- Rebuild incomplete retained-job projection and catalog indexes under the synchronization lock before failing closed.
- Trim expired Horizon source references before retained-index synchronization so expired job hashes cannot cause a permanent rebuild loop.
- Fence filtered retained-index reads across concurrent synchronization and keep facet catalog reads non-mutating.
- Snapshot mutable ready, reserved, and delayed queue structures atomically while applying pending-state filters, renew the short-lived snapshot during chunked reads, and fail closed if it expires.
- Classify jobs explicitly made available by New Dawn as Ready in both filters and displayed state while retaining Released for naturally elapsed schedules.
- Apply queue-activity job sorting to every currently loaded row, and expose local batch-detail job sorting only when the full non-paginated list is available.
- Scan only the two Redis hash fields needed to determine bulk failed-job retryability instead of hydrating full payload, exception, and context records.
- Keep global failed-job retry and clear actions available regardless of Horizon's unfiltered retained count, dispatching them as bounded asynchronous bulk operations instead of rejecting large scopes before dispatch.
- Prevent retained batch scans from reporting incomplete lower bounds as exact totals or enabling destructive actions.
- Avoid instantiating arbitrary classes while decoding serialized queued-job payloads.
- Propagate Laravel's Vite CSP nonce to package scripts and Inertia's runtime-injected styles.
- Pin the patched `brace-expansion` transitive release used by the frontend build toolchain.
- Bind batch continuation cursors to the complete active filter and sort signature so stale cursors cannot skip rows after a query change.

## [0.1.3] - 2026-07-24

### Added

- Added storage-agnostic batch browsing, filtering, counts, queue summaries, and actions through Laravel's configured batch repository, including DynamoDB-backed batch metadata.
- Added accessible collapsible JSON payloads and exception context with copy controls.

### Changed

- Show `Pausing` and `Continuing` states immediately for Horizon instances and supervisors, mark managed supervisors while an instance pauses, and rely on normal automatic refresh or temporary polling when automatic refresh is disabled.
- Populate batch queue and connection filters from the complete retained batch history, refresh metrics automatically, and present runtimes, waits, and backoff values consistently.

### Fixed

- Keep JSON expand and collapse controls anchored in the viewport while their payload content changes height.
- Keep Inertia infinite-scroll results, URL-backed filters, and pagination cursors stable while polling or when retained Horizon records disappear.
- Make bulk job and batch clearing and retries scan the complete retained source safely without skipping entries, duplicating work, or acting on ineligible records.
- Guard monitoring mutations from reserved Horizon keys, preserve correct failed and pending job links and states, and support slash-bearing supervisor names and metric identifiers.

## [0.1.2] - 2026-07-23

### Added

- Added live autoscaling indicators to supervisor rows, including predicted scale-up, scale-down, and idle states with complete animation cycles across target changes.
- Added an action to make an individual delayed job available to workers immediately while preserving its original scheduling metadata.

### Changed

- Distinguish ready, delayed, released, and reserved pending jobs using their effective schedule, and update their displayed state when a scheduled release time passes.
- Order pending jobs by effective availability across the complete retained set, and paginate completed, silenced, failed, and tagged failed jobs consistently from oldest to newest.

### Fixed

- Prevent retry attempts from inheriting stale scheduling state from the original failed job.
- Refresh immediately when automatic loading is enabled and reconcile polling resets without losing loaded infinite-scroll results or query state.
- Finish the active autoscaling indicator cycle before changing direction or returning to its neutral state.

## [0.1.1] - 2026-07-23

### Added

- Added a compatibility matrix covering PHP 8.3 through 8.5, Laravel 12.38 through 13, and Horizon 5.46 through the latest compatible 5.x release.
- Added a Redis-backed smoke test that starts a real Horizon supervisor and verifies both successful and failed job processing.

### Changed

- Set the supported runtime floors to PHP 8.3, Laravel 12.38, and Horizon 5.46, and removed end-of-life Laravel 11 from the supported matrix.
- Hide unsupported queue pause controls on Laravel 12.38 through 12.40.1 while keeping retry and clear actions available.
- Preserve queue-detail URLs while loading additional activity rows.
- Document why each version floor exists and why Redis Cluster is not yet supported.

### Fixed

- Backfill the worker option expected by newer Laravel releases when Horizon 5.46 does not register it, allowing real Horizon workers to boot normally.
- Calculate dashboard queue runtime and throughput leaders from retained metric snapshots instead of relying on repository methods unavailable in Horizon 5.46.

[Unreleased]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.5...HEAD
[0.1.5]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.4...0.1.5
[0.1.4]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.0...0.1.1
