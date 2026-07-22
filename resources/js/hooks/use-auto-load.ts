import { usePoll } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";

type AutoLoadItem = {
  id: string;
};

const emptyItems: readonly never[] = [];
const emptyProps: readonly string[] = [];

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
  const latestItems = useRef(items);
  const visibleItemsRef = useRef(items);
  const scopeRef = useRef(scope);
  const [visibleItems, setVisibleItems] = useState(items);
  const [hasNewEntries, setHasNewEntries] = useState(false);

  useEffect(() => {
    latestItems.current = items;

    if (scopeRef.current !== scope) {
      scopeRef.current = scope;
      visibleItemsRef.current = items;
      setVisibleItems(items);
      setHasNewEntries(false);

      return;
    }

    if (enabled) {
      visibleItemsRef.current = items;
      setVisibleItems(items);
      setHasNewEntries(false);

      return;
    }

    const visibleIds = new Set(visibleItemsRef.current.map((item) => item.id));

    if (visibleIds.size === 0) {
      visibleItemsRef.current = items;
      setVisibleItems(items);
      setHasNewEntries(false);

      return;
    }

    if (items.length === 0) {
      visibleItemsRef.current = items;
      setVisibleItems(items);
      setHasNewEntries(false);

      return;
    }

    const firstVisibleIndex = items.findIndex((item) => visibleIds.has(item.id));

    if (firstVisibleIndex > 0) {
      const heldItems = items.slice(firstVisibleIndex);

      visibleItemsRef.current = heldItems;
      setVisibleItems(heldItems);
      setHasNewEntries(true);

      return;
    }

    if (firstVisibleIndex === 0) {
      visibleItemsRef.current = items;
      setVisibleItems(items);
      setHasNewEntries(false);

      return;
    }

    visibleItemsRef.current = items;
    setVisibleItems(items);
    setHasNewEntries(false);
  }, [enabled, items, scope]);

  const loadNewEntries = useCallback(() => {
    visibleItemsRef.current = latestItems.current;
    setVisibleItems(latestItems.current);
    setHasNewEntries(false);
  }, []);

  const poll = usePoll(
    interval,
    () => ({
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
    }),
    { autoStart: false, mode: "cancel" },
  );
  const pollRef = useRef(poll);

  pollRef.current = poll;

  useEffect(() => {
    const controls = pollRef.current;

    if (polling && interval > 0) {
      controls.start();
    } else {
      controls.stop();
    }

    return () => controls.stop();
  }, [interval, polling]);

  const scopeChanged = scopeRef.current !== scope;

  return {
    items: enabled || scopeChanged ? items : visibleItems,
    hasNewEntries: !enabled && !scopeChanged && hasNewEntries,
    loadNewEntries,
  };
}
