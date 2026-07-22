import { act, renderHook } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { useColorScheme } from "@/hooks/use-color-scheme";

const media = vi.hoisted(() => ({
  matches: false,
  listeners: new Set<(event: MediaQueryListEvent) => void>(),
}));

function installMatchMedia() {
  Object.defineProperty(window, "matchMedia", {
    configurable: true,
    value: vi.fn().mockImplementation(() => ({
      matches: media.matches,
      media: "(prefers-color-scheme: dark)",
      onchange: null,
      addEventListener: (_type: string, listener: (event: MediaQueryListEvent) => void) =>
        media.listeners.add(listener),
      removeEventListener: (_type: string, listener: (event: MediaQueryListEvent) => void) =>
        media.listeners.delete(listener),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  });
}

describe("useColorScheme", () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove("dark");
    document.documentElement.style.colorScheme = "";
    media.matches = false;
    media.listeners.clear();
    installMatchMedia();
  });

  it("cycles system, dark, and light while persisting the selection", () => {
    const { result } = renderHook(() => useColorScheme());

    expect(result.current.scheme).toBe("system");
    expect(result.current.resolvedScheme).toBe("light");

    act(() => result.current.cycle());
    expect(result.current.scheme).toBe("dark");
    expect(localStorage.getItem("horizonColorScheme")).toBe("dark");
    expect(document.documentElement).toHaveClass("dark");

    act(() => result.current.cycle());
    expect(result.current.scheme).toBe("light");
    expect(localStorage.getItem("horizonColorScheme")).toBe("light");
    expect(document.documentElement).not.toHaveClass("dark");

    act(() => result.current.cycle());
    expect(result.current.scheme).toBe("system");
    expect(localStorage.getItem("horizonColorScheme")).toBe("system");
  });

  it("tracks operating system changes while system mode is selected", () => {
    const { result } = renderHook(() => useColorScheme());

    act(() => {
      media.matches = true;
      for (const listener of media.listeners) {
        listener({ matches: true } as MediaQueryListEvent);
      }
    });

    expect(result.current.resolvedScheme).toBe("dark");
    expect(document.documentElement).toHaveClass("dark");
  });
});
