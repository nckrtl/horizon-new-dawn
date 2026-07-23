import { router, usePage, usePoll } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";

type AutoLoadItem = {
  id: string;
};

const emptyItems: readonly never[] = [];
const emptyProps: readonly string[] = [];

function reconcileResetItems<T extends AutoLoadItem>(
  current: readonly T[],
  incoming: readonly T[],
  hasNextPage: boolean,
): readonly T[] {
  if (
    !hasNextPage ||
    current.length === 0 ||
    incoming.length === 0 ||
    incoming.length > current.length
  ) {
    return incoming;
  }

  const incomingIds = new Set(incoming.map((item) => item.id));

  if (!current.some((item) => incomingIds.has(item.id))) {
    return incoming;
  }

  return [...incoming, ...current.filter((item) => !incomingIds.has(item.id))].slice(
    0,
    current.length,
  );
}

export function useAutoLoad<T extends AutoLoadItem = AutoLoadItem>({
  enabled,
  prop,
  interval,
  cursor = "starting_at",
  items = emptyItems,
  additionalProps = emptyProps,
  scope = "default",
  polling = true,
}: {
  enabled: boolean;
  prop: string;
  interval: number;
  cursor?: string;
  items?: readonly T[];
  additionalProps?: readonly string[];
  scope?: string;
  polling?: boolean;
}) {
  const scrollProp = usePage().scrollProps?.[prop];
  const isReset = scrollProp?.reset === true;
  const hasNextPage = scrollProp?.nextPage !== null && scrollProp?.nextPage !== undefined;
  const latestItems = useRef(items);
  const visibleItemsRef = useRef(items);
  const scopeRef = useRef(scope);
  const [visibleItems, setVisibleItems] = useState(items);
  const [hasNewEntries, setHasNewEntries] = useState(false);

  useEffect(() => {
    if (scopeRef.current !== scope) {
      scopeRef.current = scope;
      latestItems.current = items;
      visibleItemsRef.current = items;
      setVisibleItems(items);
      setHasNewEntries(false);

      return;
    }

    const currentItems = visibleItemsRef.current;
    const refreshedItems = isReset ? reconcileResetItems(currentItems, items, hasNextPage) : items;

    latestItems.current = refreshedItems;

    if (enabled) {
      visibleItemsRef.current = refreshedItems;
      setVisibleItems(refreshedItems);
      setHasNewEntries(false);

      return;
    }

    const visibleIds = new Set(currentItems.map((item) => item.id));

    if (visibleIds.size === 0) {
      visibleItemsRef.current = refreshedItems;
      setVisibleItems(refreshedItems);
      setHasNewEntries(false);

      return;
    }

    if (refreshedItems.length === 0) {
      visibleItemsRef.current = refreshedItems;
      setVisibleItems(refreshedItems);
      setHasNewEntries(false);

      return;
    }

    const firstVisibleIndex = refreshedItems.findIndex((item) => visibleIds.has(item.id));

    if (firstVisibleIndex > 0) {
      const refreshedById = new Map(refreshedItems.map((item) => [item.id, item]));
      const heldItems = isReset
        ? currentItems
            .filter((item) => hasNextPage || refreshedById.has(item.id))
            .map((item) => refreshedById.get(item.id) ?? item)
        : refreshedItems.slice(firstVisibleIndex);

      visibleItemsRef.current = heldItems;
      setVisibleItems(heldItems);
      setHasNewEntries(true);

      return;
    }

    if (firstVisibleIndex === 0) {
      visibleItemsRef.current = refreshedItems;
      setVisibleItems(refreshedItems);
      setHasNewEntries(false);

      return;
    }

    visibleItemsRef.current = refreshedItems;
    setVisibleItems(refreshedItems);
    setHasNewEntries(false);
  }, [enabled, hasNextPage, isReset, items, scope]);

  const loadNewEntries = useCallback(() => {
    visibleItemsRef.current = latestItems.current;
    setVisibleItems(latestItems.current);
    setHasNewEntries(false);
  }, []);

  const requestOptions = () => ({
    data: { [cursor]: undefined },
    only: Array.from(
      new Set(
        enabled
          ? [prop, ...additionalProps, "horizon", "navigationCounts"]
          : [prop, ...additionalProps],
      ),
    ),
    preserveUrl: true,
    reset: [prop],
    showProgress: false,
  });
  const requestOptionsRef = useRef(requestOptions);
  const wasEnabledRef = useRef(enabled);

  requestOptionsRef.current = requestOptions;

  const poll = usePoll(interval, () => requestOptionsRef.current(), {
    autoStart: false,
    mode: "cancel",
  });
  const pollRef = useRef(poll);

  pollRef.current = poll;

  useEffect(() => {
    const controls = pollRef.current;

    if (polling && interval > 0) {
      if (enabled && !wasEnabledRef.current) {
        router.reload(requestOptionsRef.current());
      }

      controls.start();
    } else {
      controls.stop();
    }

    wasEnabledRef.current = enabled;

    return () => controls.stop();
  }, [enabled, interval, polling]);

  const scopeChanged = scopeRef.current !== scope;

  return {
    items: scopeChanged ? items : visibleItems,
    hasNewEntries: !enabled && !scopeChanged && hasNewEntries,
    loadNewEntries,
  };
}
