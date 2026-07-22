# Horizon New Dawn interface adjustments

These are durable review gates distilled from prior implementation and browser-feedback sessions. Apply the principles without preserving obsolete pixel values or one-off copy.

## Fidelity before invention

- Open every supplied screen and asset before implementing. Compare the rendered page at matching dimensions instead of reconstructing the design from memory.
- Use the supplied icon language and current navigation icon map. Keep related empty states and navigation visually consistent.
- Do not add visual garnish merely because a component library offers it. Status pills and badges stay text-only unless the current design explicitly includes an icon.
- Use project shadcn/Base UI components first. Create a custom primitive only after confirming the installed set cannot express the design.

## Fix systems, then sweep consumers

- Repeated border, padding, radius, hover, header, tab, or table defects usually belong in a shared primitive. Identify that seam, then inspect every consumer.
- Do not stop after correcting the route where the user noticed the problem. Sweep equivalent pages and states.
- Preserve deliberate differences between cards, tables, detail panels, and destructive actions; shared does not mean visually identical.
- Keep form inputs and compact search/filter inputs as intentional variants instead of gradually collapsing their radius, height, and spacing into one accidental style.
- When a tab changes a panel, verify that sibling content outside the tab's ownership remains visible.

## Validate meaningful states

- Exercise populated data, long queue names and UUIDs, empty results, realistic skeletons, refreshing/polling, errors, disabled actions, and destructive confirmations.
- Use the isolated demo workload when real queue behavior matters. Empty screens and mocked component renders do not prove a Horizon workflow.
- Verify desktop, phone, and the intermediate width where a fixed sidebar begins to constrain content. Check menu dismissal after navigation and horizontal overflow.
- Check light, dark, and system theme behavior when shared tokens or controls change.
- Use tabular numerals consistently for metrics and numeric columns. Keep ordinary identifiers in the surrounding typeface unless the design treats them as code.

## Respect product semantics

- Derive labels and states from Horizon/Laravel behavior and configured thresholds. Avoid broad terms such as "health" when the interface is specifically reporting a wait-threshold boundary.
- Keep actions where the established page hierarchy expects them. Avoid scattering related actions across rows, headers, and overflow menus without a product reason.
- Prefer quiet, information-dense presentation. Add icons, animation, copy affordances, or badges only when they clarify an action or state.
- Whole-row navigation must coexist with nested links, menus, and disabled controls. A disabled child action must never click through and activate the row.

## Prove the consumer

- The package owns source and compiled assets, while a consuming Laravel application serves the UI. Rebuild, run the package installer in the consumer, and reload the exact named URL.
- When no other consumer is named, prove `https://horizon-demo.nmbp/horizon/` after `php artisan horizon-new-dawn:install --force --no-interaction` in `/Users/nckrtl/apps/horizon-demo`.
- Confirm the browser loaded the new asset hashes. Inspect console and network failures and retest routes across at least one polling/cache interval.
- A green package build, Workbench page, or stale browser tab is not final UI evidence.
