# Web interface guidelines

Adapted for Horizon New Dawn from Vercel Labs' MIT-licensed Web Interface Guidelines at commit `4e799d45c17aec1498c269287a83b9dba22b966b`:

- https://github.com/vercel-labs/web-interface-guidelines
- https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/4e799d45c17aec1498c269287a83b9dba22b966b/command.md

The upstream checklist is useful evidence, not a substitute for inspecting rendered semantics or project intent.

## Accessibility and focus

- Prefer semantic HTML before ARIA. Use buttons for actions and links for navigation.
- Give icon-only controls accessible names. Mark purely decorative icons hidden from assistive technology.
- Associate form controls with visible labels or an appropriate accessible name.
- Announce meaningful asynchronous feedback such as validation and operation results.
- Keep headings hierarchical and provide a route-level main landmark and skip path where applicable.
- Preserve a visible `:focus-visible` treatment. Never remove outlines without an equivalent replacement.
- Check keyboard reachability, activation, focus order, dialog trapping, and focus return in the rendered DOM.

Native buttons and links already provide keyboard behavior. Do not require redundant key handlers. A clickable table row containing a real link may be a pointer convenience; verify keyboard users can reach the same destination before calling it inaccessible.

## Forms and destructive actions

- Use meaningful names, input types, `inputmode`, and autocomplete values where they improve the actual field.
- Keep labels and controls in one useful hit target. Never block paste.
- Start processing feedback only after submission begins; show validation beside the relevant field and focus the first error when practical.
- Confirm destructive actions or provide a reliable undo path.
- Warn about unsaved changes when the interface can genuinely lose user input.

Do not apply `autocomplete="off"`, placeholder ellipses, or `autoFocus` as blanket rules. For `autoFocus`, validate desktop and mobile behavior and retain it only when the focused field is clearly the primary action.

## Motion and interaction

- Honor reduced-motion preferences for non-essential animation.
- Prefer transform and opacity animation; list transitioned properties instead of `transition: all`.
- Make animations interruptible and keep hover, active, focus, disabled, and processing states visually coherent.
- Ensure touch targets and overlays work without accidental background scrolling or clipped content.

## Typography and content

- Use tabular numerals for comparable metrics and columns.
- Format locale-sensitive numbers and dates with `Intl.*` unless the product deliberately requires a stable technical representation.
- Handle empty, short, typical, long, and unbroken values without overlap or layout escape.
- Give errors a useful next step. Keep action labels specific.
- Use typographic polish such as balanced headings and proper ellipses when it matches the product voice; do not impose title case or ampersands mechanically.

## Images, layout, and rendering

- Give images intrinsic dimensions, meaningful alternative text, and appropriate lazy/eager loading.
- Prefer flex/grid to runtime measurement. Avoid layout reads during render and interleaved DOM reads/writes.
- Ensure flex children can shrink and long content can wrap or truncate intentionally.
- Check safe areas, horizontal overflow, sticky headers, sidebars, dialogs, and sheets at phone, tablet, intermediate, and desktop widths.
- Respect hydration safety: controlled values need change handlers, client-only values need stable initialization, and hydration suppression needs a narrow justification.

## Navigation and state

- Use Inertia `Link` or genuine anchors for navigation so modified clicks and browser history work.
- Preserve shareable state such as active tabs, meaningful filters, sort direction, and cursor/pagination position in the URL when users benefit from reload or deep-link behavior.
- Ephemeral local search does not automatically require URL state. Decide from product behavior rather than the presence of `useState`.
- Prefer Inertia navigation, prefetching, polling, deferred props, and infinite scrolling over introducing a parallel client data layer.

## Performance calibration

- Measure before requiring virtualization. Horizon New Dawn already bounds and incrementally loads many lists through Inertia.
- Use `content-visibility`, virtualization, or component splitting when rendered volume or profiling demonstrates a real cost.
- Avoid layout thrashing, repeated global listeners, oversized assets, and work on every keystroke that can be derived or deferred.
- Audit the consuming bundle and network, not only source imports.

## Theme and platform behavior

- Use semantic theme tokens and keep native controls, scrollbars, and `color-scheme` aligned with the resolved theme.
- Verify contrast in normal, hover, active, focus, disabled, and destructive states in both light and dark themes.
- Audit shadcn/Base UI abstractions through their rendered DOM; do not flag a wrapper merely because lower-level ARIA is supplied by the primitive.
