# Changelog

All notable changes to Horizon New Dawn will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.1.3]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/nckrtl/horizon-new-dawn/compare/0.1.0...0.1.1
