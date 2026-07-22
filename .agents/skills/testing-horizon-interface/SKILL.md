---
name: testing-horizon-interface
description: Use when adding, changing, fixing, or reviewing any user-visible Horizon New Dawn feature, page, interaction, responsive state, browser behavior, or UI test coverage.
---

# Testing the Horizon Interface

## Core contract

Horizon New Dawn's product boundary is the rendered consumer UI. Every user-visible feature requires at least one focused Pest Browser acceptance test that proves its user outcome through Laravel routing, Inertia, compiled assets, and React.

Lower-level tests, route smoke coverage, and manual browser checks support this boundary. None replaces the focused browser test.

**REQUIRED SUB-SKILL:** Use `pest-testing` for Pest syntax and browser APIs.

## Coverage layers

| Layer | Owns |
| --- | --- |
| Unit and feature Pest | Horizon normalization, authorization, validation, actions, routes, and exact Inertia props |
| Vitest | Component states, hooks, client transformations, and edge cases |
| Interface smoke | Every package-owned GET route renders without JavaScript, console, or accessibility errors |
| Focused Pest Browser | One complete user outcome for every visible feature |
| Consumer proof | Published assets, custom paths, Redis, workers, cache intervals, and responsive quality |

## Feature workflow

1. Identify the visible outcome and crossed layers.
2. Write the focused browser test first and observe failure.
3. Add lower-level coverage for logic and edge cases; implement the minimal change.
4. Extend `tests/Browser/InterfaceSmokeTest.php` when adding a package-owned GET route.
5. Run the focused test and `composer quality`.
6. Build, publish, and verify the named consumer; default to `https://horizon-demo.nmbp/horizon/`.

The smoke matrix must contain every package-owned GET interface route, including aliases, redirects, optional route states, and deterministic detail fixtures. It proves rendering only. A feature still requires its separate focused interaction test.

## Browser acceptance shape

A feature test must:

- exercise the feature through the rendered UI, not by calling its route directly;
- assert a specific visible result, URL, persisted preference, or backend side effect;
- assert no JavaScript errors or unexpected console logs;
- use stable roles, labels, or `data-test` selectors;
- use deterministic Testbench fixtures and mock only the external Horizon boundary;
- cover mobile, dark mode, accessibility, polling, or custom paths when those are part of the feature.

For mutations, assert feedback and durable state. For polling or deferred behavior, wait for an observable condition instead of sleeping.

For URL-backed tabs, views, filters, sorts, or cursors, use a controlled out-of-order response test: hold a background poll, deferred, or infinite-scroll response; navigate to a different query scope; release the stale response; then assert the URL, selected control, and rendered data remain aligned. Continue through another refresh when recurrence is plausible. An option-shape unit test for `preserveUrl` or cancellation is supporting coverage, not this browser regression.

## Commands

```bash
bun run build
composer test:browser
composer quality
```

## Common mistakes

- Treating manual consumer proof as durable regression coverage.
- Treating the render matrix as feature behavior coverage.
- Adding a GET interface route without extending the complete render matrix.
- Repeating prop assertions instead of testing the user outcome.
- Testing only request options while leaving stale response ordering unverified.
- Using a live Redis instance for deterministic package tests.
- Claiming completion from Testbench when publishing or real Horizon behavior changed.
