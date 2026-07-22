# React client guidelines

Adapted for Horizon New Dawn from Vercel Labs' MIT-licensed React Best Practices at commit `4559f18a20c1691c744b4395194290db6a0df5e9`:

- https://github.com/vercel-labs/agent-skills/tree/4559f18a20c1691c744b4395194290db6a0df5e9/skills/react-best-practices
- https://github.com/vercel-labs/agent-skills/blob/4559f18a20c1691c744b4395194290db6a0df5e9/skills/react-best-practices/AGENTS.md

The upstream guide contains 70 React and Next.js rules. This reference retains portable client guidance and translates framework-specific examples to Inertia 3 and Vite.

## 0. Runtime and ownership boundary

- React runs only in the browser. Preserve the existing `createRoot` mount and compiled Vite assets.
- Laravel and Inertia produce the initial document, route response, and structured page props. React does not render HTML on the server or load page data through a JavaScript server.
- Inertia owns server-backed navigation, form submissions, redirects, validation errors, flash data, partial and deferred props, prefetching, polling transport, prop merging, and browser history.
- React owns component rendering, local presentation state, event handling, browser APIs, and accessible interaction behavior.
- Do not add an SSR entry, Node renderer, React Server Components, Server Actions, server loaders, JavaScript API layer, `renderToString`, `renderToPipeableStream`, or `hydrateRoot`.
- Do not enable Inertia SSR. A change to this client-only architecture requires an explicit project decision, not a local performance optimization.

## 1. Async and network work

- Check cheap synchronous conditions before starting optional async work.
- Defer `await` until the result is needed and run independent operations concurrently with `Promise.all`.
- Start useful prefetching from clear user intent such as hover or focus when the destination is likely.
- Use Inertia deferred props and partial reloads to avoid blocking unrelated page regions.
- Avoid request waterfalls created by React effects. If data belongs to a page, load it in the Laravel controller and deliver it as an Inertia prop.
- Do not fetch page data in `useEffect` to imitate a server loader. Use an Inertia visit, partial reload, poll, form, or existing intentional Laravel endpoint.
- Do not add a client fetching library to deduplicate data already owned by Inertia.

## 2. Bundle loading

- Keep import paths statically analyzable. Use explicit loader maps when selecting dynamic modules.
- Rely on the existing `import.meta.glob("./pages/**/*.tsx")` page split and Vite's production output.
- Dynamically import genuinely heavy, non-critical features only when bundle analysis supports the change.
- Defer non-critical third-party code until it is needed, but preserve error reporting and accessibility.
- Avoid broad internal barrel files that unintentionally pull large modules into common chunks.
- Do not deep-import libraries such as `lucide-react` when their supported typed entry point tree-shakes correctly; verify the actual Vite bundle before changing import style.

## 3. Inertia state and polling

- Use `usePoll` with explicit `only` props, overlap/cancellation behavior, and cleanup. Do not create unmanaged timers.
- Include every shared or page prop needed to keep refreshed UI coherent, including navigation counts and Horizon state where applicable.
- Preserve the URL when a background refresh must not disturb cursor, tab, sort, filter, or detail state.
- Keep merge intent explicit for prepend/append behavior and reconcile visible items when the scope changes.
- Store current poll controls in a ref when hook-returned control identity can change; effects should depend on the conditions that start or stop polling.
- Test refresh behavior across multiple responses, not only the first render.

## 4. Derived state and rerenders

- Derive values during render rather than copying props into state with an effect.
- Subscribe to the smallest meaningful state, such as a boolean or active identifier, instead of a large object when possible.
- Use functional state updates when the next value depends on the previous value.
- Use lazy `useState` initialization for storage reads or expensive initialization.
- Put interaction logic in the event that caused it instead of a follow-up effect.
- Keep effect dependencies primitive or otherwise stable when practical. Split unrelated effect responsibilities.
- Do not define components inside other components.
- Hoist non-primitive default values when referential stability matters.
- Use refs for high-frequency transient values that do not affect rendering.
- Use `startTransition` or `useDeferredValue` when measured expensive rendering should not block typing or interaction.

## 5. Memoization

- Memoize expensive computation or a component whose rerender cost is demonstrated and whose props can remain stable.
- Do not wrap simple primitive expressions in `useMemo`.
- Treat `useMemo` as an optimization, not a semantic guarantee. Code must remain correct if React discards cached values.
- Avoid passing freshly allocated objects and arrays to memoized children when doing so defeats an otherwise useful memo boundary.
- Prefer a clearer state/data boundary over a web of callback and value memoization.

## 6. Rendering and lists

- Use stable domain identifiers as keys. Never use a cursor position for live Horizon collections.
- Use an explicit ternary when `&&` could render `0` or another unintended value.
- Hoist static JSX only when it meaningfully avoids repeated construction.
- Consider `content-visibility` or virtualization for a measured large-list cost; Inertia `InfiniteScroll` already limits many route payloads and remains the first project-native choice.
- Keep list merge, ordering, selection, and new-entry indicators correct before optimizing their render path.
- Animate a wrapper rather than complex SVG geometry, and keep SVG coordinate precision reasonable for package-owned artwork.
- Prefer compositor-friendly animation and disable expensive chart animation when live updates make it distracting or costly.

## 7. Browser state, storage, and browser APIs

- Initialize browser-only values without a visible flicker. This application uses a client mount, so do not add hydration workarounds such as `suppressHydrationWarning`.
- Version and validate persisted local-storage data when its shape may evolve. Store the minimum required value.
- Cache repeated storage reads within a render path, but do not let a cache obscure changes from another tab or system theme events.
- Deduplicate global event listeners and always clean them up. Use passive listeners for scroll/touch events that never cancel default behavior.
- Batch DOM reads and writes. Never call layout-measurement APIs during render.
- Defer non-critical browser work with an idle callback only when it has a timeout/fallback and is safe to delay.

## 8. JavaScript hot paths

- Build a `Map` or `Set` for repeated membership or lookup work over the same collection.
- Use `toSorted()` when preserving input immutability matters.
- Combine repeated array passes only in a demonstrated hot path; readable `map`/`filter` pipelines are acceptable otherwise.
- Exit early from expensive work when a cheap condition resolves the result.
- Hoist regular expressions and invariant computations out of hot loops.
- Find min/max with a loop instead of sorting solely to obtain an extreme value.
- Cache deterministic expensive function results only with bounded lifetime and invalidation appropriate to the data.

## Explicitly excluded upstream rules

Do not apply React SSR, hydration-server, React Server Component serialization, Server Actions, `React.cache`, cross-request JavaScript LRU caches, Next.js `after()`, `next/dynamic`, `next/link`, Next.js resource loading, `optimizePackageImports`, or SWR guidance to this package. Their architectural concerns belong to Laravel, Inertia, or Vite here.
