---
name: reviewing-horizon-interface
description: Use when reviewing Horizon New Dawn UI code or rendered pages for design fidelity, accessibility, responsive behavior, interaction consistency, empty/loading states, or browser-visible regressions.
---

# Reviewing the Horizon Interface

## Overview

Review the interface at the boundary the user experiences. Treat supplied designs and selected browser evidence as the visual authority, then apply web-interface guidance without turning contextual recommendations into mechanical findings.

## Review contract

1. Establish the authority for the task: explicit user direction and selected browser evidence, supplied design artifacts, current project conventions and shared components, then general guidelines.
2. Read [interface-guidelines.md](references/interface-guidelines.md). Read [project-adjustments.md](references/project-adjustments.md) for every Horizon New Dawn review.
3. Inspect shared primitives before reporting repeated page-level symptoms. One table, header, badge, spacing, or navigation defect may affect many routes.
4. Review applicable states, not only the easiest screenshot: populated, empty, loading, refreshing, error, long content, destructive actions, light/dark/system theme, and responsive widths.
5. Validate the rendered package in the exact consumer URL named by the user. When none is named, use `https://horizon-demo.nmbp/horizon/`. A package build or Testbench render is not consumer proof.
6. If changes are authorized, rebuild and publish package assets into the consumer before browser validation. If the task is review-only, do not mutate files or runtime state.
7. Report concise findings grouped by file or page. Give severity, exact location, observed impact, and the smallest coherent fix. Distinguish confirmed defects from product choices and measurements that still need browser proof.

## Hard gates

- Reproduce the supplied design; do not replace it with a generic dashboard interpretation.
- Reuse installed shadcn/Base UI primitives and existing semantic theme tokens before creating custom UI.
- Do not add decorative icons, especially inside badges or status pills, unless the design or user explicitly calls for them.
- Native controls inherit keyboard behavior; audit the rendered semantics before demanding redundant handlers.
- Pure UI changes do not add or modify automated tests unless the user explicitly requests them. Validate them in the browser.
- Source, typecheck, and build success are supporting evidence, never substitutes for the exact published consumer.

## Review output

Lead with the verdict. List actionable findings first, followed by verified passes and the exact browser routes, states, viewports, console, and network evidence checked. If no findings remain, say so directly and name any unverified boundary.
