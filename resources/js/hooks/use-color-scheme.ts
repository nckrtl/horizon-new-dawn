import { useCallback, useEffect, useMemo, useState } from "react";

export type ColorScheme = "system" | "dark" | "light";
export type ResolvedColorScheme = Exclude<ColorScheme, "system">;

const storageKey = "horizonColorScheme";
const darkModeQuery = "(prefers-color-scheme: dark)";
const schemes: ColorScheme[] = ["system", "dark", "light"];

function storedScheme(): ColorScheme {
  const value = window.localStorage.getItem(storageKey);

  return value === "dark" || value === "light" || value === "system" ? value : "system";
}

function systemScheme(): ResolvedColorScheme {
  return window.matchMedia(darkModeQuery).matches ? "dark" : "light";
}

function applyScheme(scheme: ResolvedColorScheme) {
  document.documentElement.classList.toggle("dark", scheme === "dark");
  document.documentElement.style.colorScheme = scheme;
}

export function useColorScheme() {
  const [scheme, setScheme] = useState<ColorScheme>(storedScheme);
  const [systemPreference, setSystemPreference] = useState<ResolvedColorScheme>(systemScheme);
  const resolvedScheme = scheme === "system" ? systemPreference : scheme;

  useEffect(() => {
    const media = window.matchMedia(darkModeQuery);
    const handleChange = (event: MediaQueryListEvent) => {
      setSystemPreference(event.matches ? "dark" : "light");
    };

    media.addEventListener("change", handleChange);

    return () => media.removeEventListener("change", handleChange);
  }, []);

  useEffect(() => {
    window.localStorage.setItem(storageKey, scheme);
    applyScheme(resolvedScheme);
  }, [resolvedScheme, scheme]);

  const cycle = useCallback(() => {
    setScheme((current) => schemes[(schemes.indexOf(current) + 1) % schemes.length]);
  }, []);

  return useMemo(() => ({ scheme, resolvedScheme, cycle }), [cycle, resolvedScheme, scheme]);
}
