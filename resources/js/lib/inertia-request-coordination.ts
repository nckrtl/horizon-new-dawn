import type { VisitOptions } from "@inertiajs/core";
import { router } from "@inertiajs/react";

export function backgroundVisitOptions(_href: string, options: VisitOptions): VisitOptions {
  const isBackgroundGet =
    options.async === true &&
    (options.method === undefined || options.method === "get") &&
    options.prefetch !== true &&
    options.component == null;

  if (!isBackgroundGet || "preserveUrl" in options) {
    return options;
  }

  return { ...options, preserveUrl: true };
}

export function registerLatestInertiaVisitWins() {
  let foregroundVisitInFlight = false;
  const removeBeforeListener = router.on("before", (event) => {
    const visit = event.detail.visit as typeof event.detail.visit & { deferredProps?: boolean };

    if (visit.prefetch) {
      return;
    }

    if (visit.async && foregroundVisitInFlight && !visit.deferredProps) {
      return false;
    }

    router.cancelAll({ async: true, prefetch: false, sync: false });
  });
  const removeStartListener = router.on("start", (event) => {
    if (!event.detail.visit.async) {
      foregroundVisitInFlight = true;
    }
  });
  const removeFinishListener = router.on("finish", (event) => {
    if (!event.detail.visit.async) {
      foregroundVisitInFlight = false;
    }
  });

  return () => {
    removeBeforeListener();
    removeStartListener();
    removeFinishListener();
  };
}
