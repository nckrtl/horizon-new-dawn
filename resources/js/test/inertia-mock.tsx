import type { ReactNode } from "react";
import { vi } from "vitest";

import type { HorizonPageProps } from "@/types/page";

export const defaultHorizonPageProps: Pick<HorizonPageProps, "horizon" | "monitoredTags" | "meta"> =
  {
    horizon: {
      baseUrl: "/horizon",
      pollInterval: 5000,
      status: "running",
      processing: false,
      maintenanceMode: false,
    },
    monitoredTags: [],
    meta: {
      title: "Test",
      activeNavigation: "dashboard",
    },
  };

export function inertiaTestMocks(options?: {
  props?: Partial<HorizonPageProps>;
  includeRouter?: boolean;
}) {
  const props = {
    ...defaultHorizonPageProps,
    ...options?.props,
    horizon: {
      ...defaultHorizonPageProps.horizon,
      ...options?.props?.horizon,
    },
  };

  return {
    Head: () => null,
    Link: ({
      children,
      href,
      className,
      prefetch: _prefetch,
      preserveState: _preserveState,
      ...rest
    }: {
      children: ReactNode;
      href: string;
      className?: string;
      [key: string]: unknown;
    }) => (
      <a className={className} href={href} {...rest}>
        {children}
      </a>
    ),
    usePage: () => ({ props }),
    router: {
      cancelAll: vi.fn(),
      visit: vi.fn(),
      prefetch: vi.fn(),
      reload: vi.fn(),
      post: vi.fn(),
      delete: vi.fn(),
      replace: vi.fn(({ url }: { url?: string }) => {
        if (url !== undefined) {
          window.history.replaceState(window.history.state, "", url);
        }
      }),
    },
    usePoll: vi.fn(() => ({ start: vi.fn(), stop: vi.fn() })),
    InfiniteScroll: ({ children }: { children: ReactNode }) => <>{children}</>,
  };
}
