---
name: checking-horizon-compatibility
description: Verify Horizon New Dawn features across its declared PHP, Laravel, Horizon, Inertia, and browser compatibility matrix. Use whenever adding or changing code that calls Laravel or Horizon APIs, uses PHP language/library features, adds routes or mutations, changes page props, changes Composer constraints, or claims support for another framework version.
---

# Checking Horizon Compatibility

Treat compatibility as a feature boundary, not merely a Composer resolution result. Prove that unsupported operations cannot execute and that supported versions retain the complete experience.

## Establish the matrix

Read `composer.json`, `README.md`, `.github/workflows/tests.yml`, and the installed dependency metadata. Record:

- minimum PHP and every supported Laravel major;
- the minimum accepted Horizon release and the newest resolvable Horizon 5.x release;
- frontend adapter requirements that can narrow Laravel support;
- development dependencies that may accidentally force a newer Laravel release.

Never infer feature availability from Horizon's broad Illuminate constraint.

## Audit every new feature

1. List every external symbol used: PHP syntax/functions, Laravel methods/contracts, Horizon contracts/repositories, Inertia APIs, and generated route helpers.
2. Search version-specific official documentation first. When introduction timing is unclear, compare tagged upstream source and release history. Record the first release containing every required method and its runtime behavior.
3. Search the whole repository for each symbol. Include controllers, actions, data collectors, middleware, console commands, frontend props, routes, tests, and build tooling.
4. Classify the result:
   - **Universal:** present throughout the declared matrix.
   - **Gateable:** optional without corrupting data or changing the core package contract.
   - **Matrix-breaking:** fundamental and therefore requires raising a dependency floor.

## Implement gateable features

Prefer capability detection such as `method_exists()` over `version_compare()`. Detect the complete method set, not one convenient symbol.

Carry one server-derived capability through every boundary:

1. backend collectors avoid unsupported reads;
2. actions reject unsupported direct calls;
3. mutation endpoints return `404` or another deliberate unavailable response;
4. shared Inertia data exposes the capability;
5. React hides only the unavailable controls while preserving unrelated actions.

Hidden UI alone is never a compatibility implementation. Do not emulate framework internals by writing undocumented cache keys or Redis structures.

Example: queue pausing requires `QueueManager::pause()`, `pauseFor()`, `resume()`, and `isPaused()`. Horizon does not polyfill these methods, and workers on older Laravel versions do not honor the pause state.

## Prove the contract

Write the unsupported-path test first and observe it fail. At minimum verify:

- an unsupported runtime never invokes missing methods;
- disabled mutation routes cannot execute;
- the shared capability is false;
- supported runtimes preserve reads, writes, authorization, validation, and UI controls;
- lowest supported PHP executes focused paths that use changed language/library features.

Run the full package suite on the lowest supported Laravel/PHP pair, each intermediate Laravel major, and the newest Laravel/PHP pair. Test both the minimum declared Horizon release and newest resolvable Horizon 5.x release when their code paths differ. Add pre/post boundary jobs for APIs introduced in a minor release. Remove or replace development-only packages that prevent Composer from resolving a boundary; do not let them silently select a newer framework. Assert the installed PHP, Laravel, and Horizon versions in every compatibility job.

Run formatting, static analysis, frontend tests, type checking, and the production build on the primary runtime. For worker behavior, verify with real Redis/Horizon workers in a consuming application when the feature affects reservation or process control.

Keep `composer.json`, lockfile, README, CI, and feature documentation aligned with the proven result.

## Stop conditions

Do not claim compatibility when only the current lockfile passes, a test matrix silently resolves the wrong Laravel major, a dev dependency narrows the matrix, PHP analysis targets only the newest version, or an unsupported backend call remains reachable.
