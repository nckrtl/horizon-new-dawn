export function isInteractiveTarget(target: EventTarget | null): boolean {
  return (
    target instanceof Element &&
    target.closest('a, button, input, select, textarea, [role="menuitem"], [role="dialog"]') !==
      null
  );
}
