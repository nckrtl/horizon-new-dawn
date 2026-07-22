import "../css/app.css";

import { createInertiaApp, router } from "@inertiajs/react";
import { TooltipProvider } from "@/components/ui/tooltip";
import { Toaster } from "@/components/ui/sonner";
import { HorizonLayout } from "@/layouts/horizon-layout";
import { recoverFromAssetVersionChange } from "@/lib/asset-version-recovery";
import {
  backgroundVisitOptions,
  registerLatestInertiaVisitWins,
} from "@/lib/inertia-request-coordination";
import { pageModulePath } from "@/lib/page-name";
import { recoverFromPreloadError } from "@/lib/preload-error-recovery";
import { StrictMode, type ComponentType } from "react";
import { createRoot } from "react-dom/client";

window.addEventListener("vite:preloadError", (event) => {
  recoverFromPreloadError(event, () => window.location.reload());
});

router.on("location", (event) =>
  recoverFromAssetVersionChange(event, () => {
    window.setTimeout(() => window.location.reload(), 0);
  }),
);

registerLatestInertiaVisitWins();

void createInertiaApp({
  defaults: {
    visitOptions: backgroundVisitOptions,
  },
  title: (title) => title,
  progress: {
    color: "var(--primary)",
  },
  layout: () => HorizonLayout,
  resolve: async (name) => {
    const pages = import.meta.glob<{ default: ComponentType<any> }>("./pages/**/*.tsx");
    const page = pages[pageModulePath(name)];

    if (!page) {
      throw new Error(`Unknown Inertia page: ${name}`);
    }

    return (await page()).default;
  },
  setup({ el, App, props }) {
    if (!el) {
      throw new Error("Inertia mount element is missing.");
    }

    createRoot(el).render(
      <StrictMode>
        <TooltipProvider>
          <Toaster />
          <App {...props} />
        </TooltipProvider>
      </StrictMode>,
    );
  },
});
