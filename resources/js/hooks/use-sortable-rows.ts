import { useCallback, useMemo, useState } from "react";

import { currentQueryParameter, replaceCurrentQuery } from "@/lib/url-query";

export type SortDirection = "asc" | "desc";
export type SortValue = string | number | null;
export type SortColumn<T> = {
  key: string;
  value: (row: T) => SortValue;
};
export type SortState = {
  key: string;
  direction: SortDirection;
};
export type SortUrlOptions = {
  persist?: boolean;
  prefix?: string;
};

const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: "base" });

function compareValues(left: SortValue, right: SortValue, direction: SortDirection) {
  if (left === null && right === null) {
    return 0;
  }

  if (left === null) {
    return 1;
  }

  if (right === null) {
    return -1;
  }

  const comparison =
    typeof left === "number" && typeof right === "number"
      ? left - right
      : collator.compare(String(left), String(right));

  return direction === "asc" ? comparison : comparison * -1;
}

export function sortRows<T>(
  rows: readonly T[],
  columns: readonly SortColumn<T>[],
  sort: SortState | null,
): T[] {
  if (sort === null) {
    return [...rows];
  }

  const column = columns.find((candidate) => candidate.key === sort.key);

  if (!column) {
    return [...rows];
  }

  return rows
    .map((row, index) => ({ row, index }))
    .sort((left, right) => {
      const comparison = compareValues(
        column.value(left.row),
        column.value(right.row),
        sort.direction,
      );

      return comparison === 0 ? left.index - right.index : comparison;
    })
    .map(({ row }) => row);
}

function queryParameterNames(prefix?: string) {
  return prefix
    ? { sort: `${prefix}_sort`, direction: `${prefix}_direction` }
    : { sort: "sort", direction: "direction" };
}

function initialSort<T>(columns: readonly SortColumn<T>[], options: SortUrlOptions) {
  if (!options.persist) {
    return null;
  }

  const parameters = queryParameterNames(options.prefix);
  const key = currentQueryParameter(parameters.sort);
  const direction = currentQueryParameter(parameters.direction);

  if (
    key === null ||
    (direction !== "asc" && direction !== "desc") ||
    !columns.some((column) => column.key === key)
  ) {
    return null;
  }

  return { key, direction } satisfies SortState;
}

export function useSortableRows<T>(
  rows: readonly T[],
  columns: readonly SortColumn<T>[],
  options: SortUrlOptions = {},
) {
  const { persist = false, prefix } = options;
  const [sort, setSort] = useState<SortState | null>(() =>
    initialSort(columns, { persist, prefix }),
  );
  const sortedRows = useMemo(() => sortRows(rows, columns, sort), [columns, rows, sort]);
  const toggle = useCallback(
    (key: string) => {
      setSort((current) => {
        const next: SortState = {
          key,
          direction: current?.key === key && current.direction === "asc" ? "desc" : "asc",
        };

        if (persist) {
          const parameters = queryParameterNames(prefix);
          replaceCurrentQuery({
            [parameters.sort]: next.key,
            [parameters.direction]: next.direction,
          });
        }

        return next;
      });
    },
    [persist, prefix],
  );

  return { rows: sortedRows, sort, toggle };
}
