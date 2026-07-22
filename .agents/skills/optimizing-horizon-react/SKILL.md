---
name: optimizing-horizon-react
description: Use when writing, reviewing, or refactoring Horizon New Dawn's client-only React code for performance or state correctness, especially Inertia polling, infinite lists, filters, page props, rerenders, local storage, charts, and bundle behavior.
---

# Optimizing Horizon React

## Overview

Apply portable React performance practices inside Horizon New Dawn's Laravel/Inertia architecture. React is a browser-only presentation and interaction layer in this package. Optimize the largest proven cost first while preserving Laravel and Inertia's ownership of the server lifecycle, routing, page data, navigation, polling transport, prop merging, and history state.

## Runtime boundary

- Keep the existing client mount with `createRoot`. Do not introduce React server-side rendering, hydration, a Node rendering process, or an SSR entry point.
- Laravel and Inertia own the initial document and page response, controllers, middleware, authentication, authorization, validation, redirects, flash data, and structured page props.
- Inertia owns visits, forms, server-data refreshes, deferred and partial props, polling, prefetching, merged props, and history state.
- React owns browser rendering, local presentation state, accessible interactions, and calls into `@inertiajs/react` for server-backed behavior.
- Never add React Server Components, Server Actions, server data loaders, JavaScript server endpoints, `renderToString`, `renderToPipeableStream`, `hydrateRoot`, or a custom Node server.
- Do not enable Inertia SSR for this package unless the user explicitly changes the architecture.

## Workflow

1. Read [react-client-guidelines.md](references/react-client-guidelines.md) and [project-adjustments.md](references/project-adjustments.md).
2. Classify the concern before editing: server payload/query cost, network or polling behavior, bundle loading, render frequency, expensive computation, DOM/layout work, or a low-impact JavaScript micro-optimization.
3. Gather evidence at the affected boundary. Use route payloads and network timing for Inertia work, bundle output for loading claims, React/browser profiling for rerenders, and representative list/chart sizes for rendering claims.
4. Preserve the runtime boundary. PHP reads Horizon repositories and sends structured Inertia props; the browser-only React client presents them and initiates mutations through Inertia.
5. Write a failing test first for non-visual behavior changes. Purely visual changes do not add or modify automated tests unless the user explicitly requests them; validate those in the browser.
6. Implement the smallest measured improvement, then verify focused tests, typecheck, build, and the exact published consumer. Exercise at least one polling/cache interval when refresh or merge behavior changes.

## Project substitutions

- Use Inertia `Link`, `Form`, `useForm`, `router`, `usePoll`, deferred props, partial reloads, prefetching, `InfiniteScroll`, and prop merging instead of SWR, Axios, client-side loader effects, or a parallel browser API.
- Use `useHttp` only when an intentional Laravel JSON endpoint already belongs to the product contract. Do not introduce one merely to move page data fetching into React.
- Use Vite-native static imports, `import()`, and `import.meta.glob` instead of `next/dynamic` or Next.js package-import configuration.
- Keep standard typed `lucide-react` imports unless bundle evidence proves a supported deep import is better.
- Use Laravel controllers, authorization, cache, and data objects instead of React Server Components, Server Actions, `React.cache`, or Next.js `after()` patterns.

## Guardrails

- Do not add `better-all`, SWR, an LRU package, or another dependency for a guideline example without explicit approval.
- Do not add memoization speculatively. `useMemo` and `memo` must protect measurable work or a required stable identity.
- Do not combine loops, cache property access, or replace array methods merely to satisfy a micro-optimization checklist.
- Do not hide stale-state, URL, merge, or polling correctness defects behind memoization.
- Do not claim a package optimization from source or build output alone. Publish it and verify the named consumer with representative data.

## Output

Lead with the measured bottleneck or correctness issue. Explain which project-native mechanism applies, the evidence before and after, and any tradeoff. If reviewing, separate actionable findings from low-value opportunities and explicitly reject inapplicable Next.js guidance.
