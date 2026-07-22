import { router } from "@inertiajs/react";

export type QueryParameterValue = string | number | null | undefined;

function currentUrl() {
  return new URL(window.location.href);
}

function relativeUrl(url: URL) {
  return `${url.pathname}${url.search}${url.hash}`;
}

export function currentQueryParameter(name: string) {
  if (typeof window === "undefined") {
    return null;
  }

  return currentUrl().searchParams.get(name);
}

export function currentUrlWithQuery(
  parameters: Record<string, QueryParameterValue>,
  remove: readonly string[] = [],
) {
  return urlWithCurrentQuery(relativeUrl(currentUrl()), parameters, remove);
}

export function urlWithCurrentQuery(
  destination: string,
  parameters: Record<string, QueryParameterValue>,
  remove: readonly string[] = [],
) {
  const source = currentUrl();
  const url = new URL(destination, source.origin);

  source.searchParams.forEach((value, name) => {
    if (!url.searchParams.has(name)) {
      url.searchParams.set(name, value);
    }
  });

  remove.forEach((name) => url.searchParams.delete(name));

  Object.entries(parameters).forEach(([name, value]) => {
    if (value === null || value === undefined || value === "") {
      url.searchParams.delete(name);
      return;
    }

    url.searchParams.set(name, String(value));
  });

  return relativeUrl(url);
}

export function replaceCurrentQuery(parameters: Record<string, QueryParameterValue>) {
  if (typeof window === "undefined") {
    return;
  }

  const url = currentUrlWithQuery(parameters);

  if (url === relativeUrl(currentUrl())) {
    return;
  }

  router.cancelAll({ async: true, prefetch: false, sync: false });
  router.replace({ url, preserveScroll: true, preserveState: true });
}
