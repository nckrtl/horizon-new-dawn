# Horizon New Dawn React adjustments

These gates capture recurring corrections from prior implementation, remediation, and browser sessions.

## Keep React client-only

- Preserve the existing `createRoot` browser mount. Do not add React SSR, hydration, an SSR entry point, or a Node server/runtime requirement.
- Laravel and Inertia own the initial response, route handling, server state, structured page props, validation, redirects, and flash data. React renders those props and initiates server-backed behavior through Inertia.
- Keep the package's published-asset installation model: consuming Laravel applications must not need Node.js, a frontend build, or a long-running JavaScript rendering process.
- Reject React Server Components, Server Actions, `React.cache`, server loaders, `renderToString`, `renderToPipeableStream`, and `hydrateRoot` as architectural mismatches.
- Do not enable Inertia SSR unless the project explicitly adopts a different deployment and rendering architecture.

## Keep data ownership coherent

- Read Horizon repositories and contracts in PHP. Send structured page props through Inertia; do not call Horizon browser API routes from React, fetch page data in effects, or create a second frontend data layer.
- Use route-specific partial reloads, polling props, deferred data, and merge metadata. A smaller request is only correct if every dependent UI value remains coherent.
- Preserve query state such as tabs, sorts, filters, cursors, and queue metric views. Encode queue and tag identifiers and resolve every link through the package Horizon route helper.
- Keep tab ownership narrow: changing one panel must not unmount unrelated page sections or discard their state.

## Live collections need correctness before speed

- Distinguish server items, currently visible items, and newly arrived items. Reconcile them when auto-load mode or route scope changes.
- Use stable job/batch identifiers, preserve order, prevent duplicate merges, and handle empty responses and removed retained history.
- Cancel or serialize overlapping polls. Verify behavior after several intervals and while navigating between scopes.
- Bound server scans and communicate incomplete retained history instead of presenting lower-bound data as complete.
- Row-level navigation must ignore events from nested interactive targets, including disabled controls that would otherwise click through to the row.

## Optimize the real workload

- Exercise the guarded demo with completed, failed, pending, silenced, batch, monitored-tag, queue, and metric data. Populate or rebuild according to the requested lifecycle; do not duplicate an already-running slow workload blindly.
- Review long UUIDs, queue names, stack traces, charts with multiple points, and tables receiving new entries.
- Measure full-page payloads, partial reloads, common bundle chunks, render work, and layout behavior before applying micro-optimizations.

## Preserve the interface contract

- Performance changes must retain supplied design fidelity, shadcn/Base UI composition, semantic tokens, accessible states, and the established empty/loading presentation.
- A faster stale screen is a regression. Keep processing indicators, navigation counts, action eligibility, and mutation feedback synchronized with server truth.
- Browser state should remain deep-linkable where the project already exposes URL-backed tabs, filters, sorts, and cursors.

## Prove published behavior

- Build package assets, publish them into the exact consumer, and verify that the browser loaded the new hashes.
- When no other target is named, use `/Users/nckrtl/apps/horizon-demo` and `https://horizon-demo.nmbp/horizon/`.
- Inspect console and network results on desktop and mobile with representative data. For polling or cache work, repeat route checks across at least one interval.
- Source tests, TypeScript checks, and Vite output support the conclusion but do not prove the symlinked consumer.
